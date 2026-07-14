<?php
/**
 * Feature: research-tools
 *
 * (c) Vadim Pavlov 2020 - 2026
 *
 * Backend integration / smoke tests for the Research API endpoints and their
 * supporting components (Task 5.10).
 *
 * This is a SELF-CONTAINED standalone PHP script - no PHPUnit / composer needed.
 * Run with:  php www/rpi_admin/tests/EndpointsIntegrationSmokeTest.php
 *
 * ---------------------------------------------------------------------------
 * Requirements exercised
 * ---------------------------------------------------------------------------
 *  - Req 4.5        : read-only connection rejects a write attempt.
 *  - Req 4.8, 4.10  : a slow query / long-running command is terminated at its
 *                     wall-clock bound with a timeout error (validated with a
 *                     deterministic `sleep`-like proxy through ToolRunner, which
 *                     is the same 30s wall-clock mechanism the research_sql /
 *                     research_tool endpoints rely on).
 *  - Req 6.3        : `dig` uses the appliance default resolver when no server
 *                     is given, and a single `@server` argument when one is.
 *  - Req 6.7        : `ping` / `traceroute` self-terminate within the bound.
 *  - Req 8.1-8.7    : additional tools (reverse_dns, nsmx, geoip, asn, tls_cert,
 *                     reputation) run one example each where possible.
 *  - Req 8.11       : graceful failure handling (a ToolResult with exitError /
 *                     reason) when an upstream / binary / network is unavailable.
 *
 * ---------------------------------------------------------------------------
 * Determinism vs. best-effort
 * ---------------------------------------------------------------------------
 * DETERMINISTIC checks always run and must PASS in any environment:
 *   * Read-only SQLite connection rejects INSERT / UPDATE / DELETE (Req 4.5).
 *   * CommandBuilder argv smoke: dig default vs. custom server; ping/traceroute
 *     carry bounded probe flags (Req 6.3, 6.8).
 *   * ToolRunner enforces its wall-clock bound against a `sleep`-like process,
 *     returning reason='timeout' (proxy for Req 4.8 / 4.10 / 6.7).
 *
 * BEST-EFFORT checks run only when the required binary / network is available.
 * When a binary is missing or the network is unreachable they print a clear
 * SKIP and do NOT fail the suite - but they still FAIL on a genuine defect
 * (e.g. a malformed ToolResult envelope, or a runtime that blows past the
 * bound). Graceful failure (a well-formed ToolResult with exitError=true and a
 * reason) is an acceptable, non-failing outcome for the network tools.
 *
 * The suite prints a PASS / SKIP / FAIL summary and exits 0 iff no genuine
 * assertion failed (SKIPs are OK).
 */

require_once __DIR__ . '/../CommandBuilder.php';
require_once __DIR__ . '/../ToolRunner.php';

// ---------------------------------------------------------------------------
// Tiny assertion harness
// ---------------------------------------------------------------------------

$GLOBALS['__pass'] = 0;
$GLOBALS['__skip'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__failures'] = [];

/** Record a PASS. */
function pass(string $name): void {
    $GLOBALS['__pass']++;
    echo "  PASS  {$name}\n";
}

/** Record a SKIP (not a failure). */
function skip(string $name, string $reason): void {
    $GLOBALS['__skip']++;
    echo "  SKIP  {$name}  ({$reason})\n";
}

/** Record a FAIL with an explanatory message. */
function fail(string $name, string $msg): void {
    $GLOBALS['__fail']++;
    $GLOBALS['__failures'][] = "{$name}: {$msg}";
    echo "  FAIL  {$name}  -> {$msg}\n";
}

/** Assert a boolean condition; PASS on true, FAIL on false. */
function check(bool $cond, string $name, string $failMsg = 'condition was false'): void {
    if ($cond) {
        pass($name);
    } else {
        fail($name, $failMsg);
    }
}

/**
 * Verify a value has the ToolResult envelope shape produced by ToolRunner.
 *
 * @param mixed $r
 */
function isToolResult($r): bool {
    return is_array($r)
        && array_key_exists('tool', $r)
        && array_key_exists('target', $r)
        && array_key_exists('output', $r)
        && array_key_exists('truncated', $r)
        && array_key_exists('exitError', $r)
        && array_key_exists('reason', $r)
        && is_bool($r['truncated'])
        && is_bool($r['exitError']);
}

/**
 * Detect whether an executable is resolvable on PATH (no shell needed).
 */
function binAvailable(string $bin): bool {
    // Absolute path given directly.
    if (strpos($bin, '/') !== false) {
        return is_executable($bin);
    }
    $path = getenv('PATH') ?: '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin';
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        if ($dir === '') {
            continue;
        }
        $candidate = rtrim($dir, '/') . '/' . $bin;
        if (is_executable($candidate)) {
            return true;
        }
    }
    return false;
}

echo "Feature: research-tools - Backend endpoints integration / smoke tests\n";
echo str_repeat('=', 74) . "\n";

// ===========================================================================
// SECTION 1 (DETERMINISTIC): read-only connection rejects a write (Req 4.5)
// ===========================================================================
echo "\n[1] Read-only SQLite connection rejects writes (Req 4.5)\n";

$tmpDb = tempnam(sys_get_temp_dir(), 'rpidns_ro_') . '.sqlite';
try {
    // Seed a read-write database with one table and one row.
    $rw = new SQLite3($tmpDb);
    $rw->enableExceptions(true);
    $rw->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
    $rw->exec("INSERT INTO t(v) VALUES ('seed')");
    $rw->close();

    // Open a SEPARATE read-only connection (mirrors research_sql / research_tables).
    $ro = new SQLite3($tmpDb, SQLITE3_OPEN_READONLY);
    $ro->enableExceptions(true);

    $before = (int)$ro->querySingle('SELECT COUNT(*) FROM t');

    $writeStatements = [
        "INSERT INTO t(v) VALUES ('should-fail')",
        "UPDATE t SET v='changed' WHERE id=1",
        "DELETE FROM t WHERE id=1",
    ];

    foreach ($writeStatements as $stmt) {
        $rejected = false;
        try {
            $res = $ro->exec($stmt);
            // Even without exceptions, exec() must not succeed on a RO connection.
            if ($res === false) {
                $rejected = true;
            }
        } catch (\Throwable $e) {
            // SQLite reports "attempt to write a readonly database".
            $rejected = true;
        }
        check($rejected, "readonly rejects: {$stmt}", 'write was NOT rejected on a READONLY connection');
    }

    $after = (int)$ro->querySingle('SELECT COUNT(*) FROM t');
    check($before === $after && $after === 1,
        'readonly leaves row count unchanged',
        "row count changed: before={$before} after={$after}");

    $ro->close();
} catch (\Throwable $e) {
    fail('readonly setup', 'unexpected exception: ' . $e->getMessage());
} finally {
    if (is_file($tmpDb)) {
        @unlink($tmpDb);
    }
}

// ===========================================================================
// SECTION 2 (DETERMINISTIC): CommandBuilder argv smoke (Req 6.3, 6.8)
// ===========================================================================
echo "\n[2] CommandBuilder argv smoke (Req 6.3, 6.8)\n";

$cb = new CommandBuilder(4 /*maxProbes*/, 30 /*maxHops*/);

// -- dig without a server: no '@' argument (uses default resolver, Req 6.3). --
$digDefault = $cb->build('dig', ['target' => 'example.com'])[0];
$atArgsDefault = array_values(array_filter($digDefault, fn($a) => strlen($a) > 0 && $a[0] === '@'));
check(count($atArgsDefault) === 0,
    'dig (default) has no @server argument',
    'expected zero @args, got: ' . json_encode($atArgsDefault));
check($digDefault[0] === 'dig' && in_array('example.com', $digDefault, true),
    'dig (default) argv is well-formed',
    'argv: ' . json_encode($digDefault));

// -- dig with a server: exactly one '@server' argument (Req 6.3/6.4). --------
$digServer = $cb->build('dig', ['target' => 'example.com', 'dns_server' => '9.9.9.9'])[0];
$atArgsServer = array_values(array_filter($digServer, fn($a) => strlen($a) > 0 && $a[0] === '@'));
check(count($atArgsServer) === 1 && $atArgsServer[0] === '@9.9.9.9',
    'dig (custom server) has exactly one @server argument',
    'expected ["@9.9.9.9"], got: ' . json_encode($atArgsServer));
// The target must still occupy its own discrete, verbatim slot.
check(in_array('example.com', $digServer, true),
    'dig (custom server) keeps target as a discrete argument',
    'argv: ' . json_encode($digServer));

// -- ping carries a bounded probe count (-c N) so it self-terminates. --------
$ping = $cb->build('ping', ['target' => 'example.com'])[0];
$ci = array_search('-c', $ping, true);
check($ci !== false && isset($ping[$ci + 1]) && (int)$ping[$ci + 1] > 0 && (int)$ping[$ci + 1] <= 4,
    'ping argv carries bounded probe count (-c <= max)',
    'argv: ' . json_encode($ping));

// -- traceroute carries bounded probes-per-hop (-q) and max hops (-m). -------
$tr = $cb->build('traceroute', ['target' => 'example.com'])[0];
$qi = array_search('-q', $tr, true);
$mi = array_search('-m', $tr, true);
check($qi !== false && isset($tr[$qi + 1]) && (int)$tr[$qi + 1] > 0 && (int)$tr[$qi + 1] <= 4,
    'traceroute argv carries bounded probes-per-hop (-q <= max)',
    'argv: ' . json_encode($tr));
check($mi !== false && isset($tr[$mi + 1]) && (int)$tr[$mi + 1] > 0 && (int)$tr[$mi + 1] <= 30,
    'traceroute argv carries bounded max hops (-m <= max)',
    'argv: ' . json_encode($tr));

// ===========================================================================
// SECTION 3 (DETERMINISTIC): wall-clock timeout bound (Req 4.8, 4.10, 6.7)
// ===========================================================================
// A slow query (research_sql) and a slow tool (research_tool) share the same
// 30s wall-clock guard. We validate that guard deterministically with a
// `sleep`-like process: ToolRunner with a 2s bound against a 6s sleeper must
// terminate it and report reason='timeout' well before the sleeper finishes.
echo "\n[3] Wall-clock bound terminates a slow process (Req 4.8, 4.10, 6.7)\n";

$phpBin = PHP_BINARY ?: 'php';
if (!is_executable($phpBin) && !binAvailable('php')) {
    skip('timeout proxy', 'no php binary available to spawn a sleeper');
} else {
    $sleeper = [$phpBin, '-r', 'sleep(6);'];
    $runner = new ToolRunner(2 /*timeoutSec*/, 65536);
    $t0 = microtime(true);
    $res = $runner->run('sleep-proxy', 'localhost', $sleeper);
    $elapsed = microtime(true) - $t0;

    check(isToolResult($res), 'timeout: result is a well-formed ToolResult',
        'result: ' . json_encode($res));
    check(($res['reason'] ?? null) === ToolRunner::REASON_TIMEOUT,
        'timeout: reason is "timeout"',
        'reason=' . json_encode($res['reason'] ?? null));
    check($res['exitError'] === true, 'timeout: exitError flagged true',
        'exitError=' . var_export($res['exitError'] ?? null, true));
    // The process must have been killed near the 2s bound, not run the full 6s.
    check($elapsed < 5.0, 'timeout: terminated near the bound (not full sleep)',
        sprintf('elapsed=%.2fs (expected < 5s)', $elapsed));
}

// ===========================================================================
// SECTION 4 (BEST-EFFORT): core network tools run and self-terminate
// ===========================================================================
echo "\n[4] Core network tools - best-effort (Req 6.3, 6.7)\n";

$netRunner = new ToolRunner(20 /*timeoutSec*/, 262144);

// -- dig default resolver -----------------------------------------------------
if (!binAvailable(CommandBuilder::BIN_DIG)) {
    skip('dig (default resolver)', 'dig binary not available');
    skip('dig (custom server)', 'dig binary not available');
} else {
    $argv = $cb->build('dig', ['target' => 'example.com'])[0];
    $r = $netRunner->run('dig', 'example.com', $argv);
    if (($r['reason'] ?? null) === ToolRunner::REASON_START_FAILED) {
        skip('dig (default resolver)', 'dig failed to start');
    } else {
        check(isToolResult($r) && ($r['reason'] ?? '') !== ToolRunner::REASON_TIMEOUT,
            'dig (default resolver) returns a bounded ToolResult',
            'result: ' . json_encode($r));
    }

    $argv2 = $cb->build('dig', ['target' => 'example.com', 'dns_server' => '9.9.9.9'])[0];
    $r2 = $netRunner->run('dig', 'example.com', $argv2);
    if (($r2['reason'] ?? null) === ToolRunner::REASON_START_FAILED) {
        skip('dig (custom server)', 'dig failed to start');
    } else {
        check(isToolResult($r2) && ($r2['reason'] ?? '') !== ToolRunner::REASON_TIMEOUT,
            'dig (custom server 9.9.9.9) returns a bounded ToolResult',
            'result: ' . json_encode($r2));
    }
}

// -- ping self-termination ----------------------------------------------------
if (!binAvailable(CommandBuilder::BIN_PING)) {
    skip('ping self-termination', 'ping binary not available');
} else {
    $argv = $cb->build('ping', ['target' => '127.0.0.1'])[0];
    $r = $netRunner->run('ping', '127.0.0.1', $argv);
    if (($r['reason'] ?? null) === ToolRunner::REASON_START_FAILED) {
        skip('ping self-termination', 'ping failed to start (likely needs CAP_NET_RAW)');
    } else {
        // Self-termination => did not hit the ToolRunner wall-clock timeout.
        check(isToolResult($r) && ($r['reason'] ?? '') !== ToolRunner::REASON_TIMEOUT,
            'ping self-terminates within the bound',
            'result: ' . json_encode($r));
    }
}

// -- traceroute self-termination ----------------------------------------------
if (!binAvailable(CommandBuilder::BIN_TRACEROUTE)) {
    skip('traceroute self-termination', 'traceroute binary not available');
} else {
    $argv = $cb->build('traceroute', ['target' => '127.0.0.1'])[0];
    $r = $netRunner->run('traceroute', '127.0.0.1', $argv);
    if (($r['reason'] ?? null) === ToolRunner::REASON_START_FAILED) {
        skip('traceroute self-termination', 'traceroute failed to start');
    } else {
        check(isToolResult($r) && ($r['reason'] ?? '') !== ToolRunner::REASON_TIMEOUT,
            'traceroute self-terminates within the bound',
            'result: ' . json_encode($r));
    }
}

// ===========================================================================
// SECTION 5 (BEST-EFFORT): RDAP + additional tools, graceful failure (Req 8.*)
// ===========================================================================
// Each tool runs one example. When the binary is missing we SKIP. When the
// binary runs but the upstream/network is unavailable, a well-formed ToolResult
// with exitError=true (graceful failure, Req 8.11) is an ACCEPTABLE outcome -
// we only FAIL on a malformed envelope or a runaway (timeout) process.
echo "\n[5] RDAP + additional tools - best-effort, graceful failure (Req 8.1-8.7, 8.11)\n";

/**
 * Run one additional-tool example and assert graceful behaviour.
 * Uses runMany for multi-command tools (e.g. nsmx), run otherwise.
 */
function bestEffortTool(CommandBuilder $cb, ToolRunner $runner, string $tool, array $params, string $requiredBin, string $label): void {
    if (!binAvailable($requiredBin)) {
        skip($label, "{$requiredBin} binary not available");
        return;
    }
    try {
        $argvList = $cb->build($tool, $params);
    } catch (\Throwable $e) {
        fail($label, 'CommandBuilder threw: ' . $e->getMessage());
        return;
    }
    $target = (string)($params['target'] ?? '');
    $r = (count($argvList) > 1)
        ? $runner->runMany($tool, $target, $argvList)
        : $runner->run($tool, $target, $argvList[0]);

    if (($r['reason'] ?? null) === ToolRunner::REASON_START_FAILED) {
        skip($label, 'utility failed to start');
        return;
    }
    // Genuine defect: malformed envelope or a process that blew past the bound.
    if (!isToolResult($r)) {
        fail($label, 'malformed ToolResult: ' . json_encode($r));
        return;
    }
    if (($r['reason'] ?? '') === ToolRunner::REASON_TIMEOUT) {
        fail($label, 'tool did not self-terminate within the bound');
        return;
    }
    // Success OR graceful failure (exitError with a reason / non-zero exit) both pass.
    pass($label . ' (graceful: exitError=' . var_export($r['exitError'], true) . ')');
}

$netRunner2 = new ToolRunner(15 /*timeoutSec*/, 262144);

// RDAP / WHOIS against a well-known domain (Req 6.1, 8.11 for graceful failure).
bestEffortTool($cb, $netRunner2, 'rdap', ['target' => 'example.com'], CommandBuilder::BIN_CURL, 'rdap example.com');
// reverse DNS (PTR) for a well-known IP (Req 8.1).
bestEffortTool($cb, $netRunner2, 'reverse_dns', ['target' => '8.8.8.8'], CommandBuilder::BIN_DIG, 'reverse_dns 8.8.8.8');
// NS/MX enumeration (Req 8.2) - multi-command, exercised via runMany.
bestEffortTool($cb, $netRunner2, 'nsmx', ['target' => 'example.com'], CommandBuilder::BIN_DIG, 'nsmx example.com');
// GeoIP (Req 8.3) - external HTTPS API.
bestEffortTool($cb, $netRunner2, 'geoip', ['target' => '8.8.8.8'], CommandBuilder::BIN_CURL, 'geoip 8.8.8.8');
// ASN (Req 8.4) - external HTTPS API.
bestEffortTool($cb, $netRunner2, 'asn', ['target' => '8.8.8.8'], CommandBuilder::BIN_CURL, 'asn 8.8.8.8');
// TLS certificate (Req 8.5) - openssl s_client.
bestEffortTool($cb, $netRunner2, 'tls_cert', ['target' => 'example.com'], CommandBuilder::BIN_OPENSSL, 'tls_cert example.com');
// Domain reputation / threat-intel (Req 8.6) - external HTTPS API.
bestEffortTool($cb, $netRunner2, 'reputation', ['target' => 'example.com'], CommandBuilder::BIN_CURL, 'reputation example.com');

// ===========================================================================
// Summary
// ===========================================================================
echo "\n" . str_repeat('=', 74) . "\n";
printf("Summary: %d PASS, %d SKIP, %d FAIL\n",
    $GLOBALS['__pass'], $GLOBALS['__skip'], $GLOBALS['__fail']);

if ($GLOBALS['__fail'] > 0) {
    echo "\nFailures:\n";
    foreach ($GLOBALS['__failures'] as $f) {
        echo "  - {$f}\n";
    }
    echo "RESULT: FAIL\n";
    exit(1);
}

echo "RESULT: PASS (SKIPs are acceptable in constrained environments)\n";
exit(0);
