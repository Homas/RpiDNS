<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ResearchFormatter - Renders Research network-tool output for display.
 *
 * Several enrichment tools call external HTTP APIs that return JSON (geoip,
 * asn, rdap, reputation). Raw JSON is hard to read in the UI, so this component
 * turns each of them into a human-readable summary:
 *   - geoip / asn: a concise key/value summary of the useful fields.
 *   - rdap: registration data (RFC 9083) for a domain or IP network - name,
 *     status, registrar/organization, abuse contact, key dates, nameservers.
 *   - reputation: an AlienVault OTX indicator report - pulse count, the pulses
 *     themselves, and the aggregated adversary/malware/industry context.
 *   - any other JSON: pretty-printed JSON.
 *
 * `render()` returns the summary together with the pretty-printed JSON it was
 * derived from, so the UI can offer a summary/JSON toggle without a second
 * request. The JSON form is omitted when it would be identical to the summary.
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
     * Tools whose output is dig text. Their readable form is the record list;
     * the raw view keeps dig's full output (header, flags, authority section)
     * for when the details of the response matter.
     */
    const DNS_TOOLS = ['dig', 'nsmx', 'reverse_dns'];

    /**
     * Maximum size of the JSON view handed back to the client. The summary is
     * always small, but a pretty-printed OTX report with hundreds of pulses is
     * not, and it travels in the same response.
     */
    const MAX_RAW_BYTES = 262144; // 256 KiB

    /** Maximum number of list entries (pulses, nameservers, ...) rendered. */
    const MAX_LIST_ITEMS = 15;

    /** Widest label column used by the "Label: value" blocks. */
    const MAX_LABEL_COL = 16;

    /**
     * Format a tool's raw output for display.
     *
     * @param string $tool   The tool name (e.g. 'geoip', 'asn', 'dig').
     * @param string $output The raw utility output captured by ToolRunner.
     * @return string A human-readable rendering, or the original output when it
     *                is not JSON / not a JSON-producing tool.
     */
    public static function format($tool, $output) {
        $rendered = self::render($tool, $output);
        return $rendered['output'];
    }

    /**
     * Format a tool's raw output for display AND provide the JSON it came from.
     *
     * @param string $tool   The tool name (e.g. 'rdap', 'reputation', 'dig').
     * @param string $output The raw utility output captured by ToolRunner.
     * @return array{output: string, raw: string|null} `output` is what to show
     *         by default; `raw` is the pretty-printed JSON behind it, or null
     *         when there is no separate JSON view to offer.
     */
    public static function render($tool, $output) {
        $result = array('output' => is_string($output) ? $output : (string)$output, 'raw' => null);

        if (!is_string($output) || $output === '') {
            return $result;
        }

        // dig-based tools: the parsed record list becomes the default view and
        // dig's own output is kept as the raw view.
        if (in_array($tool, self::DNS_TOOLS, true)) {
            $records = self::formatDns($output);
            if ($records !== null && $records !== $output) {
                $result['output'] = $records;
                $result['raw'] = self::capRaw($output);
            }
            return $result;
        }

        // Only attempt to reformat tools that are expected to emit JSON.
        if (!in_array($tool, self::JSON_TOOLS, true)) {
            return $result;
        }

        $decoded = json_decode($output, true);
        // Not valid JSON (e.g. a curl connection error, or truncated body):
        // leave it exactly as-is so the message is preserved.
        if ($decoded === null && trim($output) !== 'null') {
            return $result;
        }

        $summary = self::summarize($tool, $decoded, $output);
        $pretty = self::prettyJson($decoded, $output);

        $result['output'] = $summary;
        // Only offer a JSON view when it differs from what is already shown,
        // so tools that fall back to pretty JSON get no pointless toggle.
        if ($summary !== $pretty) {
            $result['raw'] = self::capRaw($pretty);
        }

        return $result;
    }

    /**
     * Bound the raw view: it travels in the same response as the summary.
     *
     * @param string $raw
     * @return string
     */
    private static function capRaw($raw) {
        return (strlen($raw) > self::MAX_RAW_BYTES)
            ? substr($raw, 0, self::MAX_RAW_BYTES) . "\n\n... truncated for display."
            : $raw;
    }

    /**
     * Dispatch to the per-tool summary renderer.
     *
     * @param string $tool
     * @param mixed  $decoded Decoded JSON.
     * @param string $raw     Original output (fallback).
     * @return string
     */
    private static function summarize($tool, $decoded, $raw) {
        switch ($tool) {
            case 'geoip':
                return self::formatGeoip($decoded, $raw);
            case 'asn':
                return self::formatAsn($decoded, $raw);
            case 'rdap':
                return self::formatRdap($decoded, $raw);
            case 'reputation':
                return self::formatReputation($decoded, $raw);
            default:
                return self::prettyJson($decoded, $raw);
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
     * Render dig output as a plain record list.
     *
     * Handles output from one dig or several concatenated (the dig tool queries
     * A/AAAA/HTTPS/TXT, NS/MX runs two queries), and both verbose output and the
     * `+noall +answer` form used by the reverse lookup.
     *
     * Every answer record is listed in the order dig returned it, so a CNAME
     * chain reads top to bottom. Types that were asked for and answered nothing
     * are named explicitly, since "no AAAA" is itself a finding, and a response
     * code other than NOERROR is reported rather than looking like an empty
     * answer.
     *
     * @param string $output Raw dig output.
     * @return string|null The record list, or null when the text holds no dig
     *         response at all (an error message, or a tool that failed to start).
     */
    private static function formatDns($output) {
        $sawDig = false;
        $lines = array();
        $empty = array();

        // Per query, not per output: the tools issue several digs, and a status or
        // an empty answer belongs to the one query it came from. Reporting them
        // globally would put "NXDOMAIN" under a result that plainly resolved.
        foreach (self::digBlocks($output) as $block) {
            $status = self::digStatus($block);
            $answers = self::digBlockAnswers($block);
            $hasQuestion = strpos($block, ';; QUESTION SECTION:') !== false;

            // A fragment counts as a query only with a response code, a question,
            // or an answer. This skips dig's leading banner fragment, which would
            // otherwise be read as a second, answerless query.
            if ($status === null && !$hasQuestion && count($answers) === 0) {
                continue;
            }
            $sawDig = true;
            $type = self::digQuestionType($block);

            foreach ($answers as $record) {
                $lines[] = str_pad(self::dnsTypeName($record['type']), 8, ' ', STR_PAD_RIGHT)
                    . str_pad($record['ttl'], 7, ' ', STR_PAD_RIGHT)
                    . $record['value'];
            }

            if (count($answers) === 0) {
                // Anything other than NOERROR is worth naming: NXDOMAIN means the
                // name does not exist, SERVFAIL/REFUSED point at the resolver, and
                // an RPZ-blocked name commonly shows up here as NXDOMAIN.
                $label = ($type !== null) ? self::dnsTypeName($type) : 'query';
                if ($status !== null && $status !== 'NOERROR') {
                    $label .= ' (' . $status . ')';
                }
                if (!in_array($label, $empty, true)) { $empty[] = $label; }
            }
        }

        // Nothing that looks like a dig response: let the caller pass the original
        // text through untouched (a failure to start, a curl-style error).
        if (!$sawDig) {
            return null;
        }

        $out = implode("\n", $lines);
        if (count($empty) > 0) {
            $note = (count($lines) > 0 ? 'No records: ' : 'No records returned for: ')
                . implode(', ', $empty);
            $out .= (($out !== '') ? "\n\n" : '') . $note;
        }

        return ($out !== '') ? $out : null;
    }

    /**
     * Split concatenated dig output into one block per invocation.
     *
     * dig opens every run with its own `; <<>> DiG ...` banner, which is what the
     * multi-query tools (dig, nsmx) are separated on. The response header is also
     * treated as a boundary so output with the banner suppressed still splits per
     * query; that yields a leading banner-only fragment for verbose output, which
     * the caller's gate discards. Output with neither marker is one block, which
     * is the `+noall +answer` case.
     *
     * @param string $output
     * @return array<int, string>
     */
    private static function digBlocks($output) {
        $blocks = preg_split('/(?=^; <<>> DiG )|(?=^;; ->>HEADER<<-)/m', $output);
        if ($blocks === false || count($blocks) === 0) {
            return array($output);
        }
        // A leading empty fragment appears when the text starts with the banner.
        $blocks = array_values(array_filter($blocks, function ($b) { return trim($b) !== ''; }));

        return (count($blocks) > 0) ? $blocks : array($output);
    }

    /**
     * Answer records of a single dig block.
     *
     * Verbose output is read from its ANSWER section only, so authority and
     * additional records are never mistaken for answers. Without that marker the
     * block came from `+noall +answer` (the reverse lookup), where every
     * non-comment line already IS an answer record.
     *
     * @param string $block
     * @return array<int, array{name: string, ttl: string, type: string, value: string}>
     */
    private static function digBlockAnswers($block) {
        $source = null;
        if (preg_match('/;; ANSWER SECTION:\s*\n(.*?)(?:\n\s*\n|$)/s', $block, $m)) {
            $source = $m[1];
        } elseif (strpos($block, ';; QUESTION SECTION:') === false) {
            // No sections at all: `+noall +answer` output, comments and records
            // mixed. The record pattern below is what separates them.
            $source = $block;
        }
        if ($source === null) {
            return array();
        }

        $records = array();
        foreach (preg_split('/\r\n|\r|\n/', $source) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';') {
                continue;
            }
            // owner TTL [class] TYPE value...
            if (!preg_match('/^(\S+)\s+(\d+)\s+(?:IN|CH|HS)\s+([A-Z0-9-]+)\s+(.+)$/i', $line, $m)) {
                continue;
            }
            $records[] = array(
                'name'  => $m[1],
                'ttl'   => $m[2],
                'type'  => strtoupper($m[3]),
                'value' => trim($m[4]),
            );
        }

        return $records;
    }

    /**
     * The record type a dig block asked for, from its QUESTION section or, when
     * that is suppressed, from the banner echoing the command line.
     *
     * @param string $block
     * @return string|null
     */
    private static function digQuestionType($block) {
        if (preg_match('/;; QUESTION SECTION:\s*\n;\S+\s+(?:IN|CH|HS)\s+([A-Z0-9-]+)/i', $block, $m)) {
            return strtoupper($m[1]);
        }
        // `dig -x <ip>` asks for PTR without naming the type anywhere else.
        if (preg_match('/^; <<>> DiG [^\n]*\s-x\s/m', $block)) {
            return 'PTR';
        }
        return null;
    }

    /**
     * The response code of a dig block.
     *
     * @param string $block
     * @return string|null
     */
    private static function digStatus($block) {
        return preg_match('/status:\s*([A-Z]+)/', $block, $m) ? $m[1] : null;
    }

    /**
     * Display name for a record type. The HTTPS RR is queried as TYPE65 for
     * compatibility with older dig builds (see CommandBuilder::DIG_DEFAULT_TYPES),
     * so the numeric form is mapped back for display; a dig that knows the type
     * already prints the mnemonic itself.
     *
     * @param string $type
     * @return string
     */
    private static function dnsTypeName($type) {
        return ($type === 'TYPE65') ? 'HTTPS' : $type;
    }

    /**
     * Render an RDAP response (RFC 9083) as a readable registration summary.
     *
     * Handles both object classes the tool can return: `domain` (registrar,
     * registration dates, nameservers, DNSSEC) and `ip network` (allocation
     * range, holder, country). RDAP error objects are reported as one line.
     *
     * @param mixed  $d   Decoded JSON.
     * @param string $raw Original output (fallback).
     * @return string
     */
    private static function formatRdap($d, $raw) {
        if (!is_array($d)) {
            return $raw;
        }

        // RDAP error response: {"errorCode":404,"title":"...","description":[...]}
        if (isset($d['errorCode'])) {
            $line = 'RDAP lookup failed (' . self::val($d, 'errorCode') . ')';
            $title = self::val($d, 'title');
            if ($title !== '') { $line .= ': ' . $title; }
            $desc = self::firstString($d, 'description');
            if ($desc !== '') { $line .= "\n" . $desc; }
            return $line;
        }

        $class = self::val($d, 'objectClassName');
        $out = array();

        if ($class === 'ip network') {
            $range = trim(self::val($d, 'startAddress') . ' - ' . self::val($d, 'endAddress'), ' -');
            $out[] = self::kv(array(
                'Object'  => 'IP network',
                'Range'   => $range,
                'CIDR'    => self::rdapCidrs($d),
                'Name'    => self::val($d, 'name'),
                'Type'    => self::val($d, 'type'),
                'Country' => self::val($d, 'country'),
                'Handle'  => self::val($d, 'handle'),
                'Parent'  => self::val($d, 'parentHandle'),
                'Status'  => self::joinList($d, 'status'),
            ));
        } else {
            $name = self::val($d, 'unicodeName');
            if ($name === '') { $name = self::val($d, 'ldhName'); }
            $dnssec = '';
            if (isset($d['secureDNS']) && is_array($d['secureDNS'])) {
                $signed = self::val($d['secureDNS'], 'delegationSigned');
                if ($signed !== '') { $dnssec = ($signed === 'true') ? 'signed' : 'unsigned'; }
            }
            $out[] = self::kv(array(
                'Object' => ($class !== '' ? $class : 'domain'),
                'Name'   => $name,
                'Handle' => self::val($d, 'handle'),
                'Status' => self::joinList($d, 'status'),
                'DNSSEC' => $dnssec,
            ));
        }

        // Registrar / holder and the abuse contact, wherever they are nested.
        $roles = array(
            'Registrar'  => 'registrar',
            'Registrant' => 'registrant',
            'Org'        => 'administrative',
        );
        $contacts = array();
        foreach ($roles as $label => $role) {
            $entity = self::findEntityByRole($d, $role);
            if ($entity === null) { continue; }
            $who = self::vcard($entity, 'org');
            if ($who === '') { $who = self::vcard($entity, 'fn'); }
            if ($who === '') { continue; }
            // One entity commonly holds several roles (registrant AND
            // administrative), so the same name would be printed twice.
            if (in_array($who, $contacts, true)) { continue; }
            $contacts[$label] = $who;
        }
        $abuse = self::findEntityByRole($d, 'abuse');
        if ($abuse !== null) {
            $email = self::vcard($abuse, 'email');
            if ($email !== '') { $contacts['Abuse'] = $email; }
        }
        $contactBlock = self::kv($contacts);
        if ($contactBlock !== '') { $out[] = $contactBlock; }

        // Key lifecycle dates, in RDAP's own wording.
        $events = array();
        if (isset($d['events']) && is_array($d['events'])) {
            foreach ($d['events'] as $ev) {
                if (!is_array($ev)) { continue; }
                $action = self::val($ev, 'eventAction');
                $date = self::val($ev, 'eventDate');
                if ($action === '' || $date === '') { continue; }
                $events[ucfirst($action)] = $date;
            }
        }
        $eventBlock = self::kv($events);
        if ($eventBlock !== '') { $out[] = $eventBlock; }

        // Nameservers (domain objects only).
        if (isset($d['nameservers']) && is_array($d['nameservers'])) {
            $ns = array();
            foreach ($d['nameservers'] as $server) {
                if (!is_array($server)) { continue; }
                $host = self::val($server, 'ldhName');
                if ($host !== '') { $ns[] = strtolower($host); }
            }
            if (count($ns) > 0) {
                $out[] = self::listBlock('Nameservers', $ns);
            }
        }

        $port43 = self::val($d, 'port43');
        if ($port43 !== '') {
            $out[] = self::kv(array('WHOIS' => $port43));
        }

        $out = array_values(array_filter($out, function ($block) { return $block !== ''; }));
        return count($out) > 0 ? implode("\n\n", $out) : self::prettyJson($d, $raw);
    }

    /**
     * Render an AlienVault OTX indicator report as a readable summary.
     *
     * The headline is the pulse count: zero pulses means no OTX contributor has
     * flagged the indicator, which is the common case for benign domains and is
     * stated explicitly rather than left as an empty section.
     *
     * @param mixed  $d   Decoded JSON.
     * @param string $raw Original output (fallback).
     * @return string
     */
    private static function formatReputation($d, $raw) {
        if (!is_array($d)) {
            return $raw;
        }

        // OTX reports a failed/unsupported request as {"detail": "..."} or
        // {"error": "..."} with no pulse_info at all.
        if (!isset($d['pulse_info'])) {
            $detail = self::val($d, 'detail');
            if ($detail === '') { $detail = self::val($d, 'error'); }
            if ($detail !== '') {
                return 'Reputation lookup failed: ' . $detail;
            }
        }

        $info = (isset($d['pulse_info']) && is_array($d['pulse_info'])) ? $d['pulse_info'] : array();
        $pulses = (isset($info['pulses']) && is_array($info['pulses'])) ? $info['pulses'] : array();
        $count = self::val($info, 'count');
        if ($count === '') { $count = (string)count($pulses); }

        $out = array();
        $out[] = self::kv(array(
            'Indicator' => self::val($d, 'indicator'),
            'Type'      => self::val($d, 'type_title'),
            'Pulses'    => $count . ($count === '1' ? ' report' : ' reports'),
        ));

        // Whitelist / false-positive notes carry more weight than pulse count.
        $notes = array();
        if (isset($d['validation']) && is_array($d['validation'])) {
            foreach ($d['validation'] as $v) {
                if (!is_array($v)) { continue; }
                $note = self::val($v, 'message');
                if ($note === '') { $note = self::val($v, 'name'); }
                if ($note !== '') { $notes[] = $note; }
            }
        }
        if (isset($d['false_positive']) && is_array($d['false_positive']) && count($d['false_positive']) > 0) {
            $notes[] = 'Reported as a false positive by ' . count($d['false_positive']) . ' source(s).';
        }
        if (count($notes) > 0) {
            $out[] = self::listBlock('Whitelisted', $notes);
        }

        if ((int)$count === 0 && count($pulses) === 0) {
            $out[] = 'No threat-intelligence pulses reference this indicator.';
        } else {
            // The pulses themselves: what they are called, when they last moved,
            // and how they are tagged.
            $lines = array();
            $shown = 0;
            foreach ($pulses as $pulse) {
                if (!is_array($pulse)) { continue; }
                if ($shown >= self::MAX_LIST_ITEMS) { break; }
                $name = self::val($pulse, 'name');
                if ($name === '') { $name = self::val($pulse, 'id'); }
                $when = self::val($pulse, 'modified');
                if ($when === '') { $when = self::val($pulse, 'created'); }
                $line = $name;
                if ($when !== '') { $line .= '  [' . substr($when, 0, 10) . ']'; }
                $tags = self::joinList($pulse, 'tags');
                if ($tags !== '') { $line .= "\n" . str_repeat(' ', 4) . 'tags: ' . $tags; }
                $lines[] = $line;
                $shown++;
            }
            $remaining = count($pulses) - $shown;
            if ($remaining > 0) {
                $lines[] = '... and ' . $remaining . ' more (see JSON).';
            }
            if (count($lines) > 0) {
                $out[] = self::listBlock('Reported in', $lines);
            }
        }

        // Aggregated context OTX derives across all pulses.
        $related = array();
        if (isset($info['related']) && is_array($info['related'])) {
            foreach ($info['related'] as $source) {
                if (!is_array($source)) { continue; }
                foreach (array('adversary', 'malware_families', 'industries') as $key) {
                    if (!isset($source[$key]) || !is_array($source[$key])) { continue; }
                    foreach ($source[$key] as $value) {
                        // OTX returns these either as plain strings or as objects
                        // carrying a display name, depending on the field.
                        $name = self::nameOf($value);
                        if ($name !== '') { $related[$key][$name] = true; }
                    }
                }
            }
        }
        $context = array();
        $labels = array(
            'adversary'        => 'Adversaries',
            'malware_families' => 'Malware',
            'industries'       => 'Industries',
        );
        foreach ($labels as $key => $label) {
            if (isset($related[$key]) && count($related[$key]) > 0) {
                $context[$label] = implode(', ', array_keys($related[$key]));
            }
        }
        $contextBlock = self::kv($context);
        if ($contextBlock !== '') { $out[] = $contextBlock; }

        $out = array_values(array_filter($out, function ($block) { return $block !== ''; }));
        return count($out) > 0 ? implode("\n\n", $out) : self::prettyJson($d, $raw);
    }

    /**
     * Render CIDR prefixes from an RDAP ip network object's cidr0_cidrs.
     *
     * @param array $d Decoded ip network object.
     * @return string Comma-separated prefixes, or ''.
     */
    private static function rdapCidrs($d) {
        if (!isset($d['cidr0_cidrs']) || !is_array($d['cidr0_cidrs'])) { return ''; }
        $out = array();
        foreach ($d['cidr0_cidrs'] as $cidr) {
            if (!is_array($cidr)) { continue; }
            $prefix = self::val($cidr, 'v4prefix');
            if ($prefix === '') { $prefix = self::val($cidr, 'v6prefix'); }
            $length = self::val($cidr, 'length');
            if ($prefix !== '' && $length !== '') { $out[] = $prefix . '/' . $length; }
        }
        return implode(', ', $out);
    }

    /**
     * Find the first RDAP entity holding a given role, searching nested
     * entities as well: the abuse contact is normally nested inside the
     * registrar entity rather than listed at the top level.
     *
     * @param array  $node Decoded RDAP object (or entity).
     * @param string $role Role to look for, e.g. 'registrar' or 'abuse'.
     * @param int    $depth Recursion guard.
     * @return array|null The matching entity, or null.
     */
    private static function findEntityByRole($node, $role, $depth = 0) {
        if (!is_array($node) || $depth > 4) { return null; }
        if (!isset($node['entities']) || !is_array($node['entities'])) { return null; }

        foreach ($node['entities'] as $entity) {
            if (!is_array($entity)) { continue; }
            if (isset($entity['roles']) && is_array($entity['roles'])) {
                foreach ($entity['roles'] as $r) {
                    if (is_string($r) && stripos($r, $role) !== false) { return $entity; }
                }
            }
        }
        // Not at this level: descend into each entity's own entities.
        foreach ($node['entities'] as $entity) {
            $found = self::findEntityByRole($entity, $role, $depth + 1);
            if ($found !== null) { return $found; }
        }
        return null;
    }

    /**
     * Read a property from an RDAP entity's jCard (RFC 7095), whose shape is
     * ["vcard", [[name, params, type, value], ...]].
     *
     * @param array  $entity Decoded RDAP entity.
     * @param string $name   jCard property name, e.g. 'fn', 'org', 'email'.
     * @return string The first matching scalar value, or ''.
     */
    private static function vcard($entity, $name) {
        if (!is_array($entity) || !isset($entity['vcardArray']) || !is_array($entity['vcardArray'])) {
            return '';
        }
        $props = isset($entity['vcardArray'][1]) ? $entity['vcardArray'][1] : null;
        if (!is_array($props)) { return ''; }

        foreach ($props as $prop) {
            if (!is_array($prop) || count($prop) < 4) { continue; }
            if (!is_string($prop[0]) || strcasecmp($prop[0], $name) !== 0) { continue; }
            $value = $prop[3];
            // Structured values (e.g. org as ["Name","Unit"]) keep the first part.
            if (is_array($value)) {
                foreach ($value as $part) {
                    if (is_string($part) && trim($part) !== '') { return trim($part); }
                }
                continue;
            }
            if (is_scalar($value) && trim((string)$value) !== '') { return trim((string)$value); }
        }
        return '';
    }

    /**
     * Read a display name from a value that may be a plain string or an object
     * carrying one (OTX is inconsistent between fields).
     *
     * @param mixed $value
     * @return string
     */
    private static function nameOf($value) {
        if (is_string($value)) { return trim($value); }
        if (is_array($value)) {
            foreach (array('display_name', 'name', 'id') as $key) {
                $name = self::val($value, $key);
                if ($name !== '') { return $name; }
            }
        }
        return '';
    }

    /**
     * Join a list-valued field into a comma-separated string.
     *
     * @param array  $arr
     * @param string $key
     * @return string
     */
    private static function joinList($arr, $key) {
        if (!is_array($arr) || !isset($arr[$key]) || !is_array($arr[$key])) { return ''; }
        $out = array();
        foreach ($arr[$key] as $v) {
            if (is_scalar($v) && trim((string)$v) !== '') { $out[] = trim((string)$v); }
        }
        return implode(', ', $out);
    }

    /**
     * First string element of a list-valued field.
     *
     * @param array  $arr
     * @param string $key
     * @return string
     */
    private static function firstString($arr, $key) {
        if (!is_array($arr) || !isset($arr[$key])) { return ''; }
        if (is_string($arr[$key])) { return trim($arr[$key]); }
        if (is_array($arr[$key])) {
            foreach ($arr[$key] as $v) {
                if (is_string($v) && trim($v) !== '') { return trim($v); }
            }
        }
        return '';
    }

    /**
     * Render a labeled block of list entries, one per line and indented under
     * the label, capped at MAX_LIST_ITEMS.
     *
     * @param string $label
     * @param array  $items
     * @return string
     */
    private static function listBlock($label, array $items) {
        if (count($items) === 0) { return ''; }
        $lines = array($label . ':');
        $shown = 0;
        foreach ($items as $item) {
            if ($shown >= self::MAX_LIST_ITEMS) {
                $lines[] = '  ... and ' . (count($items) - $shown) . ' more (see JSON).';
                break;
            }
            $lines[] = '  ' . str_replace("\n", "\n  ", (string)$item);
            $shown++;
        }
        return implode("\n", $lines);
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
        // Column width is derived from the labels actually present in this
        // block, so long labels ("Last changed:") never collide with their
        // value while short blocks stay compact. It is capped so that one
        // unusually long label (RDAP's "Last update of RDAP database") cannot
        // push every value in the block far to the right; such a label simply
        // overflows its column.
        $width = 12;
        foreach ($pairs as $label => $value) {
            if ($value === '' || $value === null) { continue; }
            $needed = strlen($label) + 2; // ':' plus at least one space
            if ($needed > $width) { $width = min($needed, self::MAX_LABEL_COL); }
        }

        $lines = [];
        foreach ($pairs as $label => $value) {
            if ($value === '' || $value === null) { continue; }
            // The separating space is part of the padded string, so a label that
            // overflows the column still keeps one space before its value.
            $lines[] = str_pad($label . ': ', $width, ' ', STR_PAD_RIGHT) . $value;
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
