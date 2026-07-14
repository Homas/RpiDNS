/**
 * CSV Export - Property Tests
 *
 * Property-based tests verifying the RFC 4180 round-trip property for the
 * shared CSV export utility used by the Research page tables.
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import { toCsv } from '@/composables/useCsvExport'

/**
 * Independent, RFC 4180-compliant CSV parser implemented specifically for
 * these tests. It intentionally does NOT reuse any logic from `toCsv` so the
 * round-trip check exercises a genuinely separate implementation.
 *
 * Behavior:
 *  - Fields are separated by commas.
 *  - Records are terminated by a CRLF sequence.
 *  - A field may be enclosed in double quotes; inside a quoted field a pair of
 *    double quotes ("") denotes a single embedded double quote, and commas,
 *    CR, and LF are literal content.
 *  - A trailing CRLF (which `toCsv` always emits after every record) does not
 *    produce an extra empty record.
 *
 * @param {string} text - RFC 4180 CSV text
 * @returns {string[][]} Parsed records, each an array of string fields
 */
function parseCsv(text) {
  const records = []
  let record = []
  let field = ''
  let inQuotes = false
  let i = 0

  while (i < text.length) {
    const c = text[i]

    if (inQuotes) {
      if (c === '"') {
        if (text[i + 1] === '"') {
          // Escaped double quote inside a quoted field.
          field += '"'
          i += 2
          continue
        }
        // Closing quote.
        inQuotes = false
        i += 1
        continue
      }
      field += c
      i += 1
      continue
    }

    if (c === '"') {
      inQuotes = true
      i += 1
      continue
    }
    if (c === ',') {
      record.push(field)
      field = ''
      i += 1
      continue
    }
    if (c === '\r' && text[i + 1] === '\n') {
      // CRLF record terminator.
      record.push(field)
      records.push(record)
      record = []
      field = ''
      i += 2
      continue
    }

    field += c
    i += 1
  }

  // Flush any trailing partial record. Because `toCsv` always terminates every
  // record with CRLF, a well-formed input ends here with an empty pending
  // record and this branch is skipped.
  if (field !== '' || record.length > 0) {
    record.push(field)
    records.push(record)
  }

  return records
}

// Coerce a value to a string exactly as `toCsv` does: null/undefined => ''.
function coerce(value) {
  return value === null || value === undefined ? '' : String(value)
}

// Characters that stress RFC 4180 quoting/escaping rules plus ordinary and
// unicode content.
const trickyChar = fc.constantFrom(
  ',', '"', '\r', '\n', '\r\n', ' ', '\t',
  'a', 'B', '0', 'é', '中', '😀'
)

// A field value: strings built from tricky characters, arbitrary unicode
// strings, empty strings, and null/undefined (which `toCsv` maps to '').
const fieldValue = fc.oneof(
  fc.array(trickyChar, { maxLength: 10 }).map(parts => parts.join('')),
  fc.string({ maxLength: 12 }),
  fc.constant(''),
  fc.constant(null),
  fc.constant(undefined)
)

describe('Feature: research-tools, Property 5: CSV export round-trips under RFC 4180', () => {
  /**
   * Validates: Requirements 3.3, 3.4, 3.5, 3.8
   *
   * For any set of column names and data rows, parsing the exported CSV text
   * with an RFC 4180-compliant parser yields exactly the original header row
   * followed by the original data rows. This subsumes CRLF row termination,
   * quoting of fields containing comma/quote/CR/LF, doubling of embedded
   * quotes, and the zero-record header-only case.
   */
  it('parsing exported CSV reproduces the original columns and rows', () => {
    // Fixed column count per case; rows are rectangular (each has `ncols`
    // fields). Row set includes the empty set (minLength 0).
    const dataset = fc.integer({ min: 1, max: 6 }).chain(ncols =>
      fc.record({
        columns: fc.array(fieldValue, { minLength: ncols, maxLength: ncols }),
        rows: fc.array(
          fc.array(fieldValue, { minLength: ncols, maxLength: ncols }),
          { minLength: 0, maxLength: 12 }
        )
      })
    )

    fc.assert(
      fc.property(dataset, ({ columns, rows }) => {
        const csv = toCsv(columns, rows)
        const parsed = parseCsv(csv)

        const expected = [columns.map(coerce), ...rows.map(row => row.map(coerce))]

        expect(parsed).toEqual(expected)
      }),
      { numRuns: 100 }
    )
  })
})
