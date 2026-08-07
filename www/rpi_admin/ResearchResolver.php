<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ResearchResolver - Resolves hostnames through the Research DNS resolver.
 *
 * Only the dig-based tools can be told which server to query. Every other
 * utility (ping, traceroute, openssl, curl, chromium) asks the system resolver,
 * which on this appliance is the appliance itself - so a domain blocked by RPZ
 * resolves to the block response and the tool reports on the block page instead
 * of the real host. This component closes that gap: the name is resolved here
 * with the selected resolver and the resulting address is handed to the utility,
 * which is then pointed at the right host while still presenting the original
 * name where the protocol needs it (TLS SNI, HTTP Host).
 *
 * Only A records are used. The tools this feeds are given a single address to
 * connect to, and the appliance is not guaranteed to have IPv6 connectivity, so
 * an AAAA-only name is reported as unresolved rather than silently falling back
 * to the system resolver and producing misleading output.
 *
 * Results are memoized per (host, resolver) for the lifetime of the request, so
 * a tool that needs both a target and an API host costs at most one lookup each.
 *
 * @see .kiro/specs/research-tools/design.md ("CommandBuilder", "ToolRunner")
 * Requirements: 6.3, 6.4, 8.7, 8.11
 */
class ResearchResolver {

    /**
     * Wall-clock bound for one resolution. Deliberately well under ToolRunner's
     * 30s default: this runs BEFORE the tool the user asked for, and its cost is
     * added to that tool's own runtime.
     */
    const RESOLVE_TIMEOUT_SEC = 8;

    /** @var CommandBuilder Builds the dig argv (never executes anything). */
    private $builder;

    /** @var array<string, string|null> Memoized "host|resolver" => IPv4 or null. */
    private $cache = array();

    /**
     * @param CommandBuilder|null $builder Reused builder, or a fresh one.
     */
    public function __construct($builder = null) {
        $this->builder = ($builder instanceof CommandBuilder) ? $builder : new CommandBuilder();
    }

    /**
     * Resolve a hostname to a single IPv4 address using the given resolver.
     *
     * @param string      $host     Hostname to resolve (validated by the caller).
     * @param string|null $resolver Resolver to query, or null for the system one.
     * @return string|null The address, or null when the name did not resolve.
     */
    public function resolveA($host, $resolver = null) {
        $host = trim((string)$host);
        if ($host === '') {
            return null;
        }

        // An address needs no resolution; return it unchanged so callers can pass
        // targets through without caring which form they were given.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }
        // An IPv6 literal is not something this component can hand to the
        // single-address callers, and it needs no lookup either.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $key = $host . '|' . (string)$resolver;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $this->cache[$key] = null;

        $params = array('target' => $host, 'record_type' => 'A');
        if ($resolver !== null && $resolver !== '') {
            $params['dns_server'] = $resolver;
        }

        try {
            $cmds = $this->builder->build('dig', $params);
            $runner = new ToolRunner(self::RESOLVE_TIMEOUT_SEC);
            $result = $runner->run('dig', $host, $cmds[0]);
            $this->cache[$key] = self::firstAnswerA(isset($result['output']) ? (string)$result['output'] : '');
        } catch (Exception $e) {
            // Leave the memoized null: the caller decides whether an unresolved
            // name is fatal or simply means "carry on without an override".
        }

        return $this->cache[$key];
    }

    /**
     * Extract the first IPv4 address from the ANSWER section of dig output.
     *
     * Scoped to the ANSWER section on purpose: an A record in AUTHORITY or
     * ADDITIONAL is nameserver glue, and connecting a tool to a nameserver's
     * address instead of the target's would be both wrong and confusing. A CNAME
     * chain is handled naturally, since the address that terminates the chain is
     * in the same section.
     *
     * @param string $digOutput Raw dig output.
     * @return string|null The address, or null when the section has no A record.
     */
    public static function firstAnswerA($digOutput) {
        $answer = '';
        if (preg_match('/;; ANSWER SECTION:\s*\n(.*?)(\n\s*\n|$)/s', (string)$digOutput, $section)) {
            $answer = $section[1];
        }
        if ($answer === '') {
            return null;
        }
        if (!preg_match('/^\S+\.?\s+\d+\s+IN\s+A\s+(\d{1,3}(?:\.\d{1,3}){3})\s*$/m', $answer, $m)) {
            return null;
        }
        return (filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) ? $m[1] : null;
    }
}
