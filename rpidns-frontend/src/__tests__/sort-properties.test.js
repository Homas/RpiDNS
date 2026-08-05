/**
 * Newly Seen Queries Sorting - Property Tests
 *
 * Property-based tests verifying the sorting behavior of the client-side
 * helpers used by the Newly seen queries view.
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import { sortRows, nextSortState } from '@/composables/useNetworkTools'

/**
 * Independent reference comparator that mirrors the comparison rule documented
 * for sortRows: numeric values are compared numerically, everything else is
 * compared as a case-insensitive, numeric-aware string (localeCompare with
 * sensitivity 'base' and numeric: true). Kept separate from the implementation
 * so the property is verified against an independent oracle.
 */
function referenceCompare(a, b) {
  if (typeof a === 'number' && typeof b === 'number') {
    return a - b
  }
  const as = a == null ? '' : String(a)
  const bs = b == null ? '' : String(b)
  return as.localeCompare(bs, undefined, { sensitivity: 'base', numeric: true })
}

// The sortable columns exposed by the Newly seen queries view.
const COLUMNS = ['fqdn', 'cnt', 'last_seen']

// A single cell value: either a number (numeric comparison) or a string
// (case-insensitive string comparison). Integers are bounded and doubles are
// excluded so NaN cannot destabilize the ordering oracle.
const cellArb = fc.oneof(
  fc.integer({ min: -1000, max: 1000 }),
  fc.string()
)

// A row is a record with a value for each sortable column.
const rowArb = fc.record({
  fqdn: cellArb,
  cnt: cellArb,
  last_seen: cellArb
})

const rowsArb = fc.array(rowArb, { minLength: 0, maxLength: 30 })

const columnArb = fc.constantFrom(...COLUMNS)

describe('Feature: research-tools, Property 4: Sorting orders rows and toggles direction', () => {
  /**
   * Validates: Requirements 2.9
   *
   * For any dataset and any sortable column, the displayed rows SHALL be ordered
   * by that column, and selecting the same column again SHALL reverse the
   * ordering.
   */
  it('orders rows by the selected column and reverses order when the same column is re-selected', () => {
    fc.assert(
      fc.property(rowsArb, columnArb, (rows, column) => {
        // --- Direction toggling via nextSortState ---
        // First selection of a (previously unselected) column → ascending.
        const stateAsc = nextSortState({ column: null, descending: false }, column)
        expect(stateAsc).toEqual({ column, descending: false })

        // Re-selecting the same column → toggles to descending.
        const stateDesc = nextSortState(stateAsc, column)
        expect(stateDesc.column).toBe(column)
        expect(stateDesc.descending).toBe(!stateAsc.descending)
        expect(stateDesc.descending).toBe(true)

        // --- Ordering produced by sortRows for each direction ---
        const ascRows = sortRows(rows, column, stateAsc.descending)
        const descRows = sortRows(rows, column, stateDesc.descending)

        // sortRows returns a new array and never mutates its input.
        expect(ascRows).not.toBe(rows)
        expect(ascRows).toHaveLength(rows.length)
        expect(descRows).toHaveLength(rows.length)

        const ascValues = ascRows.map(r => r[column])
        const descValues = descRows.map(r => r[column])

        // Ascending: every adjacent pair is ordered non-decreasingly per the
        // independent reference comparison.
        for (let i = 0; i + 1 < ascValues.length; i++) {
          expect(referenceCompare(ascValues[i], ascValues[i + 1])).toBeLessThanOrEqual(0)
        }

        // Descending: every adjacent pair is ordered non-increasingly.
        for (let i = 0; i + 1 < descValues.length; i++) {
          expect(referenceCompare(descValues[i], descValues[i + 1])).toBeGreaterThanOrEqual(0)
        }

        // Re-selecting the same column reverses the ordering: two non-increasing
        // / non-decreasing arrangements of the same multiset agree position-for
        // -position when one is reversed (ties compare equal, so this holds even
        // with duplicate values).
        const n = ascValues.length
        for (let i = 0; i < n; i++) {
          expect(referenceCompare(descValues[i], ascValues[n - 1 - i])).toBe(0)
        }
      }),
      { numRuns: 100 }
    )
  })
})
