<?php
/**
 * SQL Query Validator for the RpiDNS Research page.
 *
 * Pure, side-effect-free validation of administrator-supplied SQL statements.
 * A statement is accepted only when it is a single, read-only query whose first
 * significant keyword is SELECT (or WITH ... SELECT), that contains no write /
 * side-effecting keyword, and that is at most MAX_LENGTH characters long.
 *
 * This component NEVER executes SQL. It only inspects the statement and returns
 * an accept/reject decision together with a machine-readable rejection category
 * (aligned with the RejectionAudit categories in the design) and a descriptive
 * human-readable reason. Auditing and execution are the responsibility of the
 * calling endpoint.
 *
 * Design reference: research-tools "SqlQueryValidator".
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.11, 9.4
 *
 * (c) Vadim Pavlov 2020 - 2026
 */

class SqlQueryValidator {

    /** Maximum accepted statement length, in characters (Req 4.11). */
    const MAX_LENGTH = 10000;

    /** Rejection categories (subset of RejectionAudit categories). */
    const CAT_TOO_LONG        = 'too_long';
    const CAT_MULTI_STATEMENT = 'multi_statement';
    const CAT_WRITE_ATTEMPT   = 'write_attempt';
    const CAT_INVALID_INPUT   = 'invalid_input';

    /**
     * Keywords that indicate a write / data-definition / side-effecting
     * operation. Presence of any of these as a bare token rejects the query
     * (Req 4.3 - Write_Operation).
     */
    const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'DROP', 'CREATE', 'ALTER',
        'TRUNCATE', 'ATTACH', 'DETACH', 'VACUUM', 'REINDEX', 'PRAGMA',
        'BEGIN', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'ANALYZE'
    ];

    /**
     * Validate a submitted SQL statement.
     *
     * @param string $sql The raw statement submitted by the user.
     * @return array {
     *     valid:    bool          True when the statement is a permitted read-only query.
     *     category: string|null   Rejection category (null when valid).
     *     reason:   string        Descriptive message (empty when valid).
     * }
     */
    public static function validate($sql) {
        if (!is_string($sql)) {
            return self::reject(self::CAT_INVALID_INPUT, 'The submitted query must be a string.');
        }

        // 1. Length bound (Req 4.11). Use multibyte length so multi-byte
        //    characters are counted as single characters.
        if (self::length($sql) > self::MAX_LENGTH) {
            return self::reject(
                self::CAT_TOO_LONG,
                'The submitted query exceeds the maximum allowed length of ' . self::MAX_LENGTH . ' characters.'
            );
        }

        // 2. Strip comments and string/identifier literals so keyword and
        //    statement-separator analysis only sees actual SQL code. Literal
        //    contents are replaced by a single space to preserve token
        //    boundaries without leaking their contents into keyword analysis.
        $code = self::stripCommentsAndLiterals($sql);

        // 3. Multi-statement check (Req 4.4). Trim trailing whitespace and a
        //    single optional trailing ';'. Any remaining ';' means more than
        //    one statement was submitted.
        $trimmed = rtrim($code);
        if (substr($trimmed, -1) === ';') {
            $trimmed = rtrim(substr($trimmed, 0, -1));
        }
        if (strpos($trimmed, ';') !== false) {
            return self::reject(
                self::CAT_MULTI_STATEMENT,
                'Only a single SQL statement is permitted; multiple statements were detected.'
            );
        }

        // 4. First significant token must be SELECT or WITH (Req 4.1).
        if (!preg_match('/[A-Za-z_][A-Za-z0-9_]*/', $trimmed, $firstMatch)) {
            return self::reject(
                self::CAT_INVALID_INPUT,
                'The submitted query is empty or does not contain a valid SQL statement.'
            );
        }
        $firstToken = strtoupper($firstMatch[0]);

        // 5. Collect all bare word tokens for keyword analysis.
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $trimmed, $wordMatches);
        $tokens = array_map('strtoupper', $wordMatches[0]);

        // 6. Reject forbidden write/side-effecting keywords (Req 4.3).
        foreach ($tokens as $token) {
            if (in_array($token, self::FORBIDDEN_KEYWORDS, true)) {
                return self::reject(
                    self::CAT_WRITE_ATTEMPT,
                    'Only read-only SELECT queries are permitted; the disallowed keyword "' . $token . '" was found.'
                );
            }
        }

        // 7. Enforce read-only entry point (Req 4.1, Read_Only_Query).
        if ($firstToken === 'SELECT') {
            return self::accept();
        }

        if ($firstToken === 'WITH') {
            // A WITH clause must ultimately drive a SELECT (WITH ... SELECT).
            if (in_array('SELECT', $tokens, true)) {
                return self::accept();
            }
            return self::reject(
                self::CAT_INVALID_INPUT,
                'A WITH clause must be followed by a SELECT statement.'
            );
        }

        // Any recognised write keyword was already caught above, so a first
        // token that is neither SELECT nor WITH is an unsupported statement
        // rather than a specific write attempt.
        return self::reject(
            self::CAT_INVALID_INPUT,
            'Only read-only SELECT queries are permitted; the statement must begin with SELECT or WITH.'
        );
    }

    /**
     * Convenience predicate: is the statement a permitted read-only query?
     *
     * @param string $sql Raw statement.
     * @return bool
     */
    public static function isValid($sql) {
        $result = self::validate($sql);
        return $result['valid'];
    }

    /**
     * Remove SQL comments and mask string/identifier literals.
     *
     * Handles:
     *   - '...'  single-quoted string literals ('' escapes a quote)
     *   - "..."  double-quoted identifiers ("" escapes a quote)
     *   - `...`  backtick-quoted identifiers (`` escapes a backtick)
     *   - [...]  bracket-quoted identifiers (SQLite; no escape)
     *   - --...  line comments (to end of line)
     *   - /* ... *\/  block comments
     *
     * Literal contents are replaced with a single space so that keywords hidden
     * inside strings/identifiers are not treated as SQL keywords, while token
     * boundaries are preserved.
     *
     * @param string $sql Raw statement.
     * @return string Code with comments removed and literals masked.
     */
    private static function stripCommentsAndLiterals($sql) {
        $out = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            // Line comment: -- ... to end of line
            if ($ch === '-' && $next === '-') {
                $i += 2;
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            // Block comment: /* ... */
            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($i + 1 < $len) && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2; // skip the closing */ (harmless if past end)
                $out .= ' ';
                continue;
            }

            // Single-quoted string literal
            if ($ch === "'") {
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === "'") {
                        // Doubled quote is an escaped quote inside the literal.
                        if (($i + 1 < $len) && $sql[$i + 1] === "'") {
                            $i += 2;
                            continue;
                        }
                        $i++; // closing quote
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            // Double-quoted identifier
            if ($ch === '"') {
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '"') {
                        if (($i + 1 < $len) && $sql[$i + 1] === '"') {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            // Backtick-quoted identifier
            if ($ch === '`') {
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '`') {
                        if (($i + 1 < $len) && $sql[$i + 1] === '`') {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }

            // Bracket-quoted identifier (SQLite): [ ... ]  (no escape sequence)
            if ($ch === '[') {
                $i++;
                while ($i < $len && $sql[$i] !== ']') {
                    $i++;
                }
                $i++; // skip closing ]
                $out .= ' ';
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }

    /**
     * Character length that tolerates multi-byte input when mbstring is present.
     *
     * @param string $s
     * @return int
     */
    private static function length($s) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($s, 'UTF-8');
        }
        return strlen($s);
    }

    /**
     * Build an accept result.
     * @return array
     */
    private static function accept() {
        return ['valid' => true, 'category' => null, 'reason' => ''];
    }

    /**
     * Build a reject result.
     * @param string $category
     * @param string $reason
     * @return array
     */
    private static function reject($category, $reason) {
        return ['valid' => false, 'category' => $category, 'reason' => $reason];
    }
}
