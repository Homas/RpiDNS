<?php
/**
 * Feature: research-tools, Property 11
 *
 * Property 11: Tool-input validators accept only well-formed inputs.
 *
 * "For any candidate input, isValidDomain / isValidIp / isDomainOrIp /
 *  isValidDnsServer SHALL return true if and only if the input matches the
 *  required format (domain <= 253 chars, valid IPv4/IPv6, or valid DNS-server
 *  IP/hostname)."
 *
 * Validates: Requirements 6.4, 6.5, 8.10
 *
 * This is a self-contained, standalone property-based test runnable with the
 * `php` CLI (no PHPUnit / composer required):
 *
 *     php www/rpi_admin/tests/InputValidatorPropertyTest.php
 *
 * (The bulk-list validator and its Property 12 test were withdrawn with the
 * bulk-analysis tool.)
 *
 * Testing approach
 * ----------------
 * The generators emit *tagged* candidates: each generated input carries the
 * validity that follows from the way it was constructed (a valid domain, a
 * valid IPv4/IPv6 address, or a deliberately malformed string). This
 * constructive specification is the independent reference oracle: the expected
 * result is derived from the definition of the input space, never from the
 * implementation under test.
 *
 * For every candidate the test asserts, for the IFF property:
 *   - isValidDomain(c)    === expectedDomain
 *   - isValidIp(c)        === expectedIp
 *   - isDomainOrIp(c)     === (expectedDomain || expectedIp)
 *   - isValidDnsServer(c) === (expectedDomain || expectedIp)
 *
 * It also asserts two structural invariants that must hold for *any* input,
 * independently of the tag:
 *   - isDomainOrIp(c)     === (isValidDomain(c) || isValidIp(c))
 *   - isValidDnsServer(c) === (isValidDomain(c) || isValidIp(c))
 */

require_once __DIR__ . '/../InputValidator.php';

// -----------------------------------------------------------------------------
// Reproducibility: seed the PRNG (override with SEED env var to replay a run).
// -----------------------------------------------------------------------------
$seed = getenv('SEED');
if ($seed === false || $seed === '') {
    $seed = random_int(0, PHP_INT_MAX);
} else {
    $seed = (int) $seed;
}
mt_srand($seed);

const MIN_ITERATIONS = 500; // well above the required minimum of 100

// -----------------------------------------------------------------------------
// Random primitive helpers
// -----------------------------------------------------------------------------

/** Return a random element of $arr. */
function pick(array $arr) {
    return $arr[mt_rand(0, count($arr) - 1)];
}

/** Random ASCII alphanumeric character. */
function randAlnumChar(): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return $chars[mt_rand(0, strlen($chars) - 1)];
}

/** Random ASCII letter. */
function randAlphaChar(): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return $chars[mt_rand(0, strlen($chars) - 1)];
}

/**
 * Build one well-formed RFC 1035 label of the requested length:
 * first and last characters are alphanumeric; interior characters may be a
 * hyphen. Guarantees no leading/trailing hyphen and only [A-Za-z0-9-].
 */
function genLabel(int $len): string {
    if ($len <= 0) {
        $len = 1;
    }
    if ($len === 1) {
        return randAlnumChar();
    }
    $label = randAlnumChar();
    for ($i = 1; $i < $len - 1; $i++) {
        // ~20% chance of an interior hyphen.
        $label .= (mt_rand(0, 4) === 0) ? '-' : randAlnumChar();
    }
    $label .= randAlnumChar();
    return $label;
}

// -----------------------------------------------------------------------------
// Candidate generators. Each returns [candidate, expectedDomain, expectedIp].
// -----------------------------------------------------------------------------

/** A syntactically valid domain (optionally with a trailing root dot). */
function genValidDomain(): array {
    $numLabels = mt_rand(1, 4);
    $labels = [];

    // Leading labels: random length 1..15, alnum/hyphen.
    for ($i = 0; $i < $numLabels - 1; $i++) {
        $labels[] = genLabel(mt_rand(1, 15));
    }
    // Top-level label: letters only, so it is never all-numeric.
    $tldLen = mt_rand(2, 6);
    $tld = '';
    for ($i = 0; $i < $tldLen; $i++) {
        $tld .= randAlphaChar();
    }
    $labels[] = $tld;

    $domain = implode('.', $labels);

    // Keep within the 253-char bound (regenerate rather than truncate).
    if (strlen($domain) > InputValidator::MAX_DOMAIN_LENGTH) {
        return genValidDomain();
    }

    // Occasionally append a trailing root dot; the validator must accept it.
    if (mt_rand(0, 4) === 0 && strlen($domain) < InputValidator::MAX_DOMAIN_LENGTH) {
        $domain .= '.';
    }

    return [$domain, true, false];
}

/** A canonical IPv4 address (no leading zeros). */
function genValidIpv4(): array {
    $octets = [];
    for ($i = 0; $i < 4; $i++) {
        $octets[] = (string) mt_rand(0, 255);
    }
    return [implode('.', $octets), false, true];
}

/** A valid IPv6 address (full 8-group form, or a well-known compressed form). */
function genValidIpv6(): array {
    if (mt_rand(0, 2) === 0) {
        $known = ['::1', '::', 'fe80::1', '2001:db8::ff00:42:8329', '::ffff:192.0.2.1'];
        return [pick($known), false, true];
    }
    $groups = [];
    for ($i = 0; $i < 8; $i++) {
        $groups[] = dechex(mt_rand(0, 0xffff));
    }
    return [implode(':', $groups), false, true];
}

/**
 * A deliberately malformed input that is neither a valid domain nor a valid IP.
 * Every branch here is invalid under both format definitions by construction.
 */
function genMalformed(): array {
    $kind = mt_rand(0, 12);
    switch ($kind) {
        case 0: // empty string
            $c = '';
            break;
        case 1: // label longer than 63 characters
            $c = str_repeat('a', 64) . '.com';
            break;
        case 2: // whole name longer than 253 characters
            $c = str_repeat('a.', 130) . 'com'; // ~263 chars
            break;
        case 3: // leading hyphen in a label
            $c = '-' . genLabel(mt_rand(1, 8)) . '.com';
            break;
        case 4: // trailing hyphen in a label
            $c = genLabel(mt_rand(1, 8)) . '-.com';
            break;
        case 5: // underscore (not permitted in RFC 1035 hostnames)
            $c = 'ab_cd.com';
            break;
        case 6: // empty interior label (double dot)
            $c = 'ab..com';
            break;
        case 7: // all-numeric top-level label
            $c = 'example.' . mt_rand(0, 9999);
            break;
        case 8: // single all-numeric label (not an IP either)
            $c = (string) mt_rand(100000, 999999);
            break;
        case 9: // shell metacharacters / injection attempts
            $c = pick([
                'a;b.com', 'a|b.com', 'a&b.com', '$(id).com', '`whoami`.com',
                'a b.com', "a\nb.com", "a\tb.com", "a'b.com", 'a"b.com',
                '<script>', '../etc/passwd', 'a,b.com', 'a$b.com', 'a{b}.com',
                ';', '| ', '&&', "\n", 'http://example.com',
            ]);
            break;
        case 10: // leading whitespace on an otherwise valid domain
            $c = ' example.com';
            break;
        case 11: // malformed IPv4 (octet out of range) -> neither IP nor domain
            $c = '256.100.100.999';
            break;
        default: // trailing whitespace on an otherwise valid domain
            $c = "example.com ";
            break;
    }
    return [$c, false, false];
}

// -----------------------------------------------------------------------------
// Test driver
// -----------------------------------------------------------------------------

$generators = [
    'valid_domain' => 'genValidDomain',
    'valid_ipv4'   => 'genValidIpv4',
    'valid_ipv6'   => 'genValidIpv6',
    'malformed'    => 'genMalformed',
];
$generatorNames = array_keys($generators);

$failures = [];
$counts = ['valid_domain' => 0, 'valid_ipv4' => 0, 'valid_ipv6' => 0, 'malformed' => 0];

/** Record a failing example and stop-detail. */
function fail(array &$failures, string $category, $candidate, string $message): void {
    $failures[] = [
        'category'  => $category,
        'candidate' => $candidate,
        'message'   => $message,
    ];
}

for ($i = 0; $i < MIN_ITERATIONS; $i++) {
    $name = $generatorNames[$i % count($generatorNames)]; // even coverage
    // Add extra randomness so ordering is not perfectly cyclic.
    if (mt_rand(0, 1) === 1) {
        $name = pick($generatorNames);
    }
    $counts[$name]++;

    [$candidate, $expDomain, $expIp] = $generators[$name]();

    $expDomainOrIp = $expDomain || $expIp;

    $gotDomain    = InputValidator::isValidDomain($candidate);
    $gotIp        = InputValidator::isValidIp($candidate);
    $gotEither    = InputValidator::isDomainOrIp($candidate);
    $gotDnsServer = InputValidator::isValidDnsServer($candidate);

    // --- IFF property against the constructive oracle ---
    if ($gotDomain !== $expDomain) {
        fail($failures, $name, $candidate,
            sprintf('isValidDomain returned %s, expected %s',
                var_export($gotDomain, true), var_export($expDomain, true)));
        break;
    }
    if ($gotIp !== $expIp) {
        fail($failures, $name, $candidate,
            sprintf('isValidIp returned %s, expected %s',
                var_export($gotIp, true), var_export($expIp, true)));
        break;
    }
    if ($gotEither !== $expDomainOrIp) {
        fail($failures, $name, $candidate,
            sprintf('isDomainOrIp returned %s, expected %s',
                var_export($gotEither, true), var_export($expDomainOrIp, true)));
        break;
    }
    if ($gotDnsServer !== $expDomainOrIp) {
        fail($failures, $name, $candidate,
            sprintf('isValidDnsServer returned %s, expected %s',
                var_export($gotDnsServer, true), var_export($expDomainOrIp, true)));
        break;
    }

    // --- Structural invariants (must hold for any input) ---
    if ($gotEither !== ($gotDomain || $gotIp)) {
        fail($failures, $name, $candidate,
            'isDomainOrIp is not equal to (isValidDomain || isValidIp)');
        break;
    }
    if ($gotDnsServer !== ($gotDomain || $gotIp)) {
        fail($failures, $name, $candidate,
            'isValidDnsServer is not equal to (isValidDomain || isValidIp)');
        break;
    }
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------

echo "Feature: research-tools, Property 11 - Tool-input validators\n";
echo "Seed: {$seed} (set SEED=$seed to reproduce)\n";
echo sprintf(
    "Generated: valid_domain=%d, valid_ipv4=%d, valid_ipv6=%d, malformed=%d (total=%d)\n",
    $counts['valid_domain'], $counts['valid_ipv4'], $counts['valid_ipv6'],
    $counts['malformed'], MIN_ITERATIONS
);

if (empty($failures)) {
    echo "PASS: all " . MIN_ITERATIONS . " candidates satisfied Property 11.\n";
    exit(0);
}

echo "FAIL: Property 11 violated.\n";
foreach ($failures as $f) {
    echo "  category : {$f['category']}\n";
    echo "  candidate: " . var_export($f['candidate'], true) . "\n";
    echo "  detail   : {$f['message']}\n";
}
exit(1);
