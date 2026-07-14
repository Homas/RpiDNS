<?php
/**
 * Feature: research-tools, Property 15: Rejected inputs are audited - for any
 * submission rejected by validation (write attempt, multi-statement, over-length,
 * or malformed tool input), the Research_API produces a RETAINED audit record
 * containing the rejection time, the requesting session identifier, and the
 * category of the rejected operation or input.
 *
 * Validates: Requirements 9.6
 *
 * Property-based test harness. This project has no PHPUnit/composer, so - per the
 * design's fallback for backend properties - this is a SELF-CONTAINED standalone
 * script runnable with `php`.
 *
 * Strategy: for each of >= 100 iterations we produce a GENUINE rejection of a
 * randomly chosen category. Where possible the category is derived from the real
 * validators (SqlQueryValidator for write_attempt / multi_statement / too_long /
 * invalid_input; InputValidator for invalid_domain / invalid_ip /
 * invalid_dns_server / bulk_too_large) so the audited category corresponds to an
 * actual rejected submission. The rejection is then recorded via
 * RejectionAudit::record($sessionId, $category, $endpoint, $tempDir) and read
 * back with RejectionAudit::readAll($tempDir). We assert:
 *   - the newest retained record carries a valid integer ts (> 0, close to now),
 *     the exact sessionId, and the exact category, and
 *   - the number of retained records equals the number recorded (append-only,
 *     nothing dropped).
 *
 * Minimum 100 iterations (this harness runs 200).
 *
 * (c) Vadim Pavlov 2020 - 2026
 */

require_once __DIR__ . '/../RejectionAudit.php';
require_once __DIR__ . '/../SqlQueryValidator.php';
require_once __DIR__ . '/../InputValidator.php';

/* -------------------------------------------------------------------------- */
/*  Randomness (seeded for reproducibility; seed printed for replay).          */
/* -------------------------------------------------------------------------- */

$seed = getenv('PBT_SEED') !== false ? (int) getenv('PBT_SEED') : random_int(1, PHP_INT_MAX);
mt_srand($seed);

function ri($min, $max) { return mt_rand($min, $max); }
function pick(array $a) { return $a[mt_rand(0, count($a) - 1)]; }
function chance($pct) { return mt_rand(1, 100) <= $pct; }

function randToken($minLen = 1, $maxLen = 12) {
    $alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = ri($minLen, $maxLen);
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alpha[ri(0, strlen($alpha) - 1)];
    return $s;
}

// A random session id, including the "no session" edge case ('').
function randSessionId() {
    if (chance(10)) return '';
    return 'sess_' . randToken(6, 24);
}

function randEndpoint() {
    return pick([
        'research/sql', 'research/tools/whois', 'research/tools/dig',
        'research/tools/ping', 'research/bulk', 'research/unique',
    ]);
}

/* -------------------------------------------------------------------------- */
/*  Genuine-rejection generators. Each returns the category string that must be */
/*  audited, having first confirmed (via the real validator) that the crafted   */
/*  submission is actually rejected.                                            */
/* -------------------------------------------------------------------------- */

function genWriteAttempt() {
    $kw = pick(['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'VACUUM', 'PRAGMA']);
    $sql = $kw . ' ' . randToken() . ' ' . randToken();
    $res = SqlQueryValidator::validate($sql);
    // Confirm this is genuinely rejected (defensive; not asserted here).
    return [$res['valid'] === false, SqlQueryValidator::CAT_WRITE_ATTEMPT];
}

function genMultiStatement() {
    $sql = 'SELECT ' . ri(1, 99) . ' ; SELECT ' . randToken() . ' FROM ' . randToken();
    $res = SqlQueryValidator::validate($sql);
    return [$res['valid'] === false, SqlQueryValidator::CAT_MULTI_STATEMENT];
}

function genTooLong() {
    $sql = str_repeat(' ', SqlQueryValidator::MAX_LENGTH + ri(1, 500)) . 'SELECT 1';
    $res = SqlQueryValidator::validate($sql);
    return [$res['valid'] === false, SqlQueryValidator::CAT_TOO_LONG];
}

function genInvalidInput() {
    $sql = chance(50) ? '' : str_repeat(pick([' ', "\t", "\n"]), ri(1, 6));
    $res = SqlQueryValidator::validate($sql);
    return [$res['valid'] === false, SqlQueryValidator::CAT_INVALID_INPUT];
}

function genInvalidDomain() {
    // Malformed domain (spaces / illegal chars) that InputValidator rejects.
    $bad = pick(['not a domain', 'exa mple.com', '-bad-.com', '..', 'a..b', '@@@']);
    $rejected = (InputValidator::isValidDomain($bad) === false);
    return [$rejected, 'invalid_domain'];
}

function genInvalidIp() {
    $bad = pick(['999.999.999.999', '1.2.3', 'abcd::zzzz', '256.1.1.1', 'not.an.ip']);
    $rejected = (InputValidator::isValidIp($bad) === false);
    return [$rejected, 'invalid_ip'];
}

function genInvalidDnsServer() {
    $bad = pick(['bad server', '999.999.999.999', 'ht tp://x', '::zz::']);
    $rejected = (InputValidator::isValidDnsServer($bad) === false);
    return [$rejected, 'invalid_dns_server'];
}

function genBulkTooLarge() {
    // A bulk list large enough / malformed enough to be rejected.
    $items = [];
    $n = ri(1, 5);
    for ($i = 0; $i < $n; $i++) $items[] = 'bad domain ' . $i;
    $rejected = (InputValidator::isValidBulkList($items) === false);
    return [$rejected, 'bulk_too_large'];
}

$generators = [
    'write_attempt'      => 'genWriteAttempt',
    'multi_statement'    => 'genMultiStatement',
    'too_long'           => 'genTooLong',
    'invalid_input'      => 'genInvalidInput',
    'invalid_domain'     => 'genInvalidDomain',
    'invalid_ip'         => 'genInvalidIp',
    'invalid_dns_server' => 'genInvalidDnsServer',
    'bulk_too_large'     => 'genBulkTooLarge',
];

/* -------------------------------------------------------------------------- */
/*  Unique writable temp log directory.                                        */
/* -------------------------------------------------------------------------- */

$tempDir = sys_get_temp_dir() . '/rejaudit_pbt_' . getmypid() . '_' . random_int(1000, 999999);

function rmrf($path) {
    if (is_dir($path)) {
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            rmrf($path . '/' . $entry);
        }
        @rmdir($path);
    } elseif (is_file($path)) {
        @unlink($path);
    }
}

/* -------------------------------------------------------------------------- */
/*  Run the property.                                                          */
/* -------------------------------------------------------------------------- */

$ITERATIONS = 200;
$genNames = array_keys($generators);
$failures = [];
$counts = array_fill_keys($genNames, 0);
$recordedCount = 0;

for ($iter = 0; $iter < $ITERATIONS; $iter++) {
    $name = $genNames[$iter % count($genNames)]; // even coverage across categories
    $fn = $generators[$name];
    [$genuinelyRejected, $category] = $fn();
    $counts[$name]++;

    // Sanity: the crafted submission must actually be a rejection.
    if (!$genuinelyRejected) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'why' => 'crafted submission was NOT rejected by its validator',
        ];
        if (count($failures) >= 5) break;
        continue;
    }

    $sessionId = randSessionId();
    $endpoint = randEndpoint();
    $before = time();

    $ok = RejectionAudit::record($sessionId, $category, $endpoint, $tempDir);
    $after = time();

    if ($ok !== true) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'why' => 'RejectionAudit::record() returned false (record not persisted)',
        ];
        if (count($failures) >= 5) break;
        continue;
    }
    $recordedCount++;

    $records = RejectionAudit::readAll($tempDir);

    // Append-only: number retained must equal number recorded so far.
    if (count($records) !== $recordedCount) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'why' => 'retained record count != recorded count (append-only violated)',
            'recorded' => $recordedCount, 'retained' => count($records),
        ];
        if (count($failures) >= 5) break;
        continue;
    }

    // The newest retained record is the one we just wrote.
    $rec = $records[count($records) - 1];

    // --- ts: integer, > 0, close to now ---
    $tsOk = array_key_exists('ts', $rec)
        && is_int($rec['ts'])
        && $rec['ts'] > 0
        && $rec['ts'] >= ($before - 2)
        && $rec['ts'] <= ($after + 2);

    // --- sessionId: exact match ---
    $sidOk = array_key_exists('sessionId', $rec) && $rec['sessionId'] === $sessionId;

    // --- category: exact match ---
    $catOk = array_key_exists('category', $rec) && $rec['category'] === $category;

    if (!$tsOk || !$sidOk || !$catOk) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'why' => 'newest retained record missing/incorrect ts, sessionId, or category',
            'expectedSessionId' => $sessionId,
            'expectedCategory' => $category,
            'window' => "[{$before},{$after}]",
            'record' => $rec,
            'tsOk' => $tsOk, 'sidOk' => $sidOk, 'catOk' => $catOk,
        ];
        if (count($failures) >= 5) break;
    }
}

// Final append-only check across the whole run.
$finalRecords = RejectionAudit::readAll($tempDir);
if (empty($failures) && count($finalRecords) !== $recordedCount) {
    $failures[] = [
        'iter' => 'final', 'generator' => '-',
        'why' => 'final retained count != total recorded count',
        'recorded' => $recordedCount, 'retained' => count($finalRecords),
    ];
}

/* -------------------------------------------------------------------------- */
/*  Clean up temp dir.                                                         */
/* -------------------------------------------------------------------------- */

rmrf($tempDir);

/* -------------------------------------------------------------------------- */
/*  Report.                                                                    */
/* -------------------------------------------------------------------------- */

echo "Feature: research-tools, Property 15\n";
echo "RejectionAudit property test (rejected inputs are audited)\n";
echo str_repeat('-', 60) . "\n";
echo "Seed: {$seed} (set PBT_SEED={$seed} to replay)\n";
echo "Iterations: {$ITERATIONS}\n";
echo "Records persisted: {$recordedCount}\n";
echo "Category coverage:\n";
foreach ($counts as $n => $c) {
    printf("  %-20s %d\n", $n, $c);
}
echo str_repeat('-', 60) . "\n";

if (empty($failures)) {
    echo "PASS: all {$ITERATIONS} rejected submissions produced a retained audit\n";
    echo "      record with valid ts, exact sessionId, and exact category;\n";
    echo "      retained count matched recorded count (append-only).\n";
    exit(0);
}

echo 'FAIL: ' . count($failures) . " counterexample(s) found.\n\n";
foreach ($failures as $f) {
    echo "Counterexample (iteration {$f['iter']}, generator '{$f['generator']}'):\n";
    foreach ($f as $k => $v) {
        if (in_array($k, ['iter', 'generator'], true)) continue;
        echo "  {$k} : " . var_export($v, true) . "\n";
    }
    echo "\n";
}
exit(1);
