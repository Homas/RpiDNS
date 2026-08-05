/**
 * Newly-Seen-Queries Aggregation - Property Tests
 *
 * Property-based tests verifying the client-side reference aggregation used by
 * the Newly seen queries view (aggregateUniqueQueries) against an independent
 * reference implementation.
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import { aggregateUniqueQueries } from '@/composables/useNetworkTools'

// Small FQDN pool so records collide and grouping actually occurs.
const FQDN_POOL = ['a.example.com', 'b.example.net', 'c.example.org', 'd.example.io']

// Actions include 'allowed' plus several non-allowed values so the filter is exercised.
const ACTION_POOL = ['allowed', 'blocked', 'redirected', 'allowed', '']

/**
 * Independent reference: keep only FQDNs that are FIRST SEEN in the range, i.e.
 * that have no record of any action before `start`. Then filter to
 * action==='allowed' AND start<=dt<=end (inclusive on both boundaries), group by
 * fqdn, sum cnt (1 when absent / non-finite), and take the maximum dt as
 * last_seen. Written in a deliberately different style (filter + reduce) from
 * the implementation under test. Returns a Map keyed by fqdn.
 */
function referenceAggregate(records, start, end) {
  // An fqdn is disqualified if it appears at all (any action) before the range.
  const priorFqdns = records
    .filter(r => r && r.dt < start)
    .map(r => r.fqdn)

  const allowed = records.filter(
    r => r && r.action === 'allowed' && r.dt >= start && r.dt <= end &&
         !priorFqdns.includes(r.fqdn)
  )
  const byFqdn = new Map()
  for (const r of allowed) {
    const contribution =
      typeof r.cnt === 'number' && Number.isFinite(r.cnt) ? r.cnt : 1
    if (!byFqdn.has(r.fqdn)) {
      byFqdn.set(r.fqdn, { fqdn: r.fqdn, cnt: 0, last_seen: -Infinity })
    }
    const group = byFqdn.get(r.fqdn)
    group.cnt += contribution
    group.last_seen = Math.max(group.last_seen, r.dt)
  }
  return byFqdn
}

describe('Feature: research-tools, Property 2: Unique-queries aggregation is correct', () => {
  /**
   * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.12
   *
   * For any set of query records and any inclusive [start, end] time range, the
   * aggregation result SHALL equal the reference aggregation that (a) keeps only
   * rows with action='allowed', (b) keeps only rows whose timestamp is within the
   * inclusive range, (c) groups by FQDN so no FQDN repeats, (d) reports for
   * each FQDN the total in-range allowed count and the maximum in-range timestamp
   * as "last seen", and (e) excludes any FQDN that has a record of any action
   * before the range start (first-seen / newly-observed semantics).
   */

  // A single query record: fqdn from a small pool, mixed actions, integer
  // timestamp, and an optional integer count (undefined models an absent cnt).
  const recordArb = fc.record({
    fqdn: fc.constantFrom(...FQDN_POOL),
    action: fc.constantFrom(...ACTION_POOL),
    dt: fc.integer({ min: 0, max: 20 }),
    cnt: fc.option(fc.integer({ min: 1, max: 100 }), { nil: undefined })
  })

  // A [start, end] range drawn from the same small integer domain as dt, so
  // timestamps deliberately straddle the boundaries (equal to start, equal to
  // end, and just outside on either side).
  const rangeArb = fc
    .tuple(fc.integer({ min: 0, max: 20 }), fc.integer({ min: 0, max: 20 }))
    .map(([a, b]) => (a <= b ? [a, b] : [b, a]))

  it('aggregateUniqueQueries equals the independent reference aggregation', () => {
    fc.assert(
      fc.property(
        fc.array(recordArb, { minLength: 0, maxLength: 50 }),
        rangeArb,
        (records, [start, end]) => {
          const actual = aggregateUniqueQueries(records, start, end)
          const reference = referenceAggregate(records, start, end)

          // Result is an array.
          expect(Array.isArray(actual)).toBe(true)

          // No fqdn repeats (Req 2.1): array length equals number of distinct fqdns.
          const actualByFqdn = new Map()
          for (const row of actual) {
            expect(actualByFqdn.has(row.fqdn)).toBe(false)
            actualByFqdn.set(row.fqdn, row)
          }

          // Same set of fqdns as the reference.
          expect(actualByFqdn.size).toBe(reference.size)
          const actualKeys = [...actualByFqdn.keys()].sort()
          const referenceKeys = [...reference.keys()].sort()
          expect(actualKeys).toEqual(referenceKeys)

          // Per-fqdn: same total count (Req 2.5) and same max last_seen (Req 2.5).
          for (const [fqdn, refGroup] of reference) {
            const row = actualByFqdn.get(fqdn)
            expect(row).toBeDefined()
            expect(row.cnt).toBe(refGroup.cnt)
            expect(row.last_seen).toBe(refGroup.last_seen)
          }
        }
      ),
      { numRuns: 100 }
    )
  })
})

describe('Unique-queries first-seen rule (Req 2.12)', () => {
  // Deterministic checks so the first-seen exclusion is explicitly pinned and the
  // property above can never pass vacuously.

  it('keeps an FQDN whose only activity is inside the range', () => {
    const rows = aggregateUniqueQueries([
      { fqdn: 'new.example', action: 'allowed', dt: 12 },
      { fqdn: 'new.example', action: 'allowed', dt: 15 }
    ], 10, 20)

    expect(rows).toHaveLength(1)
    expect(rows[0].fqdn).toBe('new.example')
    expect(rows[0].cnt).toBe(2)
    expect(rows[0].last_seen).toBe(15)
  })

  it('excludes an FQDN that was also requested before the range start', () => {
    const rows = aggregateUniqueQueries([
      { fqdn: 'old.example', action: 'allowed', dt: 5 },  // before the range
      { fqdn: 'old.example', action: 'allowed', dt: 12 }, // inside the range
      { fqdn: 'new.example', action: 'allowed', dt: 12 }
    ], 10, 20)

    expect(rows.map(r => r.fqdn)).toEqual(['new.example'])
  })

  it('excludes an FQDN whose only prior activity was blocked (still a prior request)', () => {
    const rows = aggregateUniqueQueries([
      { fqdn: 'seen.example', action: 'blocked', dt: 5 },
      { fqdn: 'seen.example', action: 'allowed', dt: 12 }
    ], 10, 20)

    expect(rows).toHaveLength(0)
  })
})
