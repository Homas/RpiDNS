<?php
/**
 * Feature: research-tools, Property 9: ping and traceroute are always bounded
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * Property-based test for CommandBuilder (Property 9, Requirement 6.8).
 *
 * "For any ping or traceroute invocation build, the argument vector SHALL
 *  include a probe-count bound not exceeding the configured maximum, so the
 *  invocation self-terminates."
 *
 * This is a SELF-CONTAINED standalone PHP script (no PHPUnit / composer needed).
 * Run with:  php www/rpi_admin/tests/BoundedProbesPropertyTest.php
 *
 * Strategy: generate >= 100 randomized cases with varied configured maximum
 * probe/hop counts and varied (adversarial) target strings. For every built
 * ping argv assert it contains `-c` followed by a value <= getMaxProbes(); for
 * every built traceroute argv assert it contains `-q` <= getMaxProbes() and
 * `-m` <= getMaxHops(). Minimum 100 iterations.
 */

require_once __DIR__ . '/../CommandBuilder.php';

const MIN_ITERATIONS = 100;

/**
 * Find the integer value that immediately follows a given flag in an argv.
 *
 * @param array<int,string> $argv
 * @param string            $flag e.g. '-c'
 * @return int|null The integer following the flag, or null if not found / not numeric.
 */
function argAfterFlag(array $argv, string $flag): ?int {
    $count = count($argv);
    for ($i = 0; $i < $count - 1; $i++) {
        if ($argv[$i] === $flag) {
            $next = $argv[$i + 1];
            if (is_numeric($next)) {
                return (int)$next;
            }
            return null;
        }
    }
    return null;
}

/** Build a pool of varied / adversarial target strings. */
function targetPool(): array {
    return [
        'example.com',
        'sub.domain.example.org',
        '8.8.8.8',
        '1.1.1.1',
        '2001:4860:4860::8888',
        'fe80::1',
        'a.co',
        str_repeat('x', 60) . '.example.net',
        'host-with-dash.example.io',
        'xn--n3h.example',            // punycode
        'target; rm -rf /',           // shell metacharacters (input is verbatim, still one slot)
        'foo | bar',
        '`whoami`',
        "line1\nline2",
        '10.0.0.255',
        'localhost',
    ];
}

/**
 * Deterministic-ish pseudo random generator over a seed so the run is
 * reproducible while still exercising a wide input space.
 */
function pick(array $arr, int $seed): mixed {
    return $arr[$seed % count($arr)];
}

$failures = [];
$checked = 0;

// Candidate configured maxima: include zero / negative / oversized to exercise
// the constructor clamping, plus values within range.
$probeConfigs = [-5, 0, 1, 2, 3, 4, 5, 8, 10, 50, 1000, PHP_INT_MAX];
$hopConfigs   = [-1, 0, 1, 5, 15, 29, 30, 31, 64, 255, 1000];
$targets      = targetPool();

$iteration = 0;
while ($iteration < MIN_ITERATIONS || $iteration < count($probeConfigs) * count($hopConfigs)) {
    // Sweep the cartesian product first, then keep randomizing to reach >= 100.
    $pc = pick($probeConfigs, intdiv($iteration, count($hopConfigs)));
    $hc = pick($hopConfigs, $iteration);
    $target = pick($targets, ($iteration * 7 + 3));

    $builder = new CommandBuilder($pc, $hc);
    $maxProbes = $builder->getMaxProbes();
    $maxHops   = $builder->getMaxHops();

    // --- ping: must be bounded by -c <= maxProbes ---
    $pingBuilds = $builder->build('ping', ['target' => $target]);
    foreach ($pingBuilds as $argv) {
        $c = argAfterFlag($argv, '-c');
        if ($c === null) {
            $failures[] = sprintf(
                "ping missing -c probe bound (maxProbes=%d, target=%s): argv=%s",
                $maxProbes, json_encode($target), json_encode($argv)
            );
        } elseif ($c > $maxProbes) {
            $failures[] = sprintf(
                "ping -c=%d exceeds configured maxProbes=%d (target=%s): argv=%s",
                $c, $maxProbes, json_encode($target), json_encode($argv)
            );
        }
        $checked++;
    }

    // --- traceroute: must be bounded by -q <= maxProbes and -m <= maxHops ---
    $trBuilds = $builder->build('traceroute', ['target' => $target]);
    foreach ($trBuilds as $argv) {
        $q = argAfterFlag($argv, '-q');
        $m = argAfterFlag($argv, '-m');
        if ($q === null) {
            $failures[] = sprintf(
                "traceroute missing -q probe bound (maxProbes=%d, target=%s): argv=%s",
                $maxProbes, json_encode($target), json_encode($argv)
            );
        } elseif ($q > $maxProbes) {
            $failures[] = sprintf(
                "traceroute -q=%d exceeds configured maxProbes=%d (target=%s): argv=%s",
                $q, $maxProbes, json_encode($target), json_encode($argv)
            );
        }
        if ($m === null) {
            $failures[] = sprintf(
                "traceroute missing -m hop bound (maxHops=%d, target=%s): argv=%s",
                $maxHops, json_encode($target), json_encode($argv)
            );
        } elseif ($m > $maxHops) {
            $failures[] = sprintf(
                "traceroute -m=%d exceeds configured maxHops=%d (target=%s): argv=%s",
                $m, $maxHops, json_encode($target), json_encode($argv)
            );
        }
        $checked++;
    }

    $iteration++;
}

echo "Feature: research-tools, Property 9 - ping and traceroute are always bounded\n";
echo "Iterations: {$iteration}, argv assertions: {$checked}\n";

if (empty($failures)) {
    echo "PASS: every built ping/traceroute argv includes a probe-count bound within the configured maximum.\n";
    exit(0);
}

echo "FAIL: " . count($failures) . " counterexample(s) found:\n";
foreach (array_slice($failures, 0, 10) as $f) {
    echo "  - {$f}\n";
}
exit(1);
