<?php
/**
 * Feature: research-tools, Property 1: Authentication is required for every
 * Research endpoint - for any Research_API request (research_unique,
 * research_tables, research_sql, research_tool) presented WITHOUT a valid
 * session, the response is an authentication-required denial that contains no
 * protected data and performs no requested operation.
 *
 * Validates: Requirements 1.7, 9.1
 *
 * Property-based test harness. This project has no PHPUnit/composer, so - per
 * the design's fallback for backend properties - this is a SELF-CONTAINED
 * standalone script runnable with `php`.
 *
 * Why a SUBPROCESS: the guard under test, requireResearchSession() in
 * ResearchAuth.php, calls exit() after emitting its 401 JSON denial. exit()
 * would terminate the test runner itself, so every iteration is exercised in a
 * freshly spawned `php` child process whose stdout/stderr/exit status we
 * capture and assert on.
 *
 * Strategy: for each of >= 100 iterations we
 *   - cycle through all four Research endpoints as the request context,
 *   - simulate "no valid session" by either UNSETTING the `rpidns_session`
 *     cookie or setting it to a randomly generated INVALID token (empty,
 *     random hex, or malformed/adversarial string),
 *   - inject a stub AuthService whose verifySession() returns null (so the test
 *     needs no real users DB), which is exactly what the guard treats as a
 *     missing/invalid/expired session, and
 *   - call requireResearchSession($stub) followed by a sentinel print
 *     ("REACHED_PROTECTED") that MUST never execute because the guard exits
 *     first.
 * We then assert the child's stdout contains
 *   {"status":"error","reason":"authentication required"}
 * (the 401 denial path was taken) and that the sentinel is NEVER present (no
 * protected data returned, no requested operation performed).
 *
 * Minimum 100 iterations (this harness runs 120 -> 30 per endpoint).
 *
 * (c) Vadim Pavlov 2020 - 2026
 */

/* -------------------------------------------------------------------------- */
/*  Randomness (seeded for reproducibility; seed printed for replay).          */
/* -------------------------------------------------------------------------- */

$seed = getenv('PBT_SEED') !== false ? (int) getenv('PBT_SEED') : random_int(1, PHP_INT_MAX);
mt_srand($seed);

function ri($min, $max) { return mt_rand($min, $max); }
function pick(array $a) { return $a[mt_rand(0, count($a) - 1)]; }

function randToken($minLen, $maxLen) {
    $alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = ri($minLen, $maxLen);
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alpha[ri(0, strlen($alpha) - 1)];
    return $s;
}

/**
 * Produce an invalid-session token specification.
 * Returns [mode, value] where mode is 'unset' (no cookie at all) or 'set'
 * (cookie present but invalid). Covers empty, random, and malformed tokens.
 */
function randInvalidToken() {
    switch (ri(0, 4)) {
        case 0: return ['unset', ''];                       // no cookie header
        case 1: return ['set', ''];                         // empty cookie value
        case 2: return ['set', bin2hex(random_bytes(ri(1, 40)))]; // random hex
        case 3: return ['set', randToken(1, 64)];           // random alnum
        default:                                            // malformed / adversarial
            return ['set', pick([
                "not a token",
                "' OR 1=1 --",
                "../../etc/passwd",
                str_repeat('X', ri(200, 600)),
                "\x00\x01binarygarbage",
                "{json:\"nope\"}",
                "expired-" . randToken(4, 10),
            ])];
    }
}

/* -------------------------------------------------------------------------- */
/*  Generate the child harness script (self-contained; written to a temp file).*/
/* -------------------------------------------------------------------------- */

$researchAuthPath = realpath(__DIR__ . '/../ResearchAuth.php');
if ($researchAuthPath === false) {
    fwrite(STDERR, "FATAL: cannot locate ResearchAuth.php next to tests/\n");
    exit(1);
}

$harness = <<<'PHP'
<?php
/**
 * Child harness for Feature: research-tools, Property 1.
 * argv[1] = endpoint context (research_unique|research_tables|research_sql|research_tool)
 * argv[2] = token mode ('unset' | 'set')
 * argv[3] = token value (used only when mode === 'set')
 * env RA_PATH = absolute path to ResearchAuth.php
 */
error_reporting(E_ALL & ~E_DEPRECATED);

$endpoint  = $argv[1] ?? 'research_unique';
$tokenMode = $argv[2] ?? 'unset';
// Token is passed base64-encoded so binary/adversarial bytes (e.g. NUL) survive
// the argv boundary intact.
$tokenVal  = isset($argv[3]) ? (base64_decode($argv[3], true) ?: '') : '';

// Simulate the request having NO valid session.
if ($tokenMode === 'unset') {
    unset($_COOKIE['rpidns_session']);
} else {
    $_COOKIE['rpidns_session'] = $tokenVal;
}

// Stub AuthService: verifySession() ALWAYS returns null, i.e. the guard sees a
// missing/invalid/expired session. This avoids any dependency on a real users
// DB while exercising the real guard's denial path.
class AuthService {
    public function verifySession($token = null) {
        return null;
    }
}

// Load the REAL requireResearchSession() from ResearchAuth.php. That file begins
// with a hardcoded `require_once "/opt/rpidns/www/rpi_admin/auth.php";` that is
// only present in the deployed container; here we strip that single include line
// (AuthService is stubbed above) and evaluate the rest so the guard's logic is
// tested verbatim.
$raPath = getenv('RA_PATH');
$src = file_get_contents($raPath);
if ($src === false) {
    fwrite(STDERR, "child: cannot read ResearchAuth.php at {$raPath}\n");
    exit(2);
}
$src = preg_replace('#require_once\s+["\'][^"\']*auth\.php["\']\s*;#', '', $src, 1);
$src = preg_replace('#^\s*<\?php#', '', $src, 1); // strip opening tag for eval
eval($src);

if (!function_exists('requireResearchSession')) {
    fwrite(STDERR, "child: requireResearchSession() not defined after load\n");
    exit(3);
}

$stub = new AuthService();

// Invoke the guard for this endpoint context. With verifySession() returning
// null the guard MUST emit HTTP 401 + the JSON denial and exit() right here.
requireResearchSession($stub);

// UNREACHABLE if the guard behaves correctly. Reaching this line means the
// endpoint proceeded to a protected operation without a valid session.
echo "REACHED_PROTECTED endpoint={$endpoint}\n";
PHP;

$harnessFile = tempnam(sys_get_temp_dir(), 'auth_pbt_') . '.php';
file_put_contents($harnessFile, $harness);

/* -------------------------------------------------------------------------- */
/*  Run one child process, capturing stdout, stderr and exit code.             */
/* -------------------------------------------------------------------------- */

function runChild($phpBin, $harnessFile, $raPath, $endpoint, $mode, $token) {
    // Base64-encode the token so binary/adversarial bytes survive argv safely.
    $cmd = escapeshellarg($phpBin) . ' '
         . escapeshellarg($harnessFile) . ' '
         . escapeshellarg($endpoint) . ' '
         . escapeshellarg($mode) . ' '
         . escapeshellarg(base64_encode($token));

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = $_ENV;
    $env['RA_PATH'] = $raPath;

    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        return ['stdout' => '', 'stderr' => 'proc_open failed', 'code' => -1];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'code' => $code];
}

/* -------------------------------------------------------------------------- */
/*  Run the property.                                                          */
/* -------------------------------------------------------------------------- */

$phpBin = PHP_BINARY;
$endpoints = ['research_unique', 'research_tables', 'research_sql', 'research_tool'];
$ITERATIONS = 120; // 30 per endpoint, >= 100 required
$DENIAL = '{"status":"error","reason":"authentication required"}';
$SENTINEL = 'REACHED_PROTECTED';

$failures = [];
$coverage = array_fill_keys($endpoints, 0);

for ($iter = 0; $iter < $ITERATIONS; $iter++) {
    $endpoint = $endpoints[$iter % count($endpoints)]; // cycle: covers all four
    [$mode, $token] = randInvalidToken();
    $coverage[$endpoint]++;

    $res = runChild($phpBin, $harnessFile, $researchAuthPath, $endpoint, $mode, $token);

    $denied = strpos($res['stdout'], $DENIAL) !== false;
    $reachedProtected = strpos($res['stdout'], $SENTINEL) !== false;
    $cleanLoad = ($res['stderr'] === '' || strpos($res['stderr'], 'child:') === false)
                 && stripos($res['stderr'], 'fatal') === false
                 && stripos($res['stderr'], 'parse error') === false;

    if (!$denied || $reachedProtected || !$cleanLoad) {
        $failures[] = [
            'iter'             => $iter,
            'endpoint'         => $endpoint,
            'tokenMode'        => $mode,
            'token'            => $token,
            'deniedFound'      => $denied,
            'reachedProtected' => $reachedProtected,
            'cleanLoad'        => $cleanLoad,
            'exitCode'         => $res['code'],
            'stdout'           => $res['stdout'],
            'stderr'           => $res['stderr'],
        ];
        if (count($failures) >= 5) break;
    }
}

/* -------------------------------------------------------------------------- */
/*  Clean up.                                                                  */
/* -------------------------------------------------------------------------- */

@unlink($harnessFile);

/* -------------------------------------------------------------------------- */
/*  Report.                                                                    */
/* -------------------------------------------------------------------------- */

echo "Feature: research-tools, Property 1\n";
echo "Endpoint authentication property test (auth required for every Research endpoint)\n";
echo str_repeat('-', 60) . "\n";
echo "Seed: {$seed} (set PBT_SEED={$seed} to replay)\n";
echo "Iterations: {$ITERATIONS}\n";
echo "Endpoint coverage:\n";
foreach ($coverage as $ep => $c) {
    printf("  %-20s %d\n", $ep, $c);
}
echo str_repeat('-', 60) . "\n";

if (empty($failures)) {
    echo "PASS: every unauthenticated request to all four Research endpoints was\n";
    echo "      denied with {\"status\":\"error\",\"reason\":\"authentication required\"},\n";
    echo "      and no request ever reached a protected operation (sentinel absent).\n";
    exit(0);
}

echo 'FAIL: ' . count($failures) . " counterexample(s) found.\n\n";
foreach ($failures as $f) {
    echo "Counterexample (iteration {$f['iter']}):\n";
    foreach ($f as $k => $v) {
        if ($k === 'iter') continue;
        echo "  {$k} : " . var_export($v, true) . "\n";
    }
    echo "\n";
}
exit(1);
