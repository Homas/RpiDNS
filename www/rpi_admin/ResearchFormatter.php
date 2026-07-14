<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ResearchFormatter - Renders Research network-tool output for display.
 *
 * Several enrichment tools call external HTTP APIs that return JSON (geoip,
 * asn, rdap, reputation). Raw JSON is hard to read in the UI, so this component
 * turns it into a human-readable form:
 *   - geoip / asn: a concise key/value summary of the useful fields.
 *   - rdap / reputation / any other JSON: pretty-printed JSON.
 *
 * It is deliberately conservative: output that is NOT valid JSON (plain-text
 * tool output such as dig/ping/traceroute, or a curl error like
 * "curl: (6) Could not resolve host: ...") is returned unchanged, so error
 * messages and text utilities are never mangled.
 *
 * Pure and side-effect free.
 *
 * @see .kiro/specs/research-tools/design.md ("ToolResult")
 * Requirements: 8.3, 8.4, 8.6 (readable presentation of tool output)
 */
class ResearchFormatter {

    /** Tools whose upstream returns JSON and therefore benefit from formatting. */
    const JSON_TOOLS = ['geoip', 'asn', 'rdap', 'reputation'];

    /**
     * Format a tool's raw output for display.
     *
     * @param string $tool   The tool name (e.g. 'geoip', 'asn', 'dig').
     * @param string $output The raw utility output captured by ToolRunner.
     * @return string A human-readable rendering, or the original output when it
     *                is not JSON / not a JSON-producing tool.
     */
    public static function format($tool, $output) {
        if (!is_string($output) || $output === '') {
            return (string)$output;
        }

        // Only attempt to reformat tools that are expected to emit JSON.
        if (!in_array($tool, self::JSON_TOOLS, true)) {
            return $output;
        }

        $decoded = json_decode($output, true);
        // Not valid JSON (e.g. a curl connection error, or truncated body):
        // leave it exactly as-is so the message is preserved.
        if ($decoded === null && trim($output) !== 'null') {
            return $output;
        }

        switch ($tool) {
            case 'geoip':
                return self::formatGeoip($decoded, $output);
            case 'asn':
                return self::formatAsn($decoded, $output);
            default:
                // rdap, reputation, and any other JSON: pretty-print.
                return self::prettyJson($decoded, $output);
        }
    }

    /**
     * Render an ipapi.co GeoIP response as a key/value summary.
     *
     * @param mixed  $d   Decoded JSON.
     * @param string $raw Original output (fallback).
     * @return string
     */
    private static function formatGeoip($d, $raw) {
        if (!is_array($d)) {
            return $raw;
        }

        // ipapi.co reports failures as {"error":true,"reason":"..."}.
        if (!empty($d['error'])) {
            $reason = isset($d['reason']) ? $d['reason'] : 'lookup failed';
            return 'GeoIP lookup failed: ' . $reason;
        }

        $country = self::val($d, 'country_name');
        $cc = self::val($d, 'country_code');
        if ($cc === '') { $cc = self::val($d, 'country'); }
        $countryLine = $country;
        if ($country !== '' && $cc !== '') { $countryLine = $country . ' (' . $cc . ')'; }
        elseif ($country === '' && $cc !== '') { $countryLine = $cc; }

        $lat = self::val($d, 'latitude');
        $lon = self::val($d, 'longitude');
        $location = ($lat !== '' && $lon !== '') ? ($lat . ', ' . $lon) : '';

        $asn = self::val($d, 'asn');
        $org = self::val($d, 'org');
        $asnOrg = trim($asn . ' ' . $org);

        $lines = self::kv([
            'IP'        => self::val($d, 'ip'),
            'City'      => self::val($d, 'city'),
            'Region'    => self::val($d, 'region'),
            'Country'   => $countryLine,
            'Postal'    => self::val($d, 'postal'),
            'Location'  => $location,
            'Timezone'  => self::val($d, 'timezone'),
            'ASN / Org' => $asnOrg,
        ]);

        // If none of the expected fields were present, fall back to pretty JSON.
        return $lines !== '' ? $lines : self::prettyJson($d, $raw);
    }

    /**
     * Render a RIPEstat prefix-overview response as a readable ASN summary.
     *
     * @param mixed  $d   Decoded JSON.
     * @param string $raw Original output (fallback).
     * @return string
     */
    private static function formatAsn($d, $raw) {
        if (!is_array($d) || !isset($d['data']) || !is_array($d['data'])) {
            return self::prettyJson($d, $raw);
        }
        $data = $d['data'];

        $out = [];
        $resource = self::val($data, 'resource');
        if ($resource !== '') {
            $out[] = self::pad('Resource') . $resource;
        }

        $asns = (isset($data['asns']) && is_array($data['asns'])) ? $data['asns'] : [];
        if (count($asns) === 0) {
            $out[] = 'No ASN data available for this address.';
        } else {
            foreach ($asns as $a) {
                if (!is_array($a)) { continue; }
                $asn = self::val($a, 'asn');
                $holder = self::val($a, 'holder');
                $line = 'AS' . $asn;
                if ($holder !== '') { $line .= '  (' . $holder . ')'; }
                $out[] = self::pad('ASN') . $line;
            }
        }

        // Surface any RIPEstat notices (e.g. "aligned to less-specific prefix").
        if (isset($d['messages']) && is_array($d['messages'])) {
            foreach ($d['messages'] as $msg) {
                if (is_array($msg) && isset($msg[1]) && is_string($msg[1])) {
                    $out[] = self::pad('Note') . $msg[1];
                }
            }
        }

        return count($out) > 0 ? implode("\n", $out) : self::prettyJson($d, $raw);
    }

    /**
     * Pretty-print decoded JSON, falling back to the raw string on failure.
     *
     * @param mixed  $decoded
     * @param string $raw
     * @return string
     */
    private static function prettyJson($decoded, $raw) {
        $pretty = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return ($pretty !== false) ? $pretty : $raw;
    }

    /**
     * Build an aligned "Label: value" block, skipping empty values.
     *
     * @param array $pairs Ordered label => value map.
     * @return string
     */
    private static function kv(array $pairs) {
        $lines = [];
        foreach ($pairs as $label => $value) {
            if ($value === '' || $value === null) { continue; }
            $lines[] = self::pad($label) . $value;
        }
        return implode("\n", $lines);
    }

    /**
     * Right-pad a label to a fixed column so values line up.
     *
     * @param string $label
     * @return string
     */
    private static function pad($label) {
        return str_pad($label . ':', 12, ' ', STR_PAD_RIGHT);
    }

    /**
     * Safely read a scalar value from a decoded array as a trimmed string.
     *
     * @param array  $arr
     * @param string $key
     * @return string
     */
    private static function val($arr, $key) {
        if (!is_array($arr) || !array_key_exists($key, $arr)) { return ''; }
        $v = $arr[$key];
        if (is_bool($v)) { return $v ? 'true' : 'false'; }
        if (is_scalar($v)) { return trim((string)$v); }
        return '';
    }
}
