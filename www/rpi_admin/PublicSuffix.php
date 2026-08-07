<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * PublicSuffix - Reduces a hostname to the domain that is actually registered.
 *
 * RDAP/WHOIS is published per registered domain, not per hostname: a query for
 * test.example.com is not a thing a registry answers, while example.com is. The
 * boundary cannot be found by counting labels, because many suffixes are
 * multi-label and behave like a TLD - bbc.co.uk is a registration, co.uk is not.
 * The Public Suffix List is the authority on where that boundary sits, so this
 * component implements its matching algorithm against the bundled list.
 *
 * The bundled data (data/public_suffix_list.dat) carries the ICANN section only.
 * The PRIVATE section describes namespaces delegated below a registration
 * (github.io, blogspot.com, s3.amazonaws.com); honoring it would make
 * someproject.github.io "registrable" and send RDAP a name no registry knows,
 * when the registered domain is github.io. Rules are pre-converted to punycode
 * by scripts/update_psl.php, so no intl extension is needed here.
 *
 * Pure and side-effect free apart from reading (and memoizing) the data file.
 *
 * @see .kiro/specs/research-tools/design.md ("CommandBuilder")
 * Requirements: 8.2 (RDAP/WHOIS lookup)
 */
class PublicSuffix {

    /** Bundled list, resolved relative to this file so it works in-place and in the image. */
    const DATA_FILE = __DIR__ . '/data/public_suffix_list.dat';

    /** @var array{exact: array<string,bool>, wildcard: array<string,bool>, exception: array<string,bool>}|null */
    private static $rules = null;

    /**
     * The registrable domain (public suffix plus one label) of a hostname.
     *
     * @param string $host Hostname, e.g. 'test.example.com'.
     * @return string|null The registrable domain, or null when there is none -
     *         the host IS a public suffix ('co.uk'), is a single label, or is not
     *         a hostname at all (an IP address).
     */
    public static function registrableDomain($host) {
        $labels = self::normalize($host);
        if ($labels === null) {
            return null;
        }

        $count = count($labels);
        $suffixLabels = self::suffixLength($labels);

        // Nothing to the left of the public suffix: 'com', 'co.uk'.
        if ($suffixLabels >= $count) {
            return null;
        }

        return implode('.', array_slice($labels, $count - $suffixLabels - 1));
    }

    /**
     * The public suffix of a hostname, e.g. 'co.uk' for 'bbc.co.uk'.
     *
     * @param string $host Hostname.
     * @return string|null The public suffix, or null when the host is unusable.
     */
    public static function publicSuffix($host) {
        $labels = self::normalize($host);
        if ($labels === null) {
            return null;
        }
        $count = count($labels);

        return implode('.', array_slice($labels, $count - self::suffixLength($labels)));
    }

    /**
     * Number of labels forming the public suffix, per the PSL algorithm:
     *  - an exception rule (!) wins outright, and its own leftmost label is not
     *    part of the suffix;
     *  - otherwise the prevailing rule is the matching rule with the most labels,
     *    where a '*' label matches any single label;
     *  - with no match at all the implicit rule is '*', i.e. the last label.
     *
     * @param array<int, string> $labels Normalized labels.
     * @return int Label count of the public suffix (at least 1).
     */
    private static function suffixLength(array $labels) {
        $rules = self::rules();
        $count = count($labels);

        // Exception rules take priority over every other rule.
        for ($i = 0; $i < $count; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            if (isset($rules['exception'][$candidate])) {
                return $count - $i - 1;
            }
        }

        $best = 0;
        for ($i = 0; $i < $count; $i++) {
            $length = $count - $i;
            $candidate = implode('.', array_slice($labels, $i));
            if (isset($rules['exact'][$candidate])) {
                if ($length > $best) { $best = $length; }
                continue;
            }
            // A wildcard rule '*.x.y' matches any single label above 'x.y'.
            if ($length >= 2) {
                $wildcard = '*.' . implode('.', array_slice($labels, $i + 1));
                if (isset($rules['wildcard'][$wildcard]) && $length > $best) {
                    $best = $length;
                }
            }
        }

        // No rule matched: the implicit '*' rule makes the last label the suffix,
        // which is what gives example.invalidtld a sensible answer too.
        return ($best > 0) ? $best : 1;
    }

    /**
     * Lower-case label list for a hostname, or null when the value is not a
     * multi-label hostname (an address, a single label, or malformed).
     *
     * @param string $host
     * @return array<int, string>|null
     */
    private static function normalize($host) {
        $host = strtolower(trim((string)$host));
        // A trailing root dot is legal in a FQDN but is not a label.
        $host = rtrim($host, '.');
        if ($host === '' || strpos($host, '.') === false) {
            return null;
        }
        // Addresses have no public suffix; callers pass targets through verbatim.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $host);
        foreach ($labels as $label) {
            if ($label === '' || !preg_match('/^[a-z0-9-]+$/', $label)) {
                return null;
            }
        }

        return $labels;
    }

    /**
     * Load and memoize the rule sets. A missing or unreadable data file yields
     * empty sets, which makes every lookup fall back to the implicit '*' rule -
     * i.e. "last label is the suffix". That is wrong for multi-label suffixes but
     * never fatal, so a packaging mistake degrades the RDAP target instead of
     * breaking the tool.
     *
     * @return array{exact: array<string,bool>, wildcard: array<string,bool>, exception: array<string,bool>}
     */
    private static function rules() {
        if (self::$rules !== null) {
            return self::$rules;
        }

        self::$rules = array('exact' => array(), 'wildcard' => array(), 'exception' => array());

        $lines = @file(self::DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return self::$rules;
        }

        foreach ($lines as $line) {
            $rule = trim($line);
            if ($rule === '' || strpos($rule, '//') === 0) {
                continue;
            }
            if ($rule[0] === '!') {
                self::$rules['exception'][substr($rule, 1)] = true;
            } elseif (strpos($rule, '*') !== false) {
                self::$rules['wildcard'][$rule] = true;
            } else {
                self::$rules['exact'][$rule] = true;
            }
        }

        return self::$rules;
    }
}
