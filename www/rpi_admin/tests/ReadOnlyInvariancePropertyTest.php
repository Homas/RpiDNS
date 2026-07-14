<?php
/**
 * Feature: research-tools, Property 14: Research requests never modify database state
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * Property-based test for the research read-only invariance property
 * (Property 14, Requirements 9.2, 9.5).
 *
 * "For any Research_API request - whether accepted, rejected by validation, or
 *  failed during execution - the content of the RpiDNS_Database SHALL be
 *  identical before and after the request."
 *
 * This is a SELF-CONTAINED standalone PHP script (no PHPUnit / composer needed).
 * Run with:  php www/rpi_admin/tests/ReadOnlyInvariancePropertyTest.php
 *
 * ---------------------------------------------------------------------------
 * What is exercised
 * ---------------------------------------------------------------------------
 * The read-only guarantee for the research_sql endpoint rests on two pillars
 * that this test reproduces faithfully against a REAL SQLite database:
 *
 *   (a) SqlQueryValidator::validate() rejects every non read-only SELECT
 *       BEFORE any execution occurs (Req 4.1/4.3/4.4/4.11). A rejected
 *       submission is never run, so the DB cannot change.
 *
 *   (b) The endpoint opens a SEPARATE connection with SQLITE3_OPEN_READONLY
 *       (Req 4.5). Even a validator gap cannot mutate the DB because the
 *       connection itself refuses writes.
 *
 * `handleResearchSql()` below mirrors `case "POST research_sql":` in
 * www/rpi_admin/rpidata.php: it validates first, and only on a valid result
 * opens a SQLITE3_OPEN_READONLY connection and fetches rows, catching runtime
 * errors exactly as the endpoint does.
 *
 * Because a SQLITE3_OPEN_READONLY connection must open a real file (an
 * :memory: database cannot be shared by a second connection), this test uses a
 * REAL temp-file SQLite database. A content fingerprint (a hash of every row of
 * every user table plus the schema) is computed BEFORE and AFTER every request
 * and asserted identical, across all three request categories:
 *
 *   - ACCEPTED       : a read-only SELECT the validator accepts and that the
 *                      read-only connection executes successfully.
 *   - VAL_REJECTED   : a write attempt / multi-statement / over-length
 *                      submission the validator rejects (nothing executes).
 *   - EXEC_FAILED    : a syntactically-valid-looking SELECT that errors at
 *                      runtime (e.g. a non-existent table), caught by the
 *                      endpoint's try/catch.
 *
 * Additionally, for every iteration a write statement (INSERT/UPDATE/DELETE/
 * DROP) is attempted DIRECTLY on the SQLITE3_OPEN_READONLY connection to prove
 * the connection itself refuses writes (Req 4.5) - the fingerprint must remain
 * unchanged whether or not that raw write raised an error.
 *
 * Minimum 100 iterations.
 */

require_once __DIR__ . '/../SqlQueryValidator.php';

const MIN_ITERATIONS = 100;

// Request categories exercised (Property 14 spans all three).
const CAT_ACCEPTED     = 'accepted';
const CAT_VAL_REJECTED = 'validation_rejected';
const CAT_EXEC_FAILED  = 'execution_failed';

/**
 * Faithful mirror of `case "POST research_sql":` in rpidata.php (minus the
 * auth guard, which is out of scope for this property).
 *
 * Validates first; only a valid single read-only SELECT is executed, and it is
 * executed against a SEPARATE connection opened SQLITE3_OPEN_READONLY. Runtime
 * errors are caught. The function never writes to the database.
 *
 * @return array{outcome: string, rowCount: int, error: ?string}
 *   outcome in {'accepted','validation_rejected','execution_failed'}
 */
function handleResearchSql(string $dbPath, string $sql): array {
    // 1. Validate BEFORE any execution (Req 4.1). Rejected -> nothing runs.
    $check = SqlQueryValidator::validate($sql);
    if ($check['valid'] !== true) {
        return ['outcome' => CAT_VAL_REJECTED, 'rowCount' => 0, 'error' => $check['reason']];
    }

    // 2. Valid: execute on a READ-ONLY connection (Req 4.5, 9.2, 9.5).
    $roDb = null;
    $rowCount = 0;
    $error = null;
    try {
        $roDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $roDb->busyTimeout(5000);
        $roDb->enableExceptions(true);

        $result = $roDb->query($sql);
        if ($result === false) {
            $error = 'the submitted query failed to execute';
        } else {
            while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) {
                $rowCount++;
            }
            $result->finalize();
        }
    } catch (Exception $e) {
        // Syntactically invalid / runtime failure (Req 4.7): descriptive error,
        // no partial data. DB is untouched by a read-only connection.
        $error = $e->getMessage();
    } finally {
        if ($roDb !== null) {
            $roDb->close();
        }
    }

    if ($error !== null) {
        return ['outcome' => CAT_EXEC_FAILED, 'rowCount' => 0, 'error' => $error];
    }
    return ['outcome' => CAT_ACCEPTED, 'rowCount' => $rowCount, 'error' => null];
}

/**
 * Attempt a write DIRECTLY on a SQLITE3_OPEN_READONLY connection to prove the
 * connection refuses writes (Req 4.5). Any error is swallowed; the point is
 * that the DB content must be unchanged afterwards (asserted by the caller).
 *
 * @return bool true if the write was refused (raised an error / returned false).
 */
function attemptRawWriteOnReadonly(string $dbPath, string $writeSql): bool {
    $refused = false;
    $roDb = null;
    try {
        $roDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $roDb->enableExceptions(true);
        $ok = $roDb->exec($writeSql);
        // On a read-only connection a write should not succeed; exec() returning
        // false (or a thrown exception) both count as "refused".
        $refused = ($ok !== true);
    } catch (Exception $e) {
        $refused = true;
    } finally {
        if ($roDb !== null) {
            $roDb->close();
        }
    }
    return $refused;
}

/**
 * Compute a content fingerprint of the WHOLE database: the schema plus every
 * row of every user table, hashed. Any change to database content (rows,
 * values, or schema) changes the fingerprint.
 *
 * A fresh read-write connection is used only to READ for fingerprinting; it
 * performs no writes.
 */
function dbFingerprint(string $dbPath): string {
    $db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $db->enableExceptions(true);

    $ctx = hash_init('sha256');

    // Schema (table/index definitions), ordered for stability.
    $schemaRs = $db->query(
        "SELECT type, name, tbl_name, sql FROM sqlite_master " .
        "WHERE name NOT LIKE 'sqlite_%' ORDER BY type, name"
    );
    while (($r = $schemaRs->fetchArray(SQLITE3_ASSOC)) !== false) {
        hash_update($ctx, 'SCHEMA|' . json_encode($r) . "\n");
    }
    $schemaRs->finalize();

    // Collect user table names, ordered.
    $tables = [];
    $tblRs = $db->query(
        "SELECT name FROM sqlite_master WHERE type='table' " .
        "AND name NOT LIKE 'sqlite_%' ORDER BY name"
    );
    while (($r = $tblRs->fetchArray(SQLITE3_NUM)) !== false) {
        $tables[] = $r[0];
    }
    $tblRs->finalize();

    // Every row of every table, deterministically ordered by rowid.
    foreach ($tables as $table) {
        hash_update($ctx, 'TABLE|' . $table . "\n");
        // Quote the identifier defensively (table names here are controlled).
        $q = '"' . str_replace('"', '""', $table) . '"';
        $rowRs = $db->query('SELECT * FROM ' . $q . ' ORDER BY rowid');
        while (($row = $rowRs->fetchArray(SQLITE3_ASSOC)) !== false) {
            hash_update($ctx, 'ROW|' . json_encode($row) . "\n");
        }
        $rowRs->finalize();
    }

    $db->close();
    return hash_final($ctx);
}

/**
 * Create a fresh temp-file SQLite database, seed a schema and rows, and return
 * its path. Uses a REAL file (not :memory:) so a second SQLITE3_OPEN_READONLY
 * connection can open it.
 */
function createTempDb(): string {
    $path = tempnam(sys_get_temp_dir(), 'rpidns_p14_') . '.sqlite';
    $db = new SQLite3($path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    $db->enableExceptions(true);
    $db->exec('CREATE TABLE queries (id INTEGER PRIMARY KEY, fqdn TEXT, action TEXT, dt INTEGER)');
    $db->exec('CREATE TABLE assets (id INTEGER PRIMARY KEY, name TEXT, kind TEXT)');

    $actions = ['allowed', 'blocked'];
    for ($i = 1; $i <= 40; $i++) {
        $fqdn = 'host' . $i . '.example' . ($i % 5) . '.com';
        $action = $actions[$i % 2];
        $dt = 1700000000 + $i * 37;
        $stmt = $db->prepare('INSERT INTO queries(fqdn, action, dt) VALUES(:f,:a,:d)');
        $stmt->bindValue(':f', $fqdn, SQLITE3_TEXT);
        $stmt->bindValue(':a', $action, SQLITE3_TEXT);
        $stmt->bindValue(':d', $dt, SQLITE3_INTEGER);
        $stmt->execute();
    }
    for ($i = 1; $i <= 10; $i++) {
        $stmt = $db->prepare('INSERT INTO assets(name, kind) VALUES(:n,:k)');
        $stmt->bindValue(':n', 'asset-' . $i, SQLITE3_TEXT);
        $stmt->bindValue(':k', ($i % 3 === 0) ? 'block' : 'allow', SQLITE3_TEXT);
        $stmt->execute();
    }
    $db->close();
    return $path;
}

/** Remove a temp DB file and any SQLite side-car files. */
function cleanupTempDb(string $path): void {
    foreach ([$path, $path . '-wal', $path . '-shm', $path . '-journal'] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
}

// ---------------------------------------------------------------------------
// Generators for the three request categories.
// ---------------------------------------------------------------------------

/** Generate an ACCEPTED read-only SELECT (validator accepts, runs cleanly). */
function genAccepted(): string {
    $variants = [
        'SELECT * FROM queries',
        'SELECT fqdn, action, dt FROM queries WHERE action = \'allowed\'',
        'SELECT DISTINCT fqdn FROM queries ORDER BY fqdn',
        'SELECT action, COUNT(*) AS c FROM queries GROUP BY action',
        'SELECT fqdn, MAX(dt) AS last_dt FROM queries GROUP BY fqdn',
        'WITH a AS (SELECT fqdn FROM queries WHERE action=\'allowed\') SELECT COUNT(*) FROM a',
        'SELECT name, kind FROM assets ORDER BY id LIMIT 5',
        'SELECT COUNT(*) FROM queries WHERE dt > 1700000000',
        'SELECT q.fqdn, a.kind FROM queries q LEFT JOIN assets a ON a.name = q.fqdn',
    ];
    return $variants[mt_rand(0, count($variants) - 1)];
}

/** Generate a VALIDATION-REJECTED submission (write / multi-stmt / too long). */
function genValidationRejected(): array {
    // Returns [sql, rawWriteForReadonlyProbe]
    $writes = [
        'INSERT INTO queries(fqdn, action, dt) VALUES(\'evil.com\',\'allowed\',1)',
        'UPDATE queries SET action = \'blocked\' WHERE 1=1',
        'DELETE FROM queries',
        'DROP TABLE assets',
        'REPLACE INTO assets(id,name,kind) VALUES(1,\'x\',\'y\')',
        'ALTER TABLE queries ADD COLUMN evil TEXT',
        'CREATE TABLE hacked (x INTEGER)',
    ];
    $mode = mt_rand(0, 3);
    switch ($mode) {
        case 0: // plain write attempt
            $w = $writes[mt_rand(0, count($writes) - 1)];
            return [$w, $w];
        case 1: // multi-statement (SELECT then a write)
            $w = $writes[mt_rand(0, count($writes) - 1)];
            return ['SELECT * FROM queries; ' . $w, $w];
        case 2: // over-length (> 10,000 chars)
            $long = 'SELECT * FROM queries WHERE fqdn = \'' . str_repeat('a', 10050) . '\'';
            return [$long, 'DELETE FROM queries'];
        default: // multi-statement of two writes
            $w = $writes[mt_rand(0, count($writes) - 1)];
            return [$w . '; ' . $writes[mt_rand(0, count($writes) - 1)], $w];
    }
}

/** Generate an EXECUTION-FAILED SELECT (valid entry point, runtime error). */
function genExecutionFailed(): string {
    $variants = [
        'SELECT * FROM no_such_table',
        'SELECT no_such_column FROM queries',
        'SELECT * FROM queries WHERE',                    // syntax error after WHERE
        'SELECT abs() FROM queries',                       // wrong arg count
        'WITH x AS (SELECT * FROM missing_cte_table) SELECT * FROM x',
        'SELECT * FROM queries JOIN ghost_table ON 1=1',
    ];
    return $variants[mt_rand(0, count($variants) - 1)];
}

// ---------------------------------------------------------------------------
// Run the property.
// ---------------------------------------------------------------------------

mt_srand(140140); // deterministic, reproducible run

$dbPath = createTempDb();

$failures = [];
$counts = [CAT_ACCEPTED => 0, CAT_VAL_REJECTED => 0, CAT_EXEC_FAILED => 0];
$rawWriteRefusedCount = 0;
$iterations = MIN_ITERATIONS + 20;

for ($i = 0; $i < $iterations; $i++) {
    // Round-robin across the three categories so every category is exercised
    // many times over the run.
    $pick = $i % 3;

    if ($pick === 0) {
        $sql = genAccepted();
        $expectedOutcome = CAT_ACCEPTED;
        $rawWrite = 'DELETE FROM queries';
    } elseif ($pick === 1) {
        [$sql, $rawWrite] = genValidationRejected();
        $expectedOutcome = CAT_VAL_REJECTED;
    } else {
        $sql = genExecutionFailed();
        $expectedOutcome = CAT_EXEC_FAILED;
        $rawWrite = 'UPDATE assets SET kind = \'pwn\'';
    }

    $before = dbFingerprint($dbPath);

    $res = handleResearchSql($dbPath, $sql);

    // Additionally prove the read-only connection itself refuses a raw write
    // (Req 4.5), regardless of the request category.
    $refused = attemptRawWriteOnReadonly($dbPath, $rawWrite);
    if ($refused) {
        $rawWriteRefusedCount++;
    } else {
        $failures[] = sprintf(
            'iteration %d: raw write on READONLY connection was NOT refused: %s',
            $i, $rawWrite
        );
    }

    $after = dbFingerprint($dbPath);

    // Core Property 14 assertion: DB content identical before and after.
    if ($before !== $after) {
        $failures[] = sprintf(
            'iteration %d [%s]: DB fingerprint changed (before=%s after=%s) sql=%s',
            $i, $expectedOutcome, substr($before, 0, 12), substr($after, 0, 12), $sql
        );
    }

    // Sanity: the outcome category matches what we generated. (Not the core
    // property, but guards against generators drifting so that a category is
    // silently never exercised.)
    if ($res['outcome'] !== $expectedOutcome) {
        $failures[] = sprintf(
            'iteration %d: expected outcome %s but got %s for sql=%s (error=%s)',
            $i, $expectedOutcome, $res['outcome'], $sql, var_export($res['error'], true)
        );
    }

    $counts[$res['outcome']]++;
}

cleanupTempDb($dbPath);

echo "Feature: research-tools, Property 14 - Research requests never modify database state\n";
echo "Iterations: {$iterations} (>= " . MIN_ITERATIONS . " required)\n";
echo sprintf(
    "Category coverage: accepted=%d, validation_rejected=%d, execution_failed=%d\n",
    $counts[CAT_ACCEPTED], $counts[CAT_VAL_REJECTED], $counts[CAT_EXEC_FAILED]
);
echo "Raw writes refused by the READONLY connection (Req 4.5): {$rawWriteRefusedCount}/{$iterations}\n";

// Guard: every category must have been exercised at least once.
if ($counts[CAT_ACCEPTED] === 0 || $counts[CAT_VAL_REJECTED] === 0 || $counts[CAT_EXEC_FAILED] === 0) {
    $failures[] = 'not all three request categories were exercised';
}

if (empty($failures)) {
    echo "PASS: DB content identical before/after every request across all three categories; " .
         "the read-only connection refused every direct write.\n";
    exit(0);
}

echo 'FAIL: ' . count($failures) . " counterexample(s) found:\n";
foreach (array_slice($failures, 0, 10) as $f) {
    echo "  - {$f}\n";
}
exit(1);
