<?php
/**
 * Feature: research-tools, Property 10: Tool output is truncated with a flag
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * Property-based test for ToolRunner (Property 10, Requirement 6.2).
 *
 * "For any tool output, the returned text SHALL be at most the configured
 *  maximum size, and the truncation flag SHALL be true if and only if the raw
 *  output exceeded that maximum."
 *
 * This is a SELF-CONTAINED standalone PHP script (no PHPUnit / composer needed).
 * Run with:  php www/rpi_admin/tests/OutputTruncationPropertyTest.php
 *
 * Strategy: generate >= 100 randomized cases. Each case picks a small maximum
 * output size M (1..2000 bytes) and a raw output size N (0..~2*M, including the
 * exact boundary N == M and N == M+1). A child process is run that emits exactly
 * N bytes via a fully portable argv:
 *     ['php', '-r', 'fwrite(STDOUT, str_repeat("x", N));']
 * proc_open executes the argv WITHOUT a shell, so N is passed as a discrete,
 * verbatim argument. For every run we assert:
 *     strlen(result['output']) <= M            (captured text never exceeds max)
 *     result['truncated'] === (N > M)          (flag true iff raw exceeded max)
 * Minimum 100 iterations.
 */

require_once __DIR__ . '/../ToolRunner.php';

const MIN_ITERATIONS = 100;

/** Locate the PHP CLI binary so the emitter child is fully portable. */
function phpBinary(): string {
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_executable(PHP_BINARY)) {
        return PHP_BINARY;
    }
    return 'php';
}

/**
 * Build an argv that emits exactly $n bytes of 'x' on stdout and nothing else.
 * The byte count is embedded in the -r program as an integer literal, keeping
 * the emission deterministic and shell-free.
 *
 * @return array<int,string>
 */
function emitBytesArgv(int $n): array {
    $n = max(0, $n);
    return [
        phpBinary(),
        '-r',
        'fwrite(STDOUT, str_repeat("x", ' . $n . '));',
    ];
}

$php = phpBinary();

$failures = [];
$checked = 0;

// A generous per-run wall-clock bound; the emitter exits near-instantly.
$timeoutSec = 15;

// Deterministic seed keeps the run reproducible while covering a wide space.
mt_srand(1010);

$iteration = 0;
while ($iteration < MIN_ITERATIONS) {
    // Random small maximum output size in bytes.
    $max = mt_rand(1, 2000);

    // Choose a raw size N. Bias toward the interesting region around the
    // boundary (M-1, M, M+1) while still sampling the wider 0..2*M range.
    $mode = $iteration % 5;
    switch ($mode) {
        case 0: $n = $max;            break; // exact boundary: not truncated
        case 1: $n = $max + 1;        break; // one over: truncated
        case 2: $n = max(0, $max - 1);break; // one under: not truncated
        case 3: $n = 0;               break; // empty output: not truncated
        default: $n = mt_rand(0, 2 * $max); break; // anywhere in range
    }

    $runner = new ToolRunner($timeoutSec, $max);
    $result = $runner->run('emit', "N={$n};M={$max}", emitBytesArgv($n));

    $outLen = strlen($result['output']);
    $expectedTruncated = ($n > $max);

    // The emitter must have started and exited cleanly; a start failure would
    // invalidate the measurement, so treat it as a counterexample.
    if ($result['reason'] === ToolRunner::REASON_START_FAILED) {
        $failures[] = sprintf(
            "tool failed to start (M=%d, N=%d) using php=%s",
            $max, $n, $php
        );
        $iteration++;
        $checked++;
        continue;
    }

    // Assertion 1: captured text never exceeds the configured maximum.
    if ($outLen > $max) {
        $failures[] = sprintf(
            "output length %d exceeds configured max %d (N=%d)",
            $outLen, $max, $n
        );
    }

    // Assertion 2: truncated flag is true iff the raw output exceeded the max.
    if ($result['truncated'] !== $expectedTruncated) {
        $failures[] = sprintf(
            "truncated flag=%s but expected %s (M=%d, N=%d, capturedLen=%d)",
            var_export($result['truncated'], true),
            var_export($expectedTruncated, true),
            $max, $n, $outLen
        );
    }

    // Sanity: when not truncated, the captured length should equal N (bounded by M).
    if (!$expectedTruncated && $outLen !== min($n, $max)) {
        $failures[] = sprintf(
            "non-truncated captured length %d != expected %d (M=%d, N=%d)",
            $outLen, min($n, $max), $max, $n
        );
    }

    $checked++;
    $iteration++;
}

echo "Feature: research-tools, Property 10 - Tool output is truncated with a flag\n";
echo "Iterations: {$iteration}, runs checked: {$checked}\n";

if (empty($failures)) {
    echo "PASS: captured output never exceeds the configured max and the truncation flag is true iff raw output exceeded it.\n";
    exit(0);
}

echo "FAIL: " . count($failures) . " counterexample(s) found:\n";
foreach (array_slice($failures, 0, 10) as $f) {
    echo "  - {$f}\n";
}
exit(1);
