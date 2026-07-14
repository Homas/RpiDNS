/*
 * (c) Vadim Pavlov 2020 - 2026
 * Network tools composable for RpiDNS
 * Single source of network research tool definitions, parallel to useResearchLinks.js.
 * Adding or removing a tool here propagates to every page that renders the ContextMenu
 * (Query Log, RPZ Log, Unique Queries) with no per-page change.
 */

/**
 * Network tool definitions for backend-executed research utilities.
 * Each entry defines a machine name, a human label, and the expected target type.
 * This is the single source of truth consumed by getToolActions() and the ContextMenu.
 * @type {Array<{name: string, label: string, target: string}>}
 */
export const NETWORK_TOOLS = [
  { name: 'rdap', label: 'RDAP / WHOIS', target: 'domain_or_ip' },
  { name: 'dig', label: 'dig', target: 'domain_or_ip' },
  { name: 'ping', label: 'ping', target: 'domain_or_ip' },
  { name: 'traceroute', label: 'traceroute', target: 'domain_or_ip' }
  // additional tools appended here; propagate everywhere automatically
]

/**
 * Produce one context-menu action per defined network tool, each targeting the domain.
 * The output length always equals NETWORK_TOOLS.length and preserves definition order,
 * so adding/removing a tool in the single source propagates identically to every page.
 * @param {string} domain - The domain (or IP) to target with each tool action
 * @returns {Array<{label: string, name: string, domain: string}>} One action per tool
 */
export function getToolActions(domain) {
  return NETWORK_TOOLS.map(tool => ({
    label: tool.label,
    name: tool.name,
    domain: domain
  }))
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
 * Aggregate raw query records into distinct allowed FQDN rows for a time range.
 * Mirrors the backend reference aggregation used by the Unique_Queries_View:
 *   (a) keep only records with action === 'allowed',
 *   (b) keep only records whose timestamp is within the inclusive [start, end] range,
 *   (c) group by fqdn so no fqdn repeats,
 *   (d) report the total in-range allowed count and the maximum in-range timestamp.
 * Each record's count contribution is its numeric `cnt` field when present, otherwise 1.
 * @param {Array<{fqdn: string, action: string, dt: (number|string), cnt?: number}>} records
 * @param {(number|string)} [start] - Inclusive lower bound (omit for no lower bound)
 * @param {(number|string)} [end] - Inclusive upper bound (omit for no upper bound)
 * @returns {Array<{fqdn: string, cnt: number, last_seen: (number|string)}>} Unique rows
 */
export function aggregateUniqueQueries(records, start, end) {
  if (!Array.isArray(records)) return []
  const groups = new Map()
  for (const rec of records) {
    if (!rec || rec.action !== 'allowed') continue
    const dt = rec.dt
    if (start != null && dt < start) continue
    if (end != null && dt > end) continue

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
