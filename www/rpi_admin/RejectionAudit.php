<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * RejectionAudit - Append-only audit log for rejected Research operations.
 *
 * Whenever a Research_API validator or endpoint rejects a submission (a write
 * attempt, a multi-statement submission, an over-length statement, or a
 * malformed tool input), it records a RejectionAudit entry so the rejection can
 * be reviewed later (Req 9.6).
 *
 * Each record captures:
 *   - ts        integer  rejection time (Unix epoch seconds)
 *   - sessionId string   requesting session identifier ('' when no session)
 *   - category  string   category of the rejected operation or input
 *   - endpoint  string   the Research endpoint that performed the rejection
 *
 * Records are persisted append-only as JSON Lines (one JSON object per line) to
 * a log file (`research_audit.log`) under a configurable log directory. The
 * directory defaults to `/opt/rpidns/logs/` on the appliance but can be
 * overridden (constructor argument or the `$logDir` argument of the static
 * helpers) so the component can be exercised against a writable temporary path
 * in tests.
 *
 * Failure handling is deliberately non-fatal: if the log directory does not
 * exist the component attempts to create it, and any I/O failure is reported via
 * error_log() and surfaced as a false return value rather than raising a fatal
 * error. Auditing must never take down the request that triggered it.
 *
 * @see .kiro/specs/research-tools/design.md ("Rejection audit record (Req 9.6)")
 * Requirements: 9.6
 */
class RejectionAudit {

    /** Default log directory on the appliance. */
    const DEFAULT_LOG_DIR = '/opt/rpidns/logs';

    /** Log file name (JSON Lines) written within the log directory. */
    const LOG_FILENAME = 'research_audit.log';

    /**
     * Recognized rejection categories (mirrors the RejectionAudit data model in
     * the design). Exposed for callers; unknown categories are still recorded so
     * that no audit information is silently dropped.
     */
    const CATEGORIES = [
        'write_attempt',
        'multi_statement',
        'too_long',
        'invalid_domain',
        'invalid_ip',
        'invalid_dns_server',
        'bulk_too_large',
        'invalid_input',
    ];

    /** @var string Absolute path of the directory that holds the audit log. */
    private $logDir;

    /**
     * @param string|null $logDir Directory in which to store the audit log.
     *                            When null or empty the appliance default
     *                            (self::DEFAULT_LOG_DIR) is used. Overridable so
     *                            tests can point at a writable temporary path.
     */
    public function __construct($logDir = null) {
        $this->logDir = ($logDir !== null && $logDir !== '')
            ? rtrim((string)$logDir, '/')
            : self::DEFAULT_LOG_DIR;
    }

    /**
     * @return string The configured log directory.
     */
    public function getLogDir() {
        return $this->logDir;
    }

    /**
     * @return string The absolute path of the audit log file.
     */
    public function getLogPath() {
        return $this->logDir . '/' . self::LOG_FILENAME;
    }

    /**
     * Append a rejection record to the audit log.
     *
     * The record is written append-only as a single JSON line. If the log
     * directory is missing an attempt is made to create it; any failure is
     * logged and reported via a false return value without raising a fatal
     * error.
     *
     * @param string|null $sessionId Requesting session identifier (null → '').
     * @param string      $category  Rejection category (see self::CATEGORIES).
     * @param string      $endpoint  Endpoint that performed the rejection.
     * @param int|null    $ts        Rejection time (Unix seconds); defaults to now.
     * @return bool True when the record was persisted, false on failure.
     */
    public function append($sessionId, $category, $endpoint, $ts = null) {
        $record = [
            'ts'        => ($ts !== null) ? (int)$ts : time(),
            'sessionId' => ($sessionId !== null) ? (string)$sessionId : '',
            'category'  => (string)$category,
            'endpoint'  => ($endpoint !== null) ? (string)$endpoint : '',
        ];

        if (!$this->ensureLogDir()) {
            return false;
        }

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            error_log('[RejectionAudit] Failed to encode audit record: ' . json_last_error_msg());
            return false;
        }

        $written = @file_put_contents(
            $this->getLogPath(),
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            error_log('[RejectionAudit] Failed to write audit record to ' . $this->getLogPath());
            return false;
        }

        return true;
    }

    /**
     * Read all retained records from this instance's audit log.
     *
     * @return array<int, array> List of decoded records in the order written.
     *                            Empty when the log does not yet exist.
     */
    public function all() {
        return self::readAll($this->getLogPath());
    }

    /**
     * Static convenience callable invoked by validators/endpoints on every
     * rejection.
     *
     * @param string|null $sessionId Requesting session identifier.
     * @param string      $category  Rejection category (see self::CATEGORIES).
     * @param string      $endpoint  Endpoint that performed the rejection.
     * @param string|null $logDir    Optional log-directory override (for tests).
     * @return bool True when the record was persisted, false on failure.
     */
    public static function record($sessionId, $category, $endpoint, $logDir = null) {
        $audit = new self($logDir);
        return $audit->append($sessionId, $category, $endpoint);
    }

    /**
     * Read and decode all retained records from a log file.
     *
     * Accepts either the log file path directly or the containing directory (in
     * which case the standard log file name is appended). Malformed lines are
     * skipped so that a single bad line does not prevent reading the rest of the
     * retained records.
     *
     * @param string|null $path Log file path or directory. Null → default log file.
     * @return array<int, array> List of decoded records in the order written.
     */
    public static function readAll($path = null) {
        if ($path === null || $path === '') {
            $path = self::DEFAULT_LOG_DIR . '/' . self::LOG_FILENAME;
        }

        // If a directory was supplied, resolve to the standard log file name.
        if (is_dir($path)) {
            $path = rtrim((string)$path, '/') . '/' . self::LOG_FILENAME;
        }

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            error_log('[RejectionAudit] Failed to read audit log at ' . $path);
            return [];
        }

        $records = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * Ensure the log directory exists, creating it if necessary.
     *
     * @return bool True if the directory exists (or was created) and is writable.
     */
    private function ensureLogDir() {
        if (is_dir($this->logDir)) {
            return true;
        }

        // Attempt to create the directory tree; suppress warnings so a failure
        // is handled gracefully rather than emitting a PHP warning.
        if (@mkdir($this->logDir, 0775, true) || is_dir($this->logDir)) {
            return true;
        }

        error_log('[RejectionAudit] Log directory does not exist and could not be created: ' . $this->logDir);
        return false;
    }
}
