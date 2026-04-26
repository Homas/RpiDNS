/**
 * Context Menu Research Actions - Property Tests
 *
 * Property-based tests verifying correctness properties for the
 * context menu smart actions and research link features.
 *
 * Feature: context-menu-research-actions
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import * as fc from 'fast-check'

// Mock useApi before importing useSmartActions
const mockGet = vi.fn()
const mockDel = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    del: mockDel,
    post: vi.fn(),
    put: vi.fn()
  })
}))

import { useSmartActions } from '@/composables/useSmartActions'
import { getResearchUrls, RESEARCH_LINKS } from '@/composables/useResearchLinks'

describe('Feature: context-menu-research-actions, Property 1: Smart Block Action Correctness', () => {
  /**
   * Validates: Requirements 1.4, 1.5, 4.2
   *
   * For any valid domain string and any allow-list state (a list of IOC entries),
   * the smartBlock function SHALL return { action: 'removed', list: 'whitelist' }
   * if the domain exists in the allow list, or { action: 'add-ioc', type: 'bl' }
   * if the domain does not exist in the allow list.
   */

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('smartBlock returns correct action based on allow-list membership', async () => {
    const { smartBlock } = useSmartActions()

    await fc.assert(
      fc.asyncProperty(
        // Generate a random domain string
        fc.domain(),
        // Generate a random allow list of IOC entries (array of { ioc, rowid })
        fc.array(
          fc.record({
            ioc: fc.domain(),
            rowid: fc.integer({ min: 1, max: 10000 })
          }),
          { minLength: 0, maxLength: 20 }
        ),
        // Generate a boolean to decide if the target domain should be in the allow list
        fc.boolean(),
        async (domain, baseAllowList, domainInList) => {
          // Reset mocks for each iteration
          mockGet.mockReset()
          mockDel.mockReset()

          // Build the allow list: optionally include the target domain
          let allowList
          if (domainInList) {
            const rowid = Math.floor(Math.random() * 10000) + 1
            // Filter out any accidental matches, then add the target domain
            allowList = [
              ...baseAllowList.filter(e => e.ioc !== domain),
              { ioc: domain, rowid }
            ]
          } else {
            // Ensure the target domain is NOT in the list
            allowList = baseAllowList.filter(e => e.ioc !== domain)
          }

          // Mock the API: GET whitelist returns the allow list
          mockGet.mockResolvedValue({ data: allowList })
          // Mock DELETE to succeed
          mockDel.mockResolvedValue({ success: true })

          const result = await smartBlock(domain)

          if (allowList.some(e => e.ioc === domain)) {
            // Domain is in allow list → should be removed
            expect(result).toEqual({ action: 'removed', list: 'whitelist' })
            expect(mockDel).toHaveBeenCalled()
          } else {
            // Domain is not in allow list → should signal add to block list
            expect(result).toEqual({ action: 'add-ioc', type: 'bl' })
            expect(mockDel).not.toHaveBeenCalled()
          }
        }
      ),
      { numRuns: 100 }
    )
  })
})

describe('Feature: context-menu-research-actions, Property 2: Smart Allow Action Correctness', () => {
  /**
   * Validates: Requirements 2.4, 2.5, 4.1
   *
   * For any valid domain string and any feed name, the smartAllow function SHALL
   * return { action: 'removed', list: 'blacklist' } if the feed is a local block
   * feed and the domain exists in the block list, or { action: 'add-ioc', type: 'wl' }
   * if the feed is a third-party feed.
   */

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('smartAllow returns correct action based on feed type and block-list membership', async () => {
    const { smartAllow } = useSmartActions()

    // Arbitrary for generating feed names: either the local block feed or a random third-party feed
    const feedNameArb = fc.oneof(
      fc.constant('block.ioc2rpz.rpidns'),
      fc.stringMatching(/^[a-z][a-z0-9-]{2,20}\.[a-z]{2,6}$/).filter(
        name => name !== 'block.ioc2rpz.rpidns'
      )
    )

    await fc.assert(
      fc.asyncProperty(
        // Generate a random domain string
        fc.domain(),
        // Generate a feed name (local block feed or third-party)
        feedNameArb,
        // Generate a random block list of IOC entries
        fc.array(
          fc.record({
            ioc: fc.domain(),
            rowid: fc.integer({ min: 1, max: 10000 })
          }),
          { minLength: 0, maxLength: 20 }
        ),
        // Whether the target domain should be in the block list (only relevant for local block feed)
        fc.boolean(),
        async (domain, feedName, baseBlockList, domainInList) => {
          // Reset mocks for each iteration
          mockGet.mockReset()
          mockDel.mockReset()

          const isLocalBlock = feedName === 'block.ioc2rpz.rpidns'

          if (isLocalBlock) {
            // Build the block list: optionally include the target domain
            let blockList
            if (domainInList) {
              const rowid = Math.floor(Math.random() * 10000) + 1
              blockList = [
                ...baseBlockList.filter(e => e.ioc !== domain),
                { ioc: domain, rowid }
              ]
            } else {
              blockList = baseBlockList.filter(e => e.ioc !== domain)
            }

            // Mock the API: GET blacklist returns the block list
            mockGet.mockResolvedValue({ data: blockList })
            // Mock DELETE to succeed
            mockDel.mockResolvedValue({ success: true })

            const result = await smartAllow(domain, feedName)

            if (blockList.some(e => e.ioc === domain)) {
              // Domain is in block list → should be removed
              expect(result).toEqual({ action: 'removed', list: 'blacklist' })
              expect(mockDel).toHaveBeenCalled()
            } else {
              // Domain not in block list (edge case) → fallback to add-ioc
              expect(result).toEqual({ action: 'add-ioc', type: 'wl' })
            }

            // API should have been called for local block feed
            expect(mockGet).toHaveBeenCalled()
          } else {
            // Third-party feed → should return add-ioc without any API calls
            const result = await smartAllow(domain, feedName)

            expect(result).toEqual({ action: 'add-ioc', type: 'wl' })
            expect(mockGet).not.toHaveBeenCalled()
            expect(mockDel).not.toHaveBeenCalled()
          }
        }
      ),
      { numRuns: 100 }
    )
  })
})

describe('Feature: context-menu-research-actions, Property 3: Research Link URL Generation', () => {
  /**
   * Validates: Requirements 3.1, 3.2
   *
   * For any valid domain string, the getResearchUrls function SHALL return
   * exactly 6 research link objects, each containing a url field that includes
   * the domain string, and a name field matching one of the predefined service names.
   */

  const EXPECTED_SERVICE_NAMES = [
    'DuckDuckGo',
    'Google',
    'VirusTotal',
    'DomainTools Whois',
    'Robtex',
    'ThreatMiner'
  ]

  it('getResearchUrls returns exactly 6 objects with correct names and URLs containing the domain', () => {
    fc.assert(
      fc.property(
        fc.domain(),
        (domain) => {
          const results = getResearchUrls(domain)

          // Exactly 6 objects returned
          expect(results).toHaveLength(6)

          // Each object has name, url, and icon properties
          for (const link of results) {
            expect(link).toHaveProperty('name')
            expect(link).toHaveProperty('url')
            expect(link).toHaveProperty('icon')
          }

          // Each url contains the domain string
          for (const link of results) {
            expect(link.url).toContain(domain)
          }

          // The set of name values matches the predefined service names
          const names = results.map(link => link.name)
          expect(names).toEqual(EXPECTED_SERVICE_NAMES)
        }
      ),
      { numRuns: 100 }
    )
  })
})


describe('Feature: context-menu-research-actions, Property 4: Context Menu Viewport Clamping', () => {
  /**
   * Validates: Requirements 7.2
   *
   * For any cursor position (x, y), viewport dimensions (width, height), and
   * menu dimensions (menuWidth, menuHeight), the computed menu position SHALL
   * ensure that the menu's bounding box [posX, posY, posX + menuWidth, posY + menuHeight]
   * is fully contained within [0, 0, viewportWidth, viewportHeight].
   */

  /**
   * Pure function replicating the clamping algorithm from ContextMenu.vue:
   *   1. Start with position (x, y)
   *   2. If x + menuWidth > viewportWidth: newX = x - (menuWidth - (viewportWidth - x))
   *   3. If y + menuHeight > viewportHeight: newY = y - (menuHeight - (viewportHeight - y))
   *   4. Clamp to Math.max(0, newX) and Math.max(0, newY)
   */
  function clampMenuPosition(x, y, menuWidth, menuHeight, viewportWidth, viewportHeight) {
    let newX = x
    let newY = y

    // If menu overflows right edge, shift left
    if (newX + menuWidth > viewportWidth) {
      newX = newX - (menuWidth - (viewportWidth - newX))
    }
    // If menu overflows bottom edge, shift up
    if (newY + menuHeight > viewportHeight) {
      newY = newY - (menuHeight - (viewportHeight - newY))
    }

    // Ensure we don't go negative
    newX = Math.max(0, newX)
    newY = Math.max(0, newY)

    return { posX: newX, posY: newY }
  }

  it('clamped menu position keeps the menu fully within the viewport bounds', () => {
    // Use a chain so menu dimensions are always <= viewport dimensions
    const arb = fc.integer({ min: 100, max: 2000 }).chain(viewportWidth =>
      fc.integer({ min: 100, max: 2000 }).chain(viewportHeight =>
        fc.tuple(
          fc.constant(viewportWidth),
          fc.constant(viewportHeight),
          fc.integer({ min: 50, max: Math.min(400, viewportWidth) }),
          fc.integer({ min: 50, max: Math.min(400, viewportHeight) }),
          fc.integer({ min: 0, max: viewportWidth }),
          fc.integer({ min: 0, max: viewportHeight })
        )
      )
    )

    fc.assert(
      fc.property(
        arb,
        ([viewportWidth, viewportHeight, menuWidth, menuHeight, x, y]) => {
          const { posX, posY } = clampMenuPosition(
            x, y, menuWidth, menuHeight, viewportWidth, viewportHeight
          )

          // The menu's left edge must be >= 0
          expect(posX).toBeGreaterThanOrEqual(0)
          // The menu's top edge must be >= 0
          expect(posY).toBeGreaterThanOrEqual(0)
          // The menu's right edge must be <= viewportWidth
          expect(posX + menuWidth).toBeLessThanOrEqual(viewportWidth)
          // The menu's bottom edge must be <= viewportHeight
          expect(posY + menuHeight).toBeLessThanOrEqual(viewportHeight)
        }
      ),
      { numRuns: 100 }
    )
  })
})
