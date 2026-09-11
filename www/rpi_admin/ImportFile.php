<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ImportFile - Shell-free handling of an uploaded database archive.
 *
 * The DB import endpoint used to decide what it had been given by running
 * `/usr/bin/file <path> | /usr/bin/awk '{print $2}'` through a shell, and then
 * decompressed with `gzip -dc <path> > <out>` / `unzip -p <path> > <out>` -
 * again as shell text. The paths involved are PHP-generated, so there was no
 * direct injection, but the whole flow depended on a shell and on parsing
 * another program's prose output to decide which command to run next.
 *
 * This helper removes both dependencies:
 *  - detectType() reads the file's MAGIC BYTES. It returns the same three labels
 *    the old `file | awk` pipeline produced ("SQLite", "gzip", "Zip") plus
 *    "unknown", so the calling switch is unchanged.
 *  - extract() runs the decompressor via proc_open() with an ARGV ARRAY and
 *    writes its stdout straight to the destination file handle. No shell, no
 *    redirection assembled as a string.
 *  - the extracted result is confirmed to be a SQLite database by its header
 *    rather than by `file`'s description.
 *
 * sanitizeObjects() constrains the object list to the identifiers the importer
 * actually understands, so the trigger file consumed by parse_bind_logs.php can
 * never contain anything unexpected.
 */

if (!class_exists('ImportFile')) {
    class ImportFile {

        /** SQLite database file header (RFC-free, see the SQLite file format spec). */
        const MAGIC_SQLITE = "SQLite format 3\0";

        /** gzip member header. */
        const MAGIC_GZIP = "\x1f\x8b";

        /** Local file header of a ZIP archive. */
        const MAGIC_ZIP = "PK\x03\x04";

        /** Wall-clock bound (seconds) for a decompression run. */
        const TIMEOUT_SEC = 300;

        /**
         * Object groups importSQLiteDB() knows how to import (scripts/import_db.php).
         * `bl`/`wl` are what the UI sends; `block`/`allow` are accepted as aliases
         * because the importer handles both.
         */
        const ALLOWED_OBJECTS = [
            'assets',
            'q_raw', 'h_raw',
            'q_5m', 'h_5m',
            'q_1h', 'h_1h',
            'q_1d', 'h_1d',
            'bl', 'block',
            'wl', 'allow',
        ];

        /**
         * Identify an uploaded file from its leading bytes.
         *
         * @param string $path Path to the uploaded file.
         * @return string "SQLite", "gzip", "Zip" or "unknown".
         */
        public static function detectType(string $path): string {
            $head = self::head($path, strlen(self::MAGIC_SQLITE));
            if ($head === null) {
                return 'unknown';
            }

            if (strncmp($head, self::MAGIC_SQLITE, strlen(self::MAGIC_SQLITE)) === 0) {
                return 'SQLite';
            }
            if (strncmp($head, self::MAGIC_GZIP, strlen(self::MAGIC_GZIP)) === 0) {
                return 'gzip';
            }
            if (strncmp($head, self::MAGIC_ZIP, strlen(self::MAGIC_ZIP)) === 0) {
                return 'Zip';
            }

            return 'unknown';
        }

        /**
         * Whether a file on disk is a SQLite database.
         *
         * @param string $path
         * @return bool
         */
        public static function isSqlite(string $path): bool {
            return self::detectType($path) === 'SQLite';
        }

        /**
         * Decompress an uploaded archive to $destination and verify the result.
         *
         * @param string $type        "gzip" or "Zip".
         * @param string $source      Uploaded file path.
         * @param string $destination Destination path for the extracted database.
         * @return array{ok: bool, type: string} `ok` is true only when the
         *         extracted file is a SQLite database; `type` is the detected type
         *         of whatever was produced (for logging).
         */
        public static function extract(string $type, string $source, string $destination): array {
            switch ($type) {
                case 'gzip':
                    // -d decompress, -c to stdout: same as the previous command.
                    $argv = [self::resolveBin('gzip'), '-dc', $source];
                    break;
                case 'Zip':
                    // -p extracts the first member to stdout: same as before.
                    $argv = [self::resolveBin('unzip'), '-p', $source];
                    break;
                default:
                    return ['ok' => false, 'type' => 'unknown'];
            }

            if (!self::runToFile($argv, $destination)) {
                return ['ok' => false, 'type' => 'unknown'];
            }

            $extractedType = self::detectType($destination);
            if ($extractedType !== 'SQLite') {
                @unlink($destination);
                return ['ok' => false, 'type' => $extractedType];
            }

            return ['ok' => true, 'type' => $extractedType];
        }

        /**
         * Reduce a comma-separated object list to the known identifiers.
         *
         * Order is preserved and duplicates removed. Anything unrecognized is
         * dropped, so the request cannot put arbitrary text into the trigger file.
         *
         * @param mixed $raw Raw `objects` request value.
         * @return string Comma-separated sanitized list ('' when nothing valid).
         */
        public static function sanitizeObjects($raw): string {
            if (is_array($raw)) {
                $raw = implode(',', $raw);
            }
            if (!is_string($raw)) {
                return '';
            }

            $out = [];
            foreach (explode(',', $raw) as $obj) {
                $obj = trim($obj);
                if ($obj !== '' && in_array($obj, self::ALLOWED_OBJECTS, true) && !in_array($obj, $out, true)) {
                    $out[] = $obj;
                }
            }

            return implode(',', $out);
        }

        /** Directories searched for the decompression helpers. */
        const BIN_SEARCH_DIRS = ['/bin', '/usr/bin', '/usr/local/bin'];

        /**
         * Resolve a helper binary to an absolute path.
         *
         * The previous implementation went through a shell, which supplies a
         * default PATH of its own. proc_open()'s argv form does not: it resolves
         * through the process environment, and php-fpm is commonly configured with
         * `clear_env = yes`, leaving PATH unset. Resolving here keeps the
         * decompressors findable regardless.
         *
         * @param string $name Binary name.
         * @return string Absolute path when found, otherwise the bare name.
         */
        private static function resolveBin(string $name): string {
            foreach (self::BIN_SEARCH_DIRS as $dir) {
                $candidate = $dir . '/' . $name;
                if (@is_executable($candidate)) {
                    return $candidate;
                }
            }

            return $name;
        }

        /**
         * Read the first $length bytes of a file.
         *
         * @param string $path
         * @param int    $length
         * @return string|null Null when the file cannot be read.
         */
        private static function head(string $path, int $length): ?string {
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                return null;
            }
            $fh = @fopen($path, 'rb');
            if ($fh === false) {
                return null;
            }
            $head = @fread($fh, $length);
            fclose($fh);

            return ($head === false) ? null : $head;
        }

        /**
         * Run a command (argv array, no shell) writing its stdout to a file.
         *
         * A non-zero exit is logged but not treated as fatal: some decompressors
         * warn (and exit non-zero) on trailing garbage while still producing a
         * usable database, and the previous implementation ignored the exit status
         * entirely. Whether the extraction succeeded is decided by the caller from
         * the extracted file's own header.
         *
         * @param array<int, string> $argv
         * @param string             $destination
         * @return bool True when the command could be started.
         */
        private static function runToFile(array $argv, string $destination): bool {
            $out = @fopen($destination, 'wb');
            if ($out === false) {
                error_log('[ImportFile] cannot open destination for writing: ' . $destination);
                return false;
            }

            $descriptors = [
                0 => ['file', '/dev/null', 'r'],
                1 => $out,                 // stdout straight to the file: no shell `>`
                2 => ['pipe', 'w'],
            ];
            $pipes = [];

            $process = @proc_open($argv, $descriptors, $pipes);
            if (!is_resource($process)) {
                fclose($out);
                @unlink($destination);
                error_log('[ImportFile] failed to start ' . $argv[0]);
                return false;
            }

            stream_set_blocking($pipes[2], false);
            $stderr = '';
            $deadline = microtime(true) + self::TIMEOUT_SEC;
            $exitCode = -1;

            while (true) {
                $stderr .= (string)stream_get_contents($pipes[2]);

                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exitCode = $status['exitcode'];
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process, 9);
                    error_log('[ImportFile] ' . $argv[0] . ' timed out after ' . self::TIMEOUT_SEC . 's');
                    break;
                }
                usleep(50000);
            }

            $stderr .= (string)stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);
            fclose($out);

            if (trim($stderr) !== '') {
                error_log('[ImportFile] ' . $argv[0] . ': ' . trim($stderr));
            }

            if ($exitCode !== 0) {
                error_log('[ImportFile] ' . $argv[0] . ' exited with code ' . $exitCode);
            }

            return true;
        }
    }
}
