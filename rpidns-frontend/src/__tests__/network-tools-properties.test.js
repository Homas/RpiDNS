/**
 * Network Tools - Property Tests
 *
 * Property-based tests verifying correctness properties for the
 * single-source network tool definitions and context-menu tool actions.
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import { NETWORK_TOOLS, getToolActions } from '@/composables/useNetworkTools'

describe('Feature: research-tools, Property 13: Context-menu tool actions come from a single source', () => {
  /**
   * Feature: research-tools, Property 13: Context-menu tool actions come from a single source
   *
   * Validates: Requirements 7.1, 7.6
   *
   * For any network-tool definition list and any domain, getToolActions(domain)
   * SHALL produce exactly one action per defined tool, each targeting that domain,
   * so adding or removing a tool in the single source propagates identically to
   * every page that renders the Context_Menu.
   */

  it('getToolActions yields exactly one action per defined tool, each targeting the domain', () => {
    fc.assert(
      fc.property(
        // Arbitrary domain-like and free-form strings covering the target space
        fc.oneof(fc.domain(), fc.string()),
        (domain) => {
          const actions = getToolActions(domain)

          // Exactly one action per defined tool
          expect(actions).toHaveLength(NETWORK_TOOLS.length)

          // Names match the single-source NETWORK_TOOLS names, in order
          const actionNames = actions.map(a => a.name)
          const toolNames = NETWORK_TOOLS.map(t => t.name)
          expect(actionNames).toEqual(toolNames)

          // Each action carries the defined tool's label (in order) and targets the domain
          actions.forEach((action, i) => {
            expect(action.name).toBe(NETWORK_TOOLS[i].name)
            expect(action.label).toBe(NETWORK_TOOLS[i].label)
            expect(action.domain).toBe(domain)
          })
        }
      ),
      { numRuns: 100 }
    )
  })
})
