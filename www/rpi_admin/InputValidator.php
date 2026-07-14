<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * InputValidator - Pure, side-effect-free validators for Research tool inputs.
 *
 * These validators are run before any network tool or command is executed so
 * that malformed input is rejected without invoking a system command or
 * database query. Every method is a pure function: it depends only on its
 * arguments, performs no I/O, and produces no side effects.
 *
 * @see .kiro/specs/research-tools/design.md ("InputValidator")
 * Requirements: 6.4, 6.5, 8.8, 8.9, 8.10, 9.4
 */
class InputValidator {

    /** @var int Maximum length of a domain name (RFC 1035). */
    const MAX_DOMAIN_LENGTH = 253;

    /** @var int Maximum length of a single DNS label (RFC 1035). */
    const MAX_LABEL_LENGTH = 63;

    /** @var int Maximum number of items accepted by the bulk-analysis tool. */
    const MAX_BULK_ITEMS = 100;

    /**
     * Validate a domain name against RFC 1035 hostname syntax.
     *
     * Rules:
     * - Total length must be between 1 and 253 characters (a single optional
     *   trailing dot is permitted and ignored for the length check).
     * - The name is a sequence of dot-separated labels.
     * - Each label is 1-63 characters, contains only ASCII letters, digits and
     *   hyphens, and does not start or end with a hyphen.
     * - The top-level (last) label must not be purely numeric, so that IPv4
     *   addresses are not classified as domains.
     *
     * @param string $s Candidate domain name.
     * @return bool True if $s is a valid RFC 1035 hostname, false otherwise.
     */
    public static function isValidDomain($s): bool {
        if (!is_string($s)) {
            return false;
        }

        // Reject the empty string and anything longer than the RFC limit.
        if ($s === '' || strlen($s) > self::MAX_DOMAIN_LENGTH) {
            return false;
        }

        // A single trailing dot denotes the root and is permitted; strip it.
        if (substr($s, -1) === '.') {
            $s = substr($s, 0, -1);
        }

        // After stripping the root dot the name must still be non-empty and
        // within the length bound.
        if ($s === '' || strlen($s) > self::MAX_DOMAIN_LENGTH) {
            return false;
        }

        $labels = explode('.', $s);

        foreach ($labels as $label) {
            $len = strlen($label);
            if ($len < 1 || $len > self::MAX_LABEL_LENGTH) {
                return false;
            }
            // Alphanumeric with internal hyphens only; no leading/trailing hyphen.
            if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?$/', $label)) {
                return false;
            }
        }

        // The top-level label must not be all digits (avoids treating an IPv4
        // address such as "1.2.3.4" as a valid domain).
        $tld = $labels[count($labels) - 1];
        if (preg_match('/^[0-9]+$/', $tld)) {
            return false;
        }

        return true;
    }

    /**
     * Validate an IPv4 or IPv6 address.
     *
     * @param string $s Candidate IP address.
     * @return bool True if $s is a valid IPv4 or IPv6 address, false otherwise.
     */
    public static function isValidIp($s): bool {
        if (!is_string($s)) {
            return false;
        }

        return filter_var($s, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that the input is either a valid domain name or a valid IP.
     *
     * @param string $s Candidate domain or IP address.
     * @return bool True if $s is a valid domain or IP address, false otherwise.
     */
    public static function isDomainOrIp($s): bool {
        return self::isValidIp($s) || self::isValidDomain($s);
    }

    /**
     * Validate a DNS server address supplied for the `dig` tool.
     *
     * A DNS server may be specified as an IP address (the common case) or as a
     * hostname, so this accepts either form.
     *
     * @param string $s Candidate DNS server address.
     * @return bool True if $s is a valid IP address or hostname, false otherwise.
     */
    public static function isValidDnsServer($s): bool {
        return self::isValidIp($s) || self::isValidDomain($s);
    }

    /**
     * Validate a bulk-analysis list.
     *
     * A list is accepted if and only if it contains at most 100 items and every
     * item is a valid domain or IP address.
     *
     * @param array $items Candidate list of targets.
     * @return bool True if the list is accepted, false otherwise.
     */
    public static function isValidBulkList($items): bool {
        if (!is_array($items)) {
            return false;
        }

        if (count($items) > self::MAX_BULK_ITEMS) {
            return false;
        }

        foreach ($items as $item) {
            if (!self::isDomainOrIp($item)) {
                return false;
            }
        }

        return true;
    }
}
