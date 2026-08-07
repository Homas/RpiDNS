/*
 * (c) Vadim Pavlov 2020 - 2026
 * Network tools composable for RpiDNS
 * Single source of research tool definitions, parallel to useResearchLinks.js.
 * Adding or removing a tool here propagates to the Research tools panel and to
 * every page that renders the ContextMenu (Query Log, RPZ Log, Newly seen
 * queries) with no per-page change.
 */

/** Target classes a tool can accept. */
export const ACCEPTS_BOTH = 'both'
export const ACCEPTS_DOMAIN = 'domain'
export const ACCEPTS_IP = 'ip'

/** Result rendering kind: plain text output, or a captured image. */
export const RENDER_TEXT = 'text'
export const RENDER_IMAGE = 'image'

/**
 * Research tool definitions for backend-executed utilities. This is the single
 * source of truth consumed by the Research tools panel and the ContextMenu.
 *
 * Each entry declares:
 *  - `name`     the machine name accepted by the `research_tool` endpoint
 *  - `label`    the human label rendered in the UI
 *  - `icon`     Font Awesome icon class
 *  - `accepts`  which target class the backend validator requires
 *  - `render`   how the result is displayed (text output or image)
 *  - `slow`     true for tools that routinely take several seconds, so the UI
 *               can run them last and let the fast ones paint first
 *
 * Order is the display order: identification first, then DNS, then reachability,
 * then transport/reputation, with the visual preview last.
 *
 * @type {Array<{name: string, label: string, icon: string, accepts: string, render: string, slow?: boolean}>}
 */
export const RESEARCH_TOOLS = [
  { name: 'rdap', label: 'RDAP / WHOIS', icon: 'fa-id-card', accepts: ACCEPTS_BOTH, render: RENDER_TEXT },
  { name: 'dig', label: 'DNS records (dig)', icon: 'fa-magnifying-glass', accepts: ACCEPTS_BOTH, render: RENDER_TEXT },
  { name: 'nsmx', label: 'NS / MX records', icon: 'fa-envelope', accepts: ACCEPTS_DOMAIN, render: RENDER_TEXT },
  { name: 'reverse_dns', label: 'Reverse DNS (PTR)', icon: 'fa-arrows-rotate', accepts: ACCEPTS_IP, render: RENDER_TEXT },
  { name: 'geoip', label: 'GeoIP', icon: 'fa-globe', accepts: ACCEPTS_IP, render: RENDER_TEXT },
  { name: 'asn', label: 'ASN', icon: 'fa-network-wired', accepts: ACCEPTS_IP, render: RENDER_TEXT },
  { name: 'ping', label: 'ping', icon: 'fa-satellite-dish', accepts: ACCEPTS_BOTH, render: RENDER_TEXT },
  { name: 'traceroute', label: 'traceroute', icon: 'fa-route', accepts: ACCEPTS_BOTH, render: RENDER_TEXT, slow: true },
  { name: 'tls_cert', label: 'TLS certificate', icon: 'fa-lock', accepts: ACCEPTS_DOMAIN, render: RENDER_TEXT },
  { name: 'reputation', label: 'Reputation / threat intel', icon: 'fa-shield-halved', accepts: ACCEPTS_DOMAIN, render: RENDER_TEXT },
  { name: 'website_preview', label: 'Website preview', icon: 'fa-image', accepts: ACCEPTS_DOMAIN, render: RENDER_IMAGE, slow: true }
]

/**
 * Every tool honors the selected DNS resolver, in one of three ways depending on
 * what the underlying utility supports:
 *
 *  - `dig`, `nsmx`, `reverse_dns` query the resolver directly (`@server`).
 *  - `ping`, `traceroute`, `tls_cert`, `website_preview` cannot be told which
 *    resolver to use, so the backend resolves the target through it and hands the
 *    address to the utility. Without this they would probe, negotiate TLS with,
 *    or screenshot whatever the appliance answers for a blocked domain — its
 *    block page — instead of the real host.
 *  - `rdap`, `geoip`, `asn`, `reputation` never resolve the target at all (it
 *    travels inside a URL); their external API host is pinned through the
 *    resolver so they keep working if the appliance's own feeds block it.
 *
 * Derived from RESEARCH_TOOLS so a newly added tool is covered automatically.
 * @type {string[]}
 */
export const DNS_AWARE_TOOLS = RESEARCH_TOOLS.map(tool => tool.name)

/** Target classification results. */
export const TARGET_EMPTY = 'empty'
export const TARGET_DOMAIN = 'domain'
export const TARGET_IP = 'ip'
export const TARGET_INVALID = 'invalid'

const IPV4_RE = /^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/
const LABEL_RE = /^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?$/

/**
 * Classify a target the same way the backend validators do, so the UI only
 * offers (and only runs) the tools that the server will actually accept.
 * Mirrors InputValidator::isValidIp / isValidDomain.
 * @param {string} value - Raw target string
 * @returns {string} One of TARGET_EMPTY | TARGET_IP | TARGET_DOMAIN | TARGET_INVALID
 */
export function classifyTarget(value) {
  const s = (value == null ? '' : String(value)).trim()
  if (s === '') return TARGET_EMPTY
  if (IPV4_RE.test(s)) return TARGET_IP
  // IPv6: hex groups and colons only, at least one colon.
  if (s.includes(':') && /^[0-9A-Fa-f:.]+$/.test(s)) return TARGET_IP
  if (s.length > 253) return TARGET_INVALID

  const bare = s.endsWith('.') ? s.slice(0, -1) : s
  if (bare === '') return TARGET_INVALID
  const labels = bare.split('.')
  for (const label of labels) {
    if (label.length < 1 || label.length > 63) return TARGET_INVALID
    if (!LABEL_RE.test(label)) return TARGET_INVALID
  }
  // A purely numeric TLD is neither a valid hostname nor a valid IP.
  if (/^[0-9]+$/.test(labels[labels.length - 1])) return TARGET_INVALID
  return TARGET_DOMAIN
}

/**
 * True if a tool accepts the given target class. Tools that accept `both` are
 * applicable to any valid target; the rest require their own class.
 * @param {{accepts: string}} tool - A RESEARCH_TOOLS entry
 * @param {string} kind - A TARGET_* classification
 * @returns {boolean} Whether the tool can run against that target class
 */
export function toolAccepts(tool, kind) {
  if (!tool) return false
  if (kind !== TARGET_DOMAIN && kind !== TARGET_IP) return false
  if (tool.accepts === ACCEPTS_BOTH) return true
  return tool.accepts === kind
}

/**
 * The subset of RESEARCH_TOOLS that can run against a target class, in
 * definition order. Returns an empty list for an empty or invalid target.
 * @param {string} kind - A TARGET_* classification
 * @returns {Array<Object>} Applicable tool definitions, in definition order
 */
export function toolsForTarget(kind) {
  return RESEARCH_TOOLS.filter(tool => toolAccepts(tool, kind))
}

/*
 * ---------------------------------------------------------------------------
 * Client-side aggregation / filter / sort helpers used by UniqueQueriesView.
 * All helpers are pure and side-effect free (they never mutate their inputs).
 * ---------------------------------------------------------------------------
 */

/**
 * Filter unique-query rows by a case-insensitive substring match on the FQDN.
 * A row is included if and only if its `fqdn` contains `filterText` as a
 * case-insensitive substring. An empty or whitespace-only filter returns all rows.
 * (Req 2.6 — case-insensitive substring filter.)
 * @param {Array<{fqdn: string}>} rows - Rows to filter
 * @param {string} filterText - Substring to match against each fqdn
 * @returns {Array} A new array containing only the matching rows
 */
export function filterByFqdn(rows, filterText) {
  if (!Array.isArray(rows)) return []
  const needle = (filterText == null ? '' : String(filterText)).toLowerCase()
  if (needle === '') return rows.slice()
  return rows.filter(row => {
    const fqdn = row && row.fqdn != null ? String(row.fqdn).toLowerCase() : ''
    return fqdn.includes(needle)
  })
}

/**
 * Compare two values for sorting. Numeric values are compared numerically;
 * everything else is compared as a case-insensitive string.
 * @param {*} a - First value
 * @param {*} b - Second value
 * @returns {number} Negative if a < b, positive if a > b, 0 if equal
 */
function compareValues(a, b) {
  if (typeof a === 'number' && typeof b === 'number') {
    return a - b
  }
  const as = a == null ? '' : String(a)
  const bs = b == null ? '' : String(b)
  return as.localeCompare(bs, undefined, { sensitivity: 'base', numeric: true })
}

/**
 * Sort unique-query rows by a column in ascending or descending order.
 * Sorting is stable and returns a new array (the input is not mutated).
 * (Req 2.9 — order rows by selected column.)
 * @param {Array<Object>} rows - Rows to sort
 * @param {string} column - Property name to sort by (e.g. 'fqdn', 'cnt', 'last_seen')
 * @param {boolean} [descending=false] - When true, sort in descending order
 * @returns {Array} A new sorted array
 */
export function sortRows(rows, column, descending = false) {
  if (!Array.isArray(rows)) return []
  const dir = descending ? -1 : 1
  return rows
    .map((row, index) => ({ row, index }))
    .sort((x, y) => {
      const cmp = compareValues(
        x.row ? x.row[column] : undefined,
        y.row ? y.row[column] : undefined
      )
      // Preserve original order for equal values (stable sort)
      return cmp !== 0 ? cmp * dir : x.index - y.index
    })
    .map(entry => entry.row)
}

/**
 * Compute the next sort state given the current state and a selected column.
 * Selecting a new column sorts ascending; re-selecting the current column
 * toggles between ascending and descending. (Req 2.9 — toggle direction on
 * repeated selection of the same column.)
 * @param {{column: string|null, descending: boolean}} current - Current sort state
 * @param {string} column - The column that was selected
 * @returns {{column: string, descending: boolean}} The next sort state
 */
export function nextSortState(current, column) {
  const state = current || { column: null, descending: false }
  if (state.column === column) {
    return { column, descending: !state.descending }
  }
  return { column, descending: false }
}

/**
 * Aggregate raw query records into FIRST-SEEN allowed FQDN rows for a time range.
 *
 * "Unique" means newly observed: the FQDN was requested for the first time inside
 * the selected range and was never requested at any point before the range start.
 * Mirrors the backend reference aggregation used by the Unique_Queries_View:
 *   (a) keep only records with action === 'allowed',
 *   (b) keep only records whose timestamp is within the inclusive [start, end] range,
 *   (c) group by fqdn so no fqdn repeats,
 *   (d) report the total in-range allowed count and the maximum in-range timestamp,
 *   (e) drop any fqdn that has ANY record (allowed or blocked) with dt < start,
 *       since a previously requested domain is not newly observed.
 * Each record's count contribution is its numeric `cnt` field when present, otherwise 1.
 * @param {Array<{fqdn: string, action: string, dt: (number|string), cnt?: number}>} records
 * @param {(number|string)} [start] - Inclusive lower bound (omit for no lower bound)
 * @param {(number|string)} [end] - Inclusive upper bound (omit for no upper bound)
 * @returns {Array<{fqdn: string, cnt: number, last_seen: (number|string)}>} First-seen rows
 */
export function aggregateUniqueQueries(records, start, end) {
  if (!Array.isArray(records)) return []

  // Pass 1: every fqdn with recorded activity of any action before the range
  // start. These have been requested before and are therefore not newly seen.
  const seenBefore = new Set()
  if (start != null) {
    for (const rec of records) {
      if (!rec) continue
      if (rec.dt < start) seenBefore.add(rec.fqdn)
    }
  }

  // Pass 2: aggregate the in-range allowed records for the remaining fqdns.
  const groups = new Map()
  for (const rec of records) {
    if (!rec || rec.action !== 'allowed') continue
    const dt = rec.dt
    if (start != null && dt < start) continue
    if (end != null && dt > end) continue
    if (seenBefore.has(rec.fqdn)) continue

    const fqdn = rec.fqdn
    const contribution =
      typeof rec.cnt === 'number' && Number.isFinite(rec.cnt) ? rec.cnt : 1

    const existing = groups.get(fqdn)
    if (existing) {
      existing.cnt += contribution
      if (dt > existing.last_seen) existing.last_seen = dt
    } else {
      groups.set(fqdn, { fqdn, cnt: contribution, last_seen: dt })
    }
  }
  return Array.from(groups.values())
}
