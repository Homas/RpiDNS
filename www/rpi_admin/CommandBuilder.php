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
    const BIN_CHROMIUM   = 'chromium';

    /**
     * Headless-browser executable names, in preference order. Distributions
     * disagree on the name: Alpine's `chromium` package installs `chromium`
     * (older releases used `chromium-browser`), Debian/Ubuntu ship
     * `chromium`/`chromium-browser`, and a Chrome install provides
     * `google-chrome`. The first one found on disk is used.
     */
    const CHROMIUM_CANDIDATES = [
        'chromium',
        'chromium-browser',
        'chrome',
        'google-chrome',
        'google-chrome-stable',
    ];

    /** Directories searched for the headless-browser executable. */
    const CHROMIUM_SEARCH_DIRS = ['/usr/bin', '/usr/local/bin', '/usr/lib/chromium', '/opt/google/chrome'];

    /** Wall-clock budget (ms) chromium is given to load and render the page. */
    const PREVIEW_VIRTUAL_TIME_BUDGET_MS = 8000;

    /** Hard per-run timeout (ms) passed to chromium as a safety net. */
    const PREVIEW_TIMEOUT_MS = 20000;

    /** Tools that CommandBuilder knows how to build. */
    const SUPPORTED_TOOLS = [
        'rdap', 'dig', 'ping', 'traceroute',
        'reverse_dns', 'nsmx', 'geoip', 'asn',
        'tls_cert', 'reputation', 'website_preview'
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
     * a single argv; composite tools (e.g. NS/MX enumeration) return several.
     * Each argv is a self-contained argument vector.
     *
     * @param string $tool   One of self::SUPPORTED_TOOLS.
     * @param array  $params Tool parameters. Recognized keys:
     *                       - 'target'      string  domain or IP (user input)
     *                       - 'dns_server'  ?string custom DNS server for dig (user input)
     *                       - 'record_type' ?string dig record type (fixed, caller-chosen)
     *                       - 'output_path' ?string website-preview screenshot destination
     *                       - 'profile_dir' ?string website-preview chromium profile dir
     *                       - 'resolve_ip'  ?string pre-resolved address to pin the
     *                         tool to, from ResearchResolver. For target-connecting
     *                         tools (ping, traceroute, tls_cert, website_preview)
     *                         this is the target's address; for endpoint tools
     *                         (rdap, geoip, asn, reputation) it is the API host's.
     *                         Ignored by the dig-based tools, which take a server
     *                         directly, and ignored when not a valid address.
     * @return array<int, array<int, string>> List of argv arrays.
     * @throws InvalidArgumentException When the tool is unknown.
     */
    public function build(string $tool, array $params = []): array {
        $target = isset($params['target']) ? (string)$params['target'] : '';
        $dnsServer = array_key_exists('dns_server', $params) && $params['dns_server'] !== null && $params['dns_server'] !== ''
            ? (string)$params['dns_server']
            : null;
        $resolveIp = isset($params['resolve_ip']) ? (string)$params['resolve_ip'] : '';

        switch ($tool) {
            case 'rdap':
                return [$this->buildRdap($target, $resolveIp)];
            case 'dig':
                $recordType = isset($params['record_type']) ? (string)$params['record_type'] : 'A';
                return [$this->buildDig($target, $recordType, $dnsServer)];
            case 'ping':
                return [$this->buildPing($target, $resolveIp)];
            case 'traceroute':
                return [$this->buildTraceroute($target, $resolveIp)];
            case 'reverse_dns':
                return [$this->buildReverseDns($target, $dnsServer)];
            case 'nsmx':
                return $this->buildNsMx($target, $dnsServer);
            case 'geoip':
                return [$this->buildGeoIp($target, $resolveIp)];
            case 'asn':
                return [$this->buildAsn($target, $resolveIp)];
            case 'tls_cert':
                return [$this->buildTlsCert($target, $resolveIp)];
            case 'reputation':
                return [$this->buildReputation($target, $resolveIp)];
            case 'website_preview':
                $outputPath = isset($params['output_path']) ? (string)$params['output_path'] : '';
                $profileDir = isset($params['profile_dir']) ? (string)$params['profile_dir'] : '';
                return [$this->buildWebsitePreview($target, $outputPath, $profileDir, $resolveIp)];
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
    public function buildRdap(string $target, string $resolveIp = ''): array {
        $resource = $this->isIp($target) ? 'ip' : 'domain';
        // Target embedded in exactly one argv element (the URL). It cannot spawn
        // additional arguments or shell syntax because it is a single arg.
        $url = $this->rdapBase . '/' . $resource . '/' . $target;
        $argv = [
            self::BIN_CURL,
            '-sSL',
            '-4',                                                  // avoid IPv6 black-hole stalls
            '--connect-timeout', (string)$this->curlConnectTimeout, // fail fast if unreachable
            '--max-time', (string)$this->curlMaxTime,
            '--max-redirs', (string)self::CURL_MAX_REDIRS,         // bootstrap -> registry hop(s)
            '-A', self::CURL_USER_AGENT,                           // avoid Cloudflare stalls
            '-H', 'Accept: application/rdap+json',
        ];
        // Pins the bootstrap host only; the registry it redirects to is not known
        // until the redirect arrives and resolves through the system resolver.
        foreach ($this->resolveArgs($url, $resolveIp) as $arg) {
            $argv[] = $arg;
        }
        $argv[] = $url;

        return $argv;
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
    public function buildPing(string $target, string $connectIp = ''): array {
        // ping has no way to be told which resolver to use, so when the caller
        // has already resolved the name through the Research resolver the address
        // is probed directly. Otherwise ping resolves via the system resolver,
        // which answers blocked domains with the RPZ response.
        $host = ($connectIp !== '' && $this->isIp($connectIp)) ? $connectIp : $target;
        return [
            self::BIN_PING,
            '-c', (string)$this->maxProbes, // bounded probe count
            '-w', (string)($this->maxProbes * 2), // hard deadline as a safety net
            $host, // user input (or its resolved address): exactly one verbatim slot
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
    public function buildTraceroute(string $target, string $connectIp = ''): array {
        // Same resolver limitation as ping: trace to the pre-resolved address
        // when one is available.
        $host = ($connectIp !== '' && $this->isIp($connectIp)) ? $connectIp : $target;
        return [
            self::BIN_TRACEROUTE,
            '-q', (string)$this->maxProbes, // bounded probes per hop
            '-m', (string)$this->maxHops,   // bounded max hops
            '-w', '2',                      // per-probe wait
            $host, // user input (or its resolved address): exactly one verbatim slot
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
    public function buildGeoIp(string $ip, string $resolveIp = ''): array {
        $url = $this->geoipBase . '/' . $ip . '/json/';
        return $this->curlGet($url, $resolveIp);
    }

    /**
     * ASN lookup via curl (external HTTPS API, Req 8.4). The IP is confined to a
     * single URL argument.
     *
     * @param string $ip IP address (validated by caller).
     * @return array<int, string> argv.
     */
    public function buildAsn(string $ip, string $resolveIp = ''): array {
        // The IP (already validated as a discrete IPv4/IPv6 by the caller) is
        // confined to a single URL argument via the `resource` query parameter.
        $url = $this->asnBase . '?resource=' . $ip;
        return $this->curlGet($url, $resolveIp);
    }

    /**
     * TLS certificate retrieval via `openssl s_client` (Req 8.5).
     *
     * The domain appears as two discrete arguments - the `host:443` connect
     * target and the `-servername` SNI value - each a single argument that
     * cannot alter the command structure. `-connect`/`-servername` are fixed.
     *
     * When `$connectIp` is supplied the connection goes to that address while
     * SNI keeps the original domain, so the server still returns the certificate
     * for the name asked about. Without it openssl resolves through the system
     * resolver and, for a blocked domain, would report the block page's
     * certificate instead of the real one.
     *
     * @param string $domain    Domain (validated by caller).
     * @param string $connectIp Optional pre-resolved address to connect to.
     * @return array<int, string> argv.
     */
    public function buildTlsCert(string $domain, string $connectIp = ''): array {
        $host = ($connectIp !== '' && $this->isIp($connectIp)) ? $connectIp : $domain;
        return [
            self::BIN_OPENSSL, 's_client',
            '-connect', $host . ':443',   // single discrete argument
            '-servername', $domain,       // SNI always carries the real name
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
    public function buildReputation(string $domain, string $resolveIp = ''): array {
        $url = $this->reputationBase . '/' . $domain . '/general';
        return $this->curlGet($url, $resolveIp);
    }

    /**
     * Locate the headless-browser executable, or null when none is installed.
     *
     * The runner resolves bare names through PATH, but PHP-FPM often runs with a
     * minimal PATH, so absolute paths are resolved here instead. The result is
     * memoized for the request.
     *
     * @return string|null Absolute path to a chromium/chrome binary, or null.
     */
    public static function findChromium(): ?string {
        static $resolved = false;
        static $path = null;

        if ($resolved) {
            return $path;
        }
        $resolved = true;

        foreach (self::CHROMIUM_SEARCH_DIRS as $dir) {
            foreach (self::CHROMIUM_CANDIDATES as $name) {
                $candidate = $dir . '/' . $name;
                if (@is_executable($candidate)) {
                    $path = $candidate;
                    return $path;
                }
            }
        }

        return $path;
    }

    /**
     * Whether the website-preview tool can run on this host, i.e. a headless
     * browser is installed (Req 8.7). Used to decide the default state of the
     * website-preview feature flag so the tool works out of the box on images
     * that bundle chromium and degrades gracefully where it is absent.
     *
     * @return bool True when a headless-browser executable was found.
     */
    public static function websitePreviewAvailable(): bool {
        return self::findChromium() !== null;
    }

    /**
     * Website preview screenshot via headless Chromium (Req 8.7, feature-flagged).
     *
     * The domain is confined to a single URL argument. The screenshot output path
     * and the throwaway profile directory are server-generated (not user input)
     * and passed as fixed-form flags.
     *
     * A dedicated `--user-data-dir` is required because the web server user has
     * no writable HOME; without it chromium fails to start. `--no-sandbox` is
     * required inside the container (no user namespaces), and the virtual time
     * budget bounds how long the page is given to render before the screenshot
     * is taken.
     *
     * When `$resolveIp` is supplied the browser is pinned to that address for
     * this domain via `--host-resolver-rules`. Chromium has no "use this DNS
     * server" switch, so the caller resolves the name with the Research
     * resolver and passes the answer here. That is what lets a domain the
     * appliance blocks by RPZ still render: without it chromium would ask the
     * system resolver and get the block response. The request still carries the
     * original hostname in SNI and the Host header, so TLS and vhosts behave as
     * they would for a normal visitor.
     *
     * @param string $domain     Domain (validated by caller).
     * @param string $outputPath Server-generated destination PNG path.
     * @param string $profileDir Server-generated throwaway profile directory.
     * @param string $resolveIp  Optional address to pin the domain to.
     * @return array<int, string> argv.
     */
    public function buildWebsitePreview(
        string $domain,
        string $outputPath,
        string $profileDir = '',
        string $resolveIp = ''
    ): array {
        $url = 'https://' . $domain;
        $argv = [
            self::findChromium() ?? self::BIN_CHROMIUM,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',       // /dev/shm is small in containers
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--hide-scrollbars',
            '--window-size=1280,1024',
            '--virtual-time-budget=' . self::PREVIEW_VIRTUAL_TIME_BUDGET_MS,
            '--timeout=' . self::PREVIEW_TIMEOUT_MS,
        ];
        if ($profileDir !== '') {
            // Writable profile location: the web server user has no usable HOME.
            $argv[] = '--user-data-dir=' . $profileDir;
            $argv[] = '--crash-dumps-dir=' . $profileDir;
        }
        if ($resolveIp !== '' && $this->isIp($resolveIp)) {
            // Fixed-form rule in a single argv slot; the address is only ever a
            // validated IP, and the domain is already validated by the caller.
            $argv[] = '--host-resolver-rules=MAP ' . $domain . ' ' . $resolveIp;
        }
        $argv[] = '--screenshot=' . $outputPath;
        $argv[] = $url; // domain confined to a single URL argument

        return $argv;
    }

    /**
     * Shared curl GET argv for endpoint-style tools.
     *
     * These tools never resolve the research target - it travels inside the URL
     * path or query - so the only name resolved is the API endpoint's own. When
     * `$resolveIp` is supplied that endpoint is pinned to the address with
     * `--resolve`, which keeps the tool working when the appliance's own RPZ
     * feeds happen to block the API host. Alpine's curl has no c-ares, so
     * `--dns-servers` is unavailable and `--resolve` is the portable equivalent.
     *
     * @param string $url       Fully-formed URL (target already embedded in one slot).
     * @param string $resolveIp Optional address to pin the URL's host to.
     * @return array<int, string> argv.
     */
    private function curlGet(string $url, string $resolveIp = ''): array {
        $argv = [
            self::BIN_CURL,
            '-sSL',
            '-4',                                                  // avoid IPv6 black-hole stalls
            '--connect-timeout', (string)$this->curlConnectTimeout, // fail fast if unreachable
            '--max-time', (string)$this->curlMaxTime,
            '--max-redirs', (string)self::CURL_MAX_REDIRS,
            '-A', self::CURL_USER_AGENT,
        ];
        foreach ($this->resolveArgs($url, $resolveIp) as $arg) {
            $argv[] = $arg;
        }
        $argv[] = $url;

        return $argv;
    }

    /**
     * Build the `--resolve host:port:address` pair for a URL, or nothing when no
     * usable address was supplied.
     *
     * Only the URL's own host is pinned. A redirect to another host (rdap.org
     * bootstrapping to a registry server, for instance) resolves normally,
     * because its name is not known until the redirect arrives.
     *
     * @param string $url       Fully-formed URL.
     * @param string $resolveIp Candidate address.
     * @return array<int, string> Zero or two argv elements.
     */
    private function resolveArgs(string $url, string $resolveIp): array {
        if ($resolveIp === '' || !$this->isIp($resolveIp)) {
            return [];
        }
        $host = self::urlHost($url);
        if ($host === null) {
            return [];
        }
        $port = (strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'http') ? '80' : '443';

        return ['--resolve', $host . ':' . $port . ':' . $resolveIp];
    }

    /**
     * Host component of a URL, or null when it cannot be determined.
     *
     * @param string $url
     * @return string|null
     */
    public static function urlHost(string $url): ?string {
        $host = parse_url($url, PHP_URL_HOST);
        return (is_string($host) && $host !== '') ? $host : null;
    }

    /**
     * The API host a curl-based tool contacts, so a caller can resolve it through
     * the Research resolver before the request is built. Returns null for tools
     * that contact no fixed endpoint.
     *
     * @param string $tool Tool name.
     * @return string|null Hostname, or null.
     */
    public function apiHost(string $tool): ?string {
        switch ($tool) {
            case 'rdap':
                return self::urlHost($this->rdapBase);
            case 'geoip':
                return self::urlHost($this->geoipBase);
            case 'asn':
                return self::urlHost($this->asnBase);
            case 'reputation':
                return self::urlHost($this->reputationBase);
            default:
                return null;
        }
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
