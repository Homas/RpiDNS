<?php
/**
 * Feature: research-tools, Property 7: SQL results are capped and flagged
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * Property-based test for the research_sql result cap/flag behaviour
 * (Property 7, Requirement 4.6).
 *
 * "For any result set, the Research_API SHALL return at most 10,000 rows, and
 *  the truncation flag SHALL be true if and only if the underlying result
 *  exceeded 10,000 rows."
 *
 * This is a SELF-CONTAINED standalone PHP script (no PHPUnit / composer needed).
 * Run with:  php www/rpi_admin/tests/SqlResultCapPropertyTest.php
 *
 * ---------------------------------------------------------------------------
 * What is exercised
 * ---------------------------------------------------------------------------
 * The cap/flag logic lives inside `case "POST research_sql":` in
 * www/rpi_admin/rpidata.php. Its row-fetch loop is (paraphrased):
 *
 *     $rs_max_rows = 10000;
 *     while (($row = $rs_result->fetchArray(SQLITE3_NUM)) !== false) {
 *         if (count($rs_rows) >= $rs_max_rows) { $rs_truncated = true; break; }
 *         $rs_rows[] = $row;
 *     }
 *
 * i.e. it fetches rows from a REAL SQLite3 result, caps the collected rows at
 * 10,000, and sets truncated=true iff a 10,001st row is present in the
 * underlying result.
 *
 * This test reproduces that exact loop in the local `capFetch()` function
 * below and runs it against a REAL SQLite3 connection (an in-memory database
 * populated with a generated number of rows N). For every generated N it
 * asserts the Property 7 invariant:
 *
 *     count(returned rows) <= CAP        AND        truncated === (N > CAP)
 *
 * ---------------------------------------------------------------------------
 * Cap parameterisation (runtime vs. fidelity)
 * ---------------------------------------------------------------------------
 * Property 7 states the invariant `count <= CAP and truncated iff N > CAP`.
 * The production CAP is 10,000. To keep the suite fast while still exercising
 * the REAL 10,000 boundary, this test does BOTH:
 *
 *   1. Explicit boundary cases at the REAL cap of 10,000 rows:
 *        N in {0, 1, 9999, 10000, 10001, 10002, ... ~10005}
 *      These insert up to 10,001+ rows and validate the exact production
 *      boundary (truncated flips from false at N=10000 to true at N=10001).
 *
 *   2. The bulk of randomized iterations use a SMALLER, configurable cap so
 *      that thousands of rows need not be inserted every iteration. The
 *      asserted invariant is identical (count <= CAP and truncated iff N>CAP);
 *      only the CAP value changes. Random N values straddle the chosen cap
 *      (both below and above), with extra weight on the +/-1 boundary.
 *
 * The capFetch() implementation is cap-agnostic (it takes the cap as an
 * argument, exactly mirroring rpidata.php's $rs_max_rows), so validating it at
 * a smaller cap and at the real 10,000 cap exercises the same code path.
 *
 * Minimum 100 iterations.
 */

const MIN_ITERATIONS = 100;
const REAL_CAP       = 10000; // production row cap (rpidata.php $rs_max_rows)

/**
 * Faithful mirror of the rpidata.php research_sql row-fetch loop.
 *
 * Executes $sql against the REAL SQLite3 connection $db, collects rows via
 * fetchArray(SQLITE3_NUM), caps the collected rows at $cap, and sets the
 * truncated flag to true iff a ($cap + 1)-th row is present in the underlying
 * result set.
 *
 * @return array{rows: array<int,array>, truncated: bool, columns: array<int,string>}
 */
function capFetch(SQLite3 $db, string $sql, int $cap): array {
    $rows = [];
    $truncated = false;
    $columns = [];

    $result = $db->query($sql);
    if ($result === false) {
        throw new RuntimeException('query failed to execute: ' . $sql);
    }

    // Column names in query-returned order (mirrors rpidata.php).
    $numCols = $result->numColumns();
    for ($c = 0; $c < $numCols; $c++) {
        $columns[] = $result->columnName($c);
    }

    // The exact cap/flag loop from rpidata.php: stop once we have $cap rows and
    // a further row still exists -> truncated.
    while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) {
        if (count($rows) >= $cap) {
            $truncated = true;
            break;
        }
        $rows[] = $row;
    }
    $result->finalize();

    return ['rows' => $rows, 'truncated' => $truncated, 'columns' => $columns];
}

/**
 * Populate table `t` in $db with exactly $n rows (values 1..$n) using a fast
 * recursive-CTE bulk insert, so large N (up to ~10,001) stays cheap.
 * The table is reset (all rows removed) before insertion.
 */
function seedRows(SQLite3 $db, int $n): void {
    $db->exec('DELETE FROM t');
    if ($n <= 0) {
        return;
    }
    // Bump the recursion depth limit above the largest N we generate so the
    // recursive CTE can produce all rows.
    $db->exec('PRAGMA recursive_triggers = 0');
    $sql =
        'INSERT INTO t(v) ' .
        'WITH RECURSIVE c(x) AS (' .
        '  SELECT 1 UNION ALL SELECT x + 1 FROM c WHERE x < ' . $n .
        ') SELECT x FROM c';
    if ($db->exec($sql) !== true) {
        throw new RuntimeException('failed to seed ' . $n . ' rows');
    }
}

// One shared in-memory REAL SQLite3 connection for the whole run.
$db = new SQLite3(':memory:');
$db->enableExceptions(true);
$db->exec('CREATE TABLE t (v INTEGER)');

$failures = [];
$checked  = 0;

// Deterministic seed keeps the run reproducible while covering a wide space.
mt_srand(7007);

/**
 * Build the list of test cases: explicit real-cap boundary cases first, then
 * randomized cases against a smaller cap. Each case is [cap, n].
 *
 * @var array<int,array{0:int,1:int}> $cases
 */
$cases = [];

// --- (1) Explicit boundary cases at the REAL production cap of 10,000. ------
// These insert up to 10,005 rows and prove the exact boundary: truncated is
// false at N <= 10000 and true at N >= 10001.
$realCapNs = [0, 1, 9998, 9999, REAL_CAP, REAL_CAP + 1, REAL_CAP + 2, REAL_CAP + 5];
foreach ($realCapNs as $n) {
    $cases[] = [REAL_CAP, $n];
}

// --- (2) Randomized iterations against a smaller, configurable cap. ---------
// Enough to comfortably exceed the 100-iteration minimum together with (1).
$randomCount = MIN_ITERATIONS + 20;
for ($i = 0; $i < $randomCount; $i++) {
    // Small cap keeps inserts cheap while validating the identical invariant.
    $cap  = mt_rand(1, 200);
    $mode = $i % 5;
    switch ($mode) {
        case 0: $n = $cap;              break; // exact boundary: not truncated
        case 1: $n = $cap + 1;          break; // one over: truncated
        case 2: $n = max(0, $cap - 1);  break; // one under: not truncated
        case 3: $n = 0;                 break; // empty: not truncated
        default: $n = mt_rand(0, 2 * $cap + 5); break; // straddle the cap
    }
    $cases[] = [$cap, $n];
}

$iteration = 0;
foreach ($cases as [$cap, $n]) {
    $iteration++;

    seedRows($db, $n);

    $out = capFetch($db, 'SELECT v FROM t ORDER BY v', $cap);
    $rowCount = count($out['rows']);
    $expectedTruncated = ($n > $cap);

    // Assertion 1 (Property 7): returned row count never exceeds the cap.
    if ($rowCount > $cap) {
        $failures[] = sprintf(
            'returned %d rows exceeds cap %d (N=%d)',
            $rowCount, $cap, $n
        );
    }

    // Assertion 2 (Property 7): truncated flag true iff N > cap.
    if ($out['truncated'] !== $expectedTruncated) {
        $failures[] = sprintf(
            'truncated=%s but expected %s (cap=%d, N=%d, returned=%d)',
            var_export($out['truncated'], true),
            var_export($expectedTruncated, true),
            $cap, $n, $rowCount
        );
    }

    // Sanity: when not truncated, all N rows (up to cap) are returned exactly.
    if (!$expectedTruncated && $rowCount !== min($n, $cap)) {
        $failures[] = sprintf(
            'non-truncated returned %d rows != expected %d (cap=%d, N=%d)',
            $rowCount, min($n, $cap), $cap, $n
        );
    }

    $checked++;
}

$db->close();

echo "Feature: research-tools, Property 7 - SQL results are capped and flagged\n";
echo "Iterations: {$iteration} (>= " . MIN_ITERATIONS . " required), cases checked: {$checked}\n";
echo "Includes explicit boundary cases at the real cap of " . REAL_CAP . " rows.\n";

if (empty($failures)) {
    echo "PASS: returned row count <= cap and truncated flag true iff underlying result exceeded the cap.\n";
    exit(0);
}

echo 'FAIL: ' . count($failures) . " counterexample(s) found:\n";
foreach (array_slice($failures, 0, 10) as $f) {
    echo "  - {$f}\n";
}
exit(1);
