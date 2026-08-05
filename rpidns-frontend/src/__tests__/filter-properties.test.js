/**
 * Newly Seen Queries Filter - Property Tests
 *
 * Property-based tests verifying the case-insensitive substring filter
 * used by the Unique_Queries_View (filterByFqdn in useNetworkTools.js).
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import { filterByFqdn } from '@/composables/useNetworkTools'

describe('Feature: research-tools, Property 3: Filter is a case-insensitive substring match', () => {
  /**
   * Feature: research-tools, Property 3: Filter is a case-insensitive substring match
   *
   * Validates: Requirements 2.6
   *
   * For any dataset and any filter text, an FQDN SHALL appear in the filtered
   * result if and only if it contains the filter text as a case-insensitive
   * substring. (The empty-string filter is subsumed: "" is a substring of every
   * value, so every row matches and all rows are returned.)
   */

  // Independent reference: a row matches iff its fqdn contains the filter text
  // as a case-insensitive substring. Kept deliberately simple and separate from
  // the implementation under test.
  function referenceMatches(fqdn, filterText) {
    return String(fqdn).toLowerCase().includes(String(filterText).toLowerCase())
  }

  // Generate FQDN-like strings, including varied casing and separators, so that
  // filters can be substrings of some fqdns while others do not match.
  const fqdnArb = fc.oneof(
    fc.domain(),
    fc.string({ minLength: 0, maxLength: 30 }),
    // domain-ish with mixed case
    fc
      .tuple(
        fc.stringMatching(/^[A-Za-z0-9]{1,10}$/),
        fc.stringMatching(/^[A-Za-z]{2,4}$/)
      )
      .map(([label, tld]) => `${label}.${tld}`)
  )

  const rowsArb = fc.array(fc.record({ fqdn: fqdnArb }), {
    minLength: 0,
    maxLength: 30
  })

  it('a row is present iff its fqdn contains the filter as a case-insensitive substring', () => {
    fc.assert(
      fc.property(
        // Derive rows and a filter together so the filter can be a random-cased
        // substring of an existing fqdn (a real match), an arbitrary string, or
        // the empty string.
        rowsArb.chain((rows) => {
          const substringArb =
            rows.length > 0
              ? fc.constantFrom(...rows.map((r) => String(r.fqdn))).chain((fqdn) =>
                  fqdn.length > 0
                    ? fc
                        .tuple(
                          fc.nat({ max: fqdn.length - 1 }),
                          fc.nat({ max: fqdn.length })
                        )
                        .map(([a, b]) =>
                          fqdn.slice(Math.min(a, b), Math.max(a, b))
                        )
                    : fc.constant('')
                )
              : fc.constant('')

          // Randomly upper/lower-case each character to exercise case-insensitivity.
          const casedSubstringArb = substringArb.chain((sub) =>
            fc
              .array(fc.boolean(), { minLength: sub.length, maxLength: sub.length })
              .map((flags) =>
                sub
                  .split('')
                  .map((ch, i) => (flags[i] ? ch.toUpperCase() : ch.toLowerCase()))
                  .join('')
              )
          )

          const filterArb = fc.oneof(
            fc.string({ minLength: 0, maxLength: 15 }),
            fc.constant(''),
            casedSubstringArb
          )

          return fc.record({ rows: fc.constant(rows), filterText: filterArb })
        }),
        ({ rows, filterText }) => {
          const result = filterByFqdn(rows, filterText)

          // Expected result computed via the independent reference: preserves
          // input order and includes exactly the rows that match. This single
          // assertion captures the iff property, that all matching rows appear,
          // and that non-matching rows are excluded.
          const expected = rows.filter((r) => referenceMatches(r.fqdn, filterText))
          expect(result).toEqual(expected)

          // The count of matching rows in the result must equal the count of
          // rows that satisfy the reference match (handles duplicate objects).
          const matchCount = rows.filter((r) =>
            referenceMatches(r.fqdn, filterText)
          ).length
          expect(result.length).toBe(matchCount)
        }
      ),
      { numRuns: 100 }
    )
  })
})
