/*
 * (c) Vadim Pavlov 2020 - 2026
 * CSV export composable for RpiDNS Research tools
 * Provides a pure RFC 4180 CSV serializer and a clipboard copy helper
 * shared by the Unique Queries view and the SQL Query tool.
 */

// RFC 4180 record separator.
const CRLF = '\r\n'

/**
 * Serialize a single field value to an RFC 4180-compliant CSV field.
 * A field is quoted when it contains a comma, a double quote, a carriage
 * return, or a line feed. Embedded double quotes are escaped by doubling.
 * Null/undefined values are treated as the empty string.
 * @param {*} value - The field value to serialize
 * @returns {string} The RFC 4180-encoded field
 */
function encodeField(value) {
  const str = value === null || value === undefined ? '' : String(value)
  if (/[",\r\n]/.test(str)) {
    return '"' + str.replace(/"/g, '""') + '"'
  }
  return str
}

/**
 * Produce RFC 4180-compliant CSV text from column names and data rows.
 *
 * - Emits a header row of column names followed by one row per data record.
 * - Terminates every row (including the last) with a CRLF sequence.
 * - Quotes fields containing comma, double quote, CR, or LF and doubles
 *   embedded double quotes.
 * - When there are zero data rows, produces CSV containing only the header row.
 *
 * @param {Array<*>} columns - Column names for the header row
 * @param {Array<Array<*>>} rows - Data records, each an array of field values
 * @returns {string} RFC 4180-compliant CSV text
 */
export function toCsv(columns, rows) {
  const cols = Array.isArray(columns) ? columns : []
  const dataRows = Array.isArray(rows) ? rows : []

  const lines = []
  lines.push(cols.map(encodeField).join(','))
  for (const row of dataRows) {
    const cells = Array.isArray(row) ? row : []
    lines.push(cells.map(encodeField).join(','))
  }

  // Each record, including the last, is terminated by CRLF (RFC 4180).
  return lines.map(line => line + CRLF).join('')
}

/**
 * Serialize the dataset to CSV and write it to the system clipboard.
 * @param {Array<*>} columns - Column names for the header row
 * @param {Array<Array<*>>} rows - Data records, each an array of field values
 * @returns {Promise<void>} Resolves when the clipboard write succeeds; rejects on failure
 */
export function copyDatasetAsCsv(columns, rows) {
  const csv = toCsv(columns, rows)
  return navigator.clipboard.writeText(csv)
}
