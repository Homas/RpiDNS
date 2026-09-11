<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * NsUpdate - Safe dynamic-DNS updates for the local block/allow RPZ zones.
 *
 * The block/allow lists are provisioned into BIND with `nsupdate`. Previously
 * every call built a SHELL STRING of the form
 *
 *     printf "server bind\nupdate add <ioc>.block.ioc2rpz.rpidns 60 CNAME .\nsend\n" | nsupdate -d -v
 *
 * and handed it to exec(). Two things could be injected there: shell syntax (via
 * the pipeline) and nsupdate directives (via a newline inside the indicator).
 * The API write paths validate the indicator, but the DB import path did not, and
 * both the import and the expiry job read indicators back OUT of the database and
 * re-embedded them - so a crafted SQLite upload could reach a root shell (those
 * jobs run from the root crontab).
 *
 * This helper closes that off in three ways:
 *  1. No shell. `nsupdate` is started with proc_open() using an ARGV ARRAY, so
 *     there is no `sh -c` to interpret metacharacters and no pipeline to hijack.
 *  2. The update script is written to nsupdate's STDIN rather than produced by a
 *     shell `printf`, so backslash/quote handling is no longer a shell concern.
 *  3. Every indicator is validated as a hostname before it is placed in the
 *     script, with the SAME check the API write path uses
 *     (filter_var/FILTER_VALIDATE_DOMAIN + FILTER_FLAG_HOSTNAME). A value the UI
 *     would not have accepted is skipped, which also rules out embedded newlines
 *     and therefore nsupdate-directive injection.
 *
 * Behaviour is otherwise identical to the previous exec() calls: the same zone
 * names, the same 60s TTL, the same `CNAME .` records, the same optional wildcard
 * for "include subdomains", and the same "array of stdout lines" return shape
 * that the API surfaces to the UI as `details`.
 *
 * script() is a pure function and is where the exact text sent to nsupdate is
 * decided, so it can be asserted directly against the strings the old shell
 * pipeline produced.
 */

if (!class_exists('NsUpdate')) {
    class NsUpdate {

        /** nsupdate binary (absolute path, as used by the previous exec() calls). */
        const BIN = '/usr/bin/nsupdate';

        /** TTL used for the provisioned records. */
        const TTL = 60;

        /** Wall-clock bound (seconds) so a stuck nsupdate cannot hang cron or php-fpm. */
        const TIMEOUT_SEC = 30;

        /** Suffix shared by the dynamic local zones. */
        const ZONE_SUFFIX = 'ioc2rpz.rpidns';

        /**
         * Validate an indicator before it is used to build a zone name.
         *
         * Deliberately the same check as the block/allow write handlers in
         * rpidata.php, so nothing the UI accepts is rejected here and nothing the
         * UI rejects can slip in through an import.
         *
         * @param mixed $ioc Candidate indicator.
         * @return bool
         */
        public static function isValidIoc($ioc): bool {
            if (!is_string($ioc) || $ioc === '') {
                return false;
            }
            // Belt and braces: a hostname can never contain these, and a newline
            // is what would let a value inject an extra nsupdate directive.
            if (strpbrk($ioc, " \t\r\n\"'\\;|&$`<>()") !== false) {
                return false;
            }

            return filter_var($ioc, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        }

        /**
         * Only the two dynamic local zones exist; anything else is a bug upstream
         * or a legacy row (older schemas used 'bl'/'wl', which never matched a real
         * zone name and so were a no-op then too).
         *
         * @param mixed $list
         * @return bool
         */
        public static function isValidList($list): bool {
            return $list === 'block' || $list === 'allow';
        }

        /**
         * Fully-qualified record name for an indicator in a local zone.
         *
         * @param string $ioc  Validated indicator.
         * @param string $list 'block' or 'allow'.
         * @return string
         */
        public static function recordName(string $ioc, string $list): string {
            return $ioc . '.' . $list . '.' . self::ZONE_SUFFIX;
        }

        /**
         * Build the exact nsupdate script for an operation - pure function.
         *
         * @param string $server     BIND host for the `server` directive.
         * @param mixed  $ioc        Indicator (validated here).
         * @param mixed  $list       'block' or 'allow'.
         * @param string $op         'add' or 'delete'.
         * @param bool   $wildcard   Include the `*.<ioc>` record.
         * @param bool   $exact      Include the exact `<ioc>` record.
         * @return string The script to feed nsupdate, or '' when the input is
         *                rejected (in which case nothing should be executed).
         */
        public static function script(string $server, $ioc, $list, string $op, bool $wildcard, bool $exact = true): string {
            if (!self::isValidIoc($ioc) || !self::isValidList($list)) {
                return '';
            }
            if ($op !== 'add' && $op !== 'delete') {
                return '';
            }
            if (!$exact && !$wildcard) {
                return '';
            }
            if (!self::isValidServer($server)) {
                return '';
            }

            $name = self::recordName((string)$ioc, (string)$list);

            $script = 'server ' . $server . "\n";
            if ($exact) {
                $script .= 'update ' . $op . ' ' . $name . ' ' . self::TTL . " CNAME .\n";
            }
            if ($wildcard) {
                $script .= 'update ' . $op . ' *.' . $name . ' ' . self::TTL . " CNAME .\n";
            }
            $script .= "send\n";

            return $script;
        }

        /**
         * Add an indicator to a local zone.
         *
         * @param string $server     BIND host to send the update to.
         * @param mixed  $ioc        Indicator (validated here).
         * @param mixed  $list       'block' or 'allow'.
         * @param bool   $subdomains Also add the `*.<ioc>` wildcard.
         * @return array<int, string> nsupdate stdout lines (empty on skip/failure).
         */
        public static function add(string $server, $ioc, $list, bool $subdomains): array {
            return self::send($server, $ioc, $list, 'add', $subdomains, true);
        }

        /**
         * Remove an indicator from a local zone.
         *
         * The wildcard is always deleted alongside the exact name (matching the
         * previous behaviour): deleting a record that is not present is a no-op
         * for nsupdate, so this is safe whether or not subdomains were added.
         *
         * @param string $server BIND host to send the update to.
         * @param mixed  $ioc    Indicator (validated here).
         * @param mixed  $list   'block' or 'allow'.
         * @return array<int, string> nsupdate stdout lines (empty on skip/failure).
         */
        public static function delete(string $server, $ioc, $list): array {
            return self::send($server, $ioc, $list, 'delete', true, true);
        }

        /**
         * Remove only the wildcard for an indicator, leaving the exact name.
         *
         * Used when "include subdomains" is turned off for an existing entry.
         *
         * @param string $server BIND host to send the update to.
         * @param mixed  $ioc    Indicator (validated here).
         * @param mixed  $list   'block' or 'allow'.
         * @return array<int, string> nsupdate stdout lines (empty on skip/failure).
         */
        public static function deleteWildcard(string $server, $ioc, $list): array {
            return self::send($server, $ioc, $list, 'delete', true, false);
        }

        /**
         * Build the script and, when it is non-empty, execute it.
         *
         * @return array<int, string> stdout lines.
         */
        private static function send(string $server, $ioc, $list, string $op, bool $wildcard, bool $exact): array {
            $script = self::script($server, $ioc, $list, $op, $wildcard, $exact);
            if ($script === '') {
                self::logSkip($ioc, $list);
                return [];
            }

            return self::run($script);
        }

        /**
         * Execute nsupdate with an already-built script on stdin.
         *
         * @param string $script Update script (validated, fixed-form).
         * @return array<int, string> stdout lines.
         */
        private static function run(string $script): array {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $pipes = [];

            // ARGV ARRAY form: executed directly, never through a shell.
            $process = @proc_open([self::BIN, '-d', '-v'], $descriptors, $pipes);
            if (!is_resource($process)) {
                error_log('[NsUpdate] failed to start ' . self::BIN);
                return [];
            }

            fwrite($pipes[0], $script);
            fclose($pipes[0]);

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = '';
            $stderr = '';
            $deadline = microtime(true) + self::TIMEOUT_SEC;

            while (true) {
                $stdout .= (string)stream_get_contents($pipes[1]);
                $stderr .= (string)stream_get_contents($pipes[2]);

                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process, 9);
                    error_log('[NsUpdate] timed out after ' . self::TIMEOUT_SEC . 's');
                    break;
                }
                usleep(20000);
            }

            // Drain whatever was buffered after the last read.
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if (trim($stderr) !== '') {
                // nsupdate -d writes its debug trace to stderr. Previously this
                // went to the php-fpm / cron log; keep it there.
                error_log('[NsUpdate] ' . trim($stderr));
            }

            // exec() returned stdout split into lines with no trailing newline;
            // preserve that shape for the API's `details` field.
            $stdout = str_replace("\r\n", "\n", $stdout);
            $lines = explode("\n", rtrim($stdout, "\n"));

            return ($lines === ['']) ? [] : $lines;
        }

        /**
         * Sanity-check the configured BIND host. It comes from server-side
         * configuration (rpidns_vars.php), never from a request.
         *
         * @param string $server
         * @return bool
         */
        private static function isValidServer(string $server): bool {
            if ($server === '' || strlen($server) > 253) {
                return false;
            }
            if (filter_var($server, FILTER_VALIDATE_IP) !== false) {
                return true;
            }

            return preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $server) === 1;
        }

        /**
         * Record a skipped indicator so an operator can see why a list entry was
         * not provisioned into BIND.
         *
         * @param mixed $ioc
         * @param mixed $list
         */
        private static function logSkip($ioc, $list): void {
            error_log(sprintf(
                '[NsUpdate] skipped invalid indicator for %s list: %s',
                is_string($list) ? $list : gettype($list),
                is_string($ioc) ? substr($ioc, 0, 120) : gettype($ioc)
            ));
        }
    }
}
