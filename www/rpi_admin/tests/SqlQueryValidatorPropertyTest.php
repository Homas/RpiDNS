<?php
/**
 * Feature: research-tools, Property 6: SQL validator accepts exactly the single
 * read-only SELECTs - for any submitted string, the SqlQueryValidator accepts it
 * if and only if it is a single statement whose first significant keyword is
 * SELECT or WITH ... SELECT, contains no Write_Operation keyword, and is at most
 * 10,000 characters; every rejected submission is not executed.
 *
 * Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.11, 9.4
 *
 * Property-based test harness. This project has no PHPUnit/composer, so - per the
 * design's fallback ("port the validator/builder logic behind a thin, testable
 * interface and drive it with generated inputs via a data-provider that enumerates
 * a large randomized corpus (>= 100 cases)") - this is a SELF-CONTAINED standalone
 * script runnable with `php`.
 *
 * Each generated case is labeled by an INDEPENDENT reference oracle (the generator
 * that constructs the case knows, by construction, the correct accept/reject
 * classification per Property 6). The validator's decision is asserted against that
 * oracle for every generated input.
 *
 * Minimum 100 iterations (this harness runs 400).
 *
 * (c) Vadim Pavlov 2020 - 2026
 */

require_once __DIR__ . '/../SqlQueryValidator.php';

/* -------------------------------------------------------------------------- */
/*  Randomness (seeded for reproducibility; seed printed for replay).          */
/* -------------------------------------------------------------------------- */

$seed = getenv('PBT_SEED') !== false ? (int) getenv('PBT_SEED') : random_int(1, PHP_INT_MAX);
mt_srand($seed);

function ri($min, $max) { return mt_rand($min, $max); }
function pick(array $a) { return $a[mt_rand(0, count($a) - 1)]; }
function chance($pct) { return mt_rand(1, 100) <= $pct; }

/* -------------------------------------------------------------------------- */
/*  Building blocks.                                                           */
/* -------------------------------------------------------------------------- */

// The exact forbidden-keyword set the validator rejects as a bare token.
$FORBIDDEN = [
    'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'DROP', 'CREATE', 'ALTER',
    'TRUNCATE', 'ATTACH', 'DETACH', 'VACUUM', 'REINDEX', 'PRAGMA',
    'BEGIN', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'ANALYZE'
];

// Randomise the case of a keyword: SELECT -> sElEcT etc. (Req: mixed case).
function mixCase($word) {
    $out = '';
    for ($i = 0, $n = strlen($word); $i < $n; $i++) {
        $c = $word[$i];
        $out .= chance(50) ? strtoupper($c) : strtolower($c);
    }
    return $out;
}

// A safe SQL identifier that is guaranteed NOT to equal any forbidden keyword.
function safeIdent() {
    global $FORBIDDEN;
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    do {
        $len = ri(1, 8);
        $s = $letters[ri(0, 25)];
        for ($i = 1; $i < $len; $i++) {
            $s .= chance(80) ? $letters[ri(0, 25)] : (string) ri(0, 9);
        }
    } while (in_array(strtoupper($s), $FORBIDDEN, true));
    return $s;
}

function safeColumnList() {
    if (chance(25)) return '*';
    $n = ri(1, 4);
    $cols = [];
    for ($i = 0; $i < $n; $i++) $cols[] = safeIdent();
    return implode(', ', $cols);
}

// Random whitespace (spaces, tabs, newlines) to vary formatting.
function ws() {
    $chars = [' ', '  ', "\t", "\n", " \n ", ' '];
    return pick($chars);
}

// A comment (line or block) whose CONTENT includes a forbidden keyword and a ';'.
// Because comments are stripped before analysis, these must NOT affect the verdict.
function noiseComment() {
    global $FORBIDDEN;
    $kw = pick($FORBIDDEN);
    $filler = $kw . ' TABLE ' . safeIdent() . ' ; VALUES (1)';
    if (chance(50)) {
        return "-- " . $filler . "\n";      // line comment (needs newline terminator)
    }
    return "/* " . $filler . " */";          // block comment
}

// A single-quoted string literal whose CONTENT includes a forbidden keyword and ';'.
// Masked before analysis, so it must NOT affect the verdict.
function noiseStringLiteral() {
    global $FORBIDDEN;
    $kw = pick($FORBIDDEN);
    return "'" . $kw . " x; " . safeIdent() . "'";
}

// A valid single read-only SELECT statement (no bare forbidden token, no bare ';').
function buildValidSelect() {
    $sql = mixCase('SELECT') . ws() . safeColumnList()
         . ws() . mixCase('FROM') . ws() . safeIdent();

    // Optional WHERE using a masked string literal (may embed a write keyword).
    if (chance(60)) {
        $sql .= ws() . mixCase('WHERE') . ws() . safeIdent() . ws() . '=' . ws()
              . (chance(50) ? noiseStringLiteral() : (string) ri(0, 9999));
    }
    // Optional LIMIT.
    if (chance(40)) {
        $sql .= ws() . mixCase('LIMIT') . ws() . ri(1, 500);
    }
    // Optional leading and/or trailing noise comment containing write keywords.
    if (chance(50)) $sql = noiseComment() . ws() . $sql;
    if (chance(50)) $sql = $sql . ws() . noiseComment();
    // Optional single trailing semicolon (still a single statement -> accepted).
    if (chance(50)) $sql .= ws() . ';';

    return $sql;
}

// A valid WITH ... SELECT CTE statement.
function buildValidWith() {
    $cte = safeIdent();
    $sql = mixCase('WITH') . ws() . $cte . ws() . mixCase('AS') . ws() . '('
         . mixCase('SELECT') . ws() . safeColumnList() . ws() . mixCase('FROM')
         . ws() . safeIdent() . ')'
         . ws() . mixCase('SELECT') . ws() . '*' . ws() . mixCase('FROM') . ws() . $cte;

    if (chance(50)) $sql = noiseComment() . ws() . $sql;
    if (chance(40)) $sql .= ws() . ';';
    return $sql;
}

/* -------------------------------------------------------------------------- */
/*  Case generators. Each returns [sql, expectedValid, expectedCategoryOrNull].*/
/*  The (expectedValid, expectedCategory) pair is the INDEPENDENT ORACLE.      */
/* -------------------------------------------------------------------------- */

function genValidSelect()  { return [buildValidSelect(), true,  null]; }
function genValidWith()    { return [buildValidWith(),   true,  null]; }

// Two statements separated by a bare ';' -> multi-statement rejection.
function genMultiStatement() {
    $a = mixCase('SELECT') . ' ' . ri(1, 99);
    $b = mixCase('SELECT') . ' ' . safeColumnList() . ' ' . mixCase('FROM') . ' ' . safeIdent();
    $sql = $a . ' ; ' . $b;
    if (chance(40)) $sql .= ' ;'; // an extra trailing ; is still multi-statement
    return [$sql, false, SqlQueryValidator::CAT_MULTI_STATEMENT];
}

// A bare forbidden keyword present as a token (no semicolon) -> write attempt.
function genWriteAttempt() {
    global $FORBIDDEN;
    $kw = mixCase(pick($FORBIDDEN));
    if (chance(50)) {
        // Statement begins with a write keyword.
        $sql = $kw . ' ' . mixCase('TABLE') . ' ' . safeIdent() . ' ' . safeIdent();
    } else {
        // SELECT that contains a bare forbidden token but NO semicolon, so the
        // multi-statement check does not fire first.
        $sql = mixCase('SELECT') . ' ' . safeColumnList() . ' ' . mixCase('FROM')
             . ' ' . safeIdent() . ' ' . $kw;
    }
    return [$sql, false, SqlQueryValidator::CAT_WRITE_ATTEMPT];
}

// A lone forbidden keyword statement (e.g. VACUUM, PRAGMA ...) -> write attempt.
function genBareForbidden() {
    global $FORBIDDEN;
    $kw = mixCase(pick($FORBIDDEN));
    $sql = chance(50) ? $kw : ($kw . ' ' . safeIdent());
    return [$sql, false, SqlQueryValidator::CAT_WRITE_ATTEMPT];
}

// A valid SELECT padded past MAX_LENGTH raw characters -> too_long.
function genOverLength() {
    $base = buildValidSelect();
    $target = SqlQueryValidator::MAX_LENGTH + ri(1, 2000);
    $pad = $target - strlen($base);
    if ($pad < 1) $pad = 1;
    // Pad with spaces before the statement so raw length exceeds the bound.
    $sql = str_repeat(' ', $pad) . $base;
    return [$sql, false, SqlQueryValidator::CAT_TOO_LONG];
}

// Empty / whitespace-only / WITH-without-SELECT -> invalid_input.
function genInvalidInput() {
    $variant = ri(0, 2);
    if ($variant === 0) return ['', false, SqlQueryValidator::CAT_INVALID_INPUT];
    if ($variant === 1) return [str_repeat(pick([' ', "\t", "\n"]), ri(1, 6)), false, SqlQueryValidator::CAT_INVALID_INPUT];
    // WITH clause with no SELECT anywhere.
    $sql = mixCase('WITH') . ' ' . safeIdent() . ' ' . mixCase('AS') . ' (' . safeIdent() . ')';
    return [$sql, false, SqlQueryValidator::CAT_INVALID_INPUT];
}

$generators = [
    'valid_select'     => 'genValidSelect',
    'valid_with'       => 'genValidWith',
    'multi_statement'  => 'genMultiStatement',
    'write_attempt'    => 'genWriteAttempt',
    'bare_forbidden'   => 'genBareForbidden',
    'over_length'      => 'genOverLength',
    'invalid_input'    => 'genInvalidInput',
];

/* -------------------------------------------------------------------------- */
/*  Run the property.                                                          */
/* -------------------------------------------------------------------------- */

$ITERATIONS = 400;
$genNames = array_keys($generators);
$failures = [];
$counts = array_fill_keys($genNames, 0);

for ($iter = 0; $iter < $ITERATIONS; $iter++) {
    $name = $genNames[$iter % count($genNames)]; // even coverage across categories
    $fn = $generators[$name];
    [$sql, $expectedValid, $expectedCategory] = $fn();
    $counts[$name]++;

    $result = SqlQueryValidator::validate($sql);

    // --- Property 6 accept-iff assertion ---
    if ($result['valid'] !== $expectedValid) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'sql' => $sql, 'expectedValid' => $expectedValid,
            'gotValid' => $result['valid'], 'gotCategory' => $result['category'],
            'gotReason' => $result['reason'],
            'why' => 'accept/reject verdict mismatch',
        ];
        if (count($failures) >= 5) break;
        continue;
    }

    // When rejected, cross-check the rejection category against the oracle.
    if (!$expectedValid && $expectedCategory !== null && $result['category'] !== $expectedCategory) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name,
            'sql' => $sql, 'expectedCategory' => $expectedCategory,
            'gotCategory' => $result['category'], 'gotReason' => $result['reason'],
            'why' => 'rejection category mismatch',
        ];
        if (count($failures) >= 5) break;
        continue;
    }

    // Rejected submissions must never be reported as valid (never executed).
    if (!$expectedValid && $result['valid'] === true) {
        $failures[] = [
            'iter' => $iter, 'generator' => $name, 'sql' => $sql,
            'why' => 'rejected submission reported as valid',
        ];
        if (count($failures) >= 5) break;
    }
}

/* -------------------------------------------------------------------------- */
/*  Report.                                                                    */
/* -------------------------------------------------------------------------- */

echo "Feature: research-tools, Property 6\n";
echo "SqlQueryValidator property test\n";
echo str_repeat('-', 60) . "\n";
echo "Seed: {$seed} (set PBT_SEED={$seed} to replay)\n";
echo "Iterations: {$ITERATIONS}\n";
echo "Category coverage:\n";
foreach ($counts as $n => $c) {
    printf("  %-16s %d\n", $n, $c);
}
echo str_repeat('-', 60) . "\n";

if (empty($failures)) {
    echo "PASS: all {$ITERATIONS} generated cases satisfied Property 6.\n";
    exit(0);
}

echo 'FAIL: ' . count($failures) . " counterexample(s) found.\n\n";
foreach ($failures as $f) {
    echo "Counterexample (iteration {$f['iter']}, generator '{$f['generator']}'):\n";
    echo "  reason : {$f['why']}\n";
    echo "  sql    : " . var_export($f['sql'], true) . "\n";
    foreach ($f as $k => $v) {
        if (in_array($k, ['iter', 'generator', 'sql', 'why'], true)) continue;
        echo "  {$k} : " . var_export($v, true) . "\n";
    }
    echo "\n";
}
exit(1);
