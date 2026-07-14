<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * CommandBuilder - Builds safe argument vectors for the Research network tools.
 *
 * This class is the single source of truth for how every Research network tool
 * is invoked. It NEVER produces a shell string: each build method returns an
 * argument vector (argv) - a flat list of strings - that is meant to be handed
 * to `proc_open()` with an argv array (or, as a fallback, escaped per-argument).
 *
 * Design guarantees (see .kiro/specs/research-tools/design.md, Properties 8 & 9):
 *  - User-supplied input is passed as a discrete argument and can never alter the
 *    fixed command structure (command-injection resistance, Req 6.6 / 8.12).
 *    For the argument-style tools (dig, ping, traceroute, reverse DNS) the input
 *    occupies exactly one argv slot, verbatim. For the URL/endpoint-style tools
 *    (RDAP, GeoIP, ASN, reputation, TLS, website-preview) the input is confined
 *    to a single discrete argv element (embedded in one URL/host argument); it is
 *    still a single argument and cannot introduce new arguments or shell syntax.
 *  - `ping` and `traceroute` are always bounded by a fixed maximum probe count so
 *    every invocation self-terminates (Req 6.8).
 *
 * The class performs NO validation of its inputs - callers MUST validate the
 * target/DNS-server with InputValidator before building a command. CommandBuilder
 * is intentionally pure and side-effect free so it can be property-tested in
 * isolation.
 */
class CommandBuilder {
    /** Default maximum number of probes for ping (`-c`) and traceroute (`-q`). */
    const DEFAULT_MAX_PROBES = 4;

    /** Default maximum number of hops for traceroute (`-m`) so it self-terminates. */
    const DEFAULT_MAX_HOPS = 30;

    /** Default wall-clock bound (seconds) applied to curl-based tools. */
    const DEFAULT_CURL_MAX_TIME = 25;

    /**
     * Default TCP connect timeout (seconds) for curl-based tools. Kept well
     * below the overall max-time so an unreachable endpoint fails fast instead
     * of consuming the entire wall-clock budget and surfacing as a bare
     * "operation timed out" (the RDAP failure mode we hit against rdap.org).
     */
    const DEFAULT_CURL_CONNECT_TIMEOUT = 8;

    /** Maximum HTTP redirects curl will follow (rdap.org bootstrap -> registry). */
    const CURL_MAX_REDIRS = 8;

    /**
     * User-Agent sent by the curl-based tools. Several upstreams (notably the
     * Cloudflare-fronted rdap.org bootstrap) stall or challenge requests that
     * carry no/blank User-Agent, which manifests as a full-timeout with zero
     * bytes received. A concrete UA avoids that.
     */
    const CURL_USER_AGENT = 'RpiDNS-Research/1.0';

    /** Executable names (resolved via PATH by the runner). */
    const BIN_DIG        = 'dig';
    const BIN_CURL       = 'curl';
    const BIN_PING       = 'ping';
    const BIN_TRACEROUTE = 'traceroute';
    const BIN_OPENSSL    = 'openssl';
    const BIN_CHROMIUM   = 'chromium-browser';

    /** Tools that CommandBuilder knows how to build. */
    const SUPPORTED_TOOLS = [
        'rdap', 'dig', 'ping', 'traceroute',
        'reverse_dns', 'nsmx', 'geoip', 'asn',
        'tls_cert', 'reputation', 'website_preview', 'bulk'
    ];

    /** @var int Configured maximum probe count for ping/traceroute. */
    private $maxProbes;

    /** @var int Configured maximum hop count for traceroute. */
    private $maxHops;

    /** @var int Configured curl wall-clock bound in seconds. */
    private $curlMaxTime;

    /** @var int Configured curl TCP connect timeout in seconds. */
    private $curlConnectTimeout;

    /**
     * @var string|null Appliance default DNS server. When null, dig uses the
     *                  system resolver (/etc/resolv.conf), which is the appliance
     *                  default DNS server (Req 6.3).
     */
    private $defaultDnsServer;

    /** @var string RDAP bootstrap base URL. */
    private $rdapBase = 'https://rdap.org';

    /** @var string GeoIP lookup base URL (IP embedded in the path). */
    private $geoipBase = 'https://ipapi.co';

    /**
     * @var string ASN lookup base URL (IP embedded as the `resource` query
     *             parameter). Uses RIPEstat's prefix-overview data API, which is
     *             free, needs no API key, and returns the origin ASN(s) plus the
     *             AS holder/organization name. Replaces the defunct BGPView API
     *             (api.bgpview.io no longer resolves).
     */
    private $asnBase = 'https://stat.ripe.net/data/prefix-overview/data.json';

    /** @var string Reputation/threat-intel base URL (domain embedded in the path). */
    private $reputationBase = 'https://otx.alienvault.com/api/v1/indicators/domain';

    /**
     * @param int         $maxProbes        Maximum probe count for ping/traceroute (configurable).
     * @param int         $maxHops          Maximum hop count for traceroute (configurable).
     * @param string|null $defaultDnsServer Optional explicit default DNS server for dig.
     * @param int         $curlMaxTime      Wall-clock bound (seconds) for curl-based tools.
     */
    public function __construct(
        int $maxProbes = self::DEFAULT_MAX_PROBES,
        int $maxHops = self::DEFAULT_MAX_HOPS,
        ?string $defaultDnsServer = null,
        int $curlMaxTime = self::DEFAULT_CURL_MAX_TIME
    ) {
        // Guard against non-positive / oversized configuration so probes stay bounded.
        $this->maxProbes = ($maxProbes > 0) ? min($maxProbes, self::DEFAULT_MAX_PROBES) : self::DEFAULT_MAX_PROBES;
        $this->maxHops = ($maxHops > 0) ? min($maxHops, self::DEFAULT_MAX_HOPS) : self::DEFAULT_MAX_HOPS;
        $this->curlMaxTime = ($curlMaxTime > 0) ? $curlMaxTime : self::DEFAULT_CURL_MAX_TIME;
        // Connect timeout is bounded by the overall max-time so it can never
        // exceed the wall-clock budget.
        $this->curlConnectTimeout = min(self::DEFAULT_CURL_CONNECT_TIMEOUT, $this->curlMaxTime);
        $this->defaultDnsServer = $defaultDnsServer;
    }

    /** @return int The configured maximum probe count. */
    public function getMaxProbes(): int {
        return $this->maxProbes;
    }

    /** @return int The configured maximum hop count. */
    public function getMaxHops(): int {
        return $this->maxHops;
    }

    /**
     * Build the command(s) for a tool.
     *
     * Every tool returns a LIST of argv arrays (`string[][]`). Most tools return
     * a single argv; composite tools (e.g. NS/MX enumeration, bulk analysis)
     * return several. Each argv is a self-contained argument vector.
     *
     * @param string $tool   One of self::SUPPORTED_TOOLS.
     * @param array  $params Tool parameters. Recognized keys:
     *                       - 'target'      string  domain or IP (user input)
     *                       - 'dns_server'  ?string custom DNS server for dig (user input)
     *                       - 'record_type' ?string dig record type (fixed, caller-chosen)
     *                       - 'output_path' ?string website-preview screenshot destination
     *                       - 'subtool'     ?string bulk: per-item tool name
     *                       - 'items'       ?array  bulk: list of targets
     * @return array<int, array<int, string>> List of argv arrays.
     * @throws InvalidArgumentException When the tool is unknown.
     */
    public function build(string $tool, array $params = []): array {
        $target = isset($params['target']) ? (string)$params['target'] : '';
        $dnsServer = array_key_exists('dns_server', $params) && $params['dns_server'] !== null && $params['dns_server'] !== ''
            ? (string)$params['dns_server']
            : null;

        switch ($tool) {
            case 'rdap':
                return [$this->buildRdap($target)];
            case 'dig':
                $recordType = isset($params['record_type']) ? (string)$params['record_type'] : 'A';
                return [$this->buildDig($target, $recordType, $dnsServer)];
            case 'ping':
                return [$this->buildPing($target)];
            case 'traceroute':
                return [$this->buildTraceroute($target)];
            case 'reverse_dns':
                return [$this->buildReverseDns($target, $dnsServer)];
            case 'nsmx':
                return $this->buildNsMx($target, $dnsServer);
            case 'geoip':
                return [$this->buildGeoIp($target)];
            case 'asn':
                return [$this->buildAsn($target)];
            case 'tls_cert':
                return [$this->buildTlsCert($target)];
            case 'reputation':
                return [$this->buildReputation($target)];
            case 'website_preview':
                $outputPath = isset($params['output_path']) ? (string)$params['output_path'] : '';
                return [$this->buildWebsitePreview($target, $outputPath)];
            case 'bulk':
                $subtool = isset($params['subtool']) ? (string)$params['subtool'] : '';
                $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : [];
                return $this->buildBulk($subtool, $items, $dnsServer);
            default:
                throw new InvalidArgumentException("Unknown research tool: {$tool}");
        }
    }

    /**
     * RDAP / WHOIS lookup via curl. RDAP replaces port-43 WHOIS with JSON over
     * HTTPS and is reachable with the already-installed curl (design note).
     *
     * The target is confined to a single URL argument (one discrete argv slot).
     *
     * @param string $target Domain or IP address (already validated by caller).
     * @return array<int, string> argv.
     */
    public function buildRdap(string $target): array {
        $resource = $this->isIp($target) ? 'ip' : 'domain';
        // Target embedded in exactly one argv element (the URL). It cannot spawn
        // additional arguments or shell syntax because it is a single arg.
        $url = $this->rdapBase . '/' . $resource . '/' . $target;
        return [
            self::BIN_CURL,
            '-sSL',
            '-4',                                                  // avoid IPv6 black-hole stalls
            '--connect-timeout', (string)$this->curlConnectTimeout, // fail fast if unreachable
            '--max-time', (string)$this->curlMaxTime,
            '--max-redirs', (string)self::CURL_MAX_REDIRS,         // bootstrap -> registry hop(s)
            '-A', self::CURL_USER_AGENT,                           // avoid Cloudflare stalls
            '-H', 'Accept: application/rdap+json',
            $url,
        ];
    }

    /**
     * `dig` query. When no DNS server is supplied the system resolver is used,
     * which is the appliance default DNS server (Req 6.3). When a server is
     * supplied it is passed as a single `@server` argument (Req 6.4).
     *
     * The target occupies exactly one argv slot, verbatim. The record type is a
     * fixed, caller-chosen argument (not user input).
     *
     * @param string      $target     Domain or IP (validated by caller).
     * @param string      $recordType DNS record type (e.g. A, AAAA, NS, MX).
     * @param string|null $dnsServer  Optional custom DNS server (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildDig(string $target, string $recordType = 'A', ?string $dnsServer = null): array {
        $argv = [self::BIN_DIG];

        $server = $dnsServer ?? $this->defaultDnsServer;
        if ($server !== null && $server !== '') {
            // Single discrete argument; the '@' prefix is fixed structure.
            $argv[] = '@' . $server;
        }

        // User input: exactly one verbatim slot.
        $argv[] = $target;
        // Fixed argument: caller-controlled record type, sanitized to a safe token.
        $argv[] = $this->safeRecordType($recordType);
        // Fixed bounding options so dig self-terminates promptly.
        $argv[] = '+time=5';
        $argv[] = '+tries=1';

        return $argv;
    }

    /**
     * `ping` bounded to a fixed maximum probe count so it self-terminates (Req 6.8).
     * The target occupies exactly one argv slot, verbatim.
     *
     * @param string $target Domain or IP (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildPing(string $target): array {
        return [
            self::BIN_PING,
            '-c', (string)$this->maxProbes, // bounded probe count
            '-w', (string)($this->maxProbes * 2), // hard deadline as a safety net
            $target, // user input: exactly one verbatim slot
        ];
    }

    /**
     * `traceroute` bounded by a fixed maximum probe count per hop (`-q`) and a
     * fixed maximum hop count (`-m`) so it self-terminates (Req 6.8).
     * The target occupies exactly one argv slot, verbatim.
     *
     * @param string $target Domain or IP (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildTraceroute(string $target): array {
        return [
            self::BIN_TRACEROUTE,
            '-q', (string)$this->maxProbes, // bounded probes per hop
            '-m', (string)$this->maxHops,   // bounded max hops
            '-w', '2',                      // per-probe wait
            $target, // user input: exactly one verbatim slot
        ];
    }

    /**
     * Reverse DNS (PTR) lookup via `dig -x`. The IP occupies exactly one argv
     * slot, verbatim (Req 8.1).
     *
     * @param string      $ip        IP address (validated by caller).
     * @param string|null $dnsServer Optional custom DNS server.
     * @return array<int, string> argv.
     */
    public function buildReverseDns(string $ip, ?string $dnsServer = null): array {
        $argv = [self::BIN_DIG];

        $server = $dnsServer ?? $this->defaultDnsServer;
        if ($server !== null && $server !== '') {
            $argv[] = '@' . $server;
        }

        $argv[] = '-x';
        $argv[] = $ip; // user input: exactly one verbatim slot
        $argv[] = '+noall';
        $argv[] = '+answer';
        $argv[] = '+time=5';
        $argv[] = '+tries=1';

        return $argv;
    }

    /**
     * NS/MX enumeration (Req 8.2). Returned as two argv arrays - one dig per
     * record type - so the domain occupies exactly one verbatim slot in each
     * command (rather than being repeated within a single command).
     *
     * @param string      $domain    Domain (validated by caller).
     * @param string|null $dnsServer Optional custom DNS server.
     * @return array<int, array<int, string>> List of argv arrays.
     */
    public function buildNsMx(string $domain, ?string $dnsServer = null): array {
        return [
            $this->buildDig($domain, 'NS', $dnsServer),
            $this->buildDig($domain, 'MX', $dnsServer),
        ];
    }

    /**
     * GeoIP lookup via curl (external HTTPS API, Req 8.3). The IP is confined to
     * a single URL argument.
     *
     * @param string $ip IP address (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildGeoIp(string $ip): array {
        $url = $this->geoipBase . '/' . $ip . '/json/';
        return $this->curlGet($url);
    }

    /**
     * ASN lookup via curl (external HTTPS API, Req 8.4). The IP is confined to a
     * single URL argument.
     *
     * @param string $ip IP address (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildAsn(string $ip): array {
        // The IP (already validated as a discrete IPv4/IPv6 by the caller) is
        // confined to a single URL argument via the `resource` query parameter.
        $url = $this->asnBase . '?resource=' . $ip;
        return $this->curlGet($url);
    }

    /**
     * TLS certificate retrieval via `openssl s_client` (Req 8.5).
     *
     * The domain appears as two discrete arguments - the `host:443` connect
     * target and the `-servername` SNI value - each a single argument that
     * cannot alter the command structure. `-connect`/`-servername` are fixed.
     *
     * @param string $domain Domain (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildTlsCert(string $domain): array {
        return [
            self::BIN_OPENSSL, 's_client',
            '-connect', $domain . ':443', // single discrete argument
            '-servername', $domain,       // single discrete argument
            '-verify_return_error',
        ];
    }

    /**
     * Domain reputation / threat-intel lookup via curl (Req 8.6). The domain is
     * confined to a single URL argument (embedded in the path).
     *
     * @param string $domain Domain (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildReputation(string $domain): array {
        $url = $this->reputationBase . '/' . $domain . '/general';
        return $this->curlGet($url);
    }

    /**
     * Website preview screenshot via headless Chromium (Req 8.7, feature-flagged).
     *
     * The domain is confined to a single URL argument. The screenshot output
     * path is server-generated (not user input) and passed as a fixed-form flag.
     *
     * @param string $domain     Domain (validated by caller).
     * @param string $outputPath Server-generated destination PNG path.
     * @return array<int, string> argv.
     */
    public function buildWebsitePreview(string $domain, string $outputPath): array {
        $url = 'https://' . $domain;
        return [
            self::BIN_CHROMIUM,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--hide-scrollbars',
            '--window-size=1280,1024',
            '--screenshot=' . $outputPath,
            $url, // domain confined to a single URL argument
        ];
    }

    /**
     * Bulk analysis: build one command list per item by delegating to the given
     * sub-tool (Req 8.8). Preserves submitted order, one command-set per item.
     *
     * @param string      $subtool   Per-item tool name (must be a single-command tool).
     * @param array       $items     List of targets (validated by caller, <= 100).
     * @param string|null $dnsServer Optional custom DNS server passed through.
     * @return array<int, array<int, string>> Flat list of argv arrays, in item order.
     * @throws InvalidArgumentException When the sub-tool is unknown or is itself 'bulk'.
     */
    public function buildBulk(string $subtool, array $items, ?string $dnsServer = null): array {
        if ($subtool === 'bulk' || !in_array($subtool, self::SUPPORTED_TOOLS, true)) {
            throw new InvalidArgumentException("Invalid bulk sub-tool: {$subtool}");
        }

        $commands = [];
        foreach ($items as $item) {
            $built = $this->build($subtool, [
                'target' => (string)$item,
                'dns_server' => $dnsServer,
            ]);
            // $built is a list of argv arrays; append each, preserving order.
            foreach ($built as $argv) {
                $commands[] = $argv;
            }
        }

        return $commands;
    }

    /**
     * Shared curl GET argv for endpoint-style tools.
     *
     * @param string $url Fully-formed URL (target already embedded in one slot).
     * @return array<int, string> argv.
     */
    private function curlGet(string $url): array {
        return [
            self::BIN_CURL,
            '-sSL',
            '-4',                                                  // avoid IPv6 black-hole stalls
            '--connect-timeout', (string)$this->curlConnectTimeout, // fail fast if unreachable
            '--max-time', (string)$this->curlMaxTime,
            '--max-redirs', (string)self::CURL_MAX_REDIRS,
            '-A', self::CURL_USER_AGENT,
            $url,
        ];
    }

    /**
     * Normalize a DNS record type to a safe uppercase token. Record type is
     * caller-chosen (not free user input); this keeps the argv strictly to a
     * known token shape even if a caller passes something unexpected.
     *
     * @param string $recordType Requested record type.
     * @return string A-Z/0-9 token, defaulting to 'A'.
     */
    private function safeRecordType(string $recordType): string {
        $token = strtoupper(trim($recordType));
        if ($token === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $token)) {
            return 'A';
        }
        return $token;
    }

    /**
     * @param string $value Candidate string.
     * @return bool True if the value is a valid IPv4 or IPv6 address.
     */
    private function isIp(string $value): bool {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
