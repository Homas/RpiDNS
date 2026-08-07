/**
 * Network Tools - Property Tests
 *
 * Property-based tests verifying correctness properties for the single-source
 * research tool definitions: target classification and the applicable-tool set
 * derived from it.
 *
 * Feature: research-tools
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

import {
  RESEARCH_TOOLS,
  classifyTarget,
  toolAccepts,
  toolsForTarget,
  TARGET_DOMAIN,
  TARGET_IP,
  TARGET_EMPTY,
  TARGET_INVALID,
  ACCEPTS_BOTH,
  ACCEPTS_DOMAIN,
  ACCEPTS_IP
} from '@/composables/useNetworkTools'

describe('Feature: research-tools, Property 13: Applicable tools come from a single source', () => {
  /**
   * Feature: research-tools, Property 13: Applicable tools come from a single source
   *
   * Validates: Requirements 7.1, 7.6, 8.10
   *
   * For any target, toolsForTarget(classifyTarget(target)) SHALL be exactly the
   * subset of RESEARCH_TOOLS whose declared input class accepts that target, in
   * definition order. Adding or removing a tool in the single source therefore
   * propagates identically to the Research tools panel and every page that
   * renders the Context_Menu, and no tool is ever offered for a target its
   * backend validator would reject.
   */

  it('yields exactly the tools whose input class accepts the target, in definition order', () => {
    fc.assert(
      fc.property(
        // Domain-like, IP-like, and free-form strings covering the target space
        fc.oneof(fc.domain(), fc.ipV4(), fc.ipV6(), fc.string()),
        (target) => {
          const kind = classifyTarget(target)
          const applicable = toolsForTarget(kind)
          const expected = RESEARCH_TOOLS.filter(tool => toolAccepts(tool, kind))

          expect(applicable.map(t => t.name)).toEqual(expected.map(t => t.name))

          // Definition order is preserved (a subsequence of RESEARCH_TOOLS).
          const allNames = RESEARCH_TOOLS.map(t => t.name)
          const indices = applicable.map(t => allNames.indexOf(t.name))
          expect(indices).toEqual([...indices].sort((a, b) => a - b))

          // An unusable target offers no tools at all.
          if (kind === TARGET_EMPTY || kind === TARGET_INVALID) {
            expect(applicable).toHaveLength(0)
          } else {
            // Every offered tool accepts this exact target class.
            for (const tool of applicable) {
              expect(
                tool.accepts === ACCEPTS_BOTH || tool.accepts === kind
              ).toBe(true)
            }
            // Every tool declared for the other class only is excluded.
            const other = kind === TARGET_DOMAIN ? ACCEPTS_IP : ACCEPTS_DOMAIN
            for (const tool of RESEARCH_TOOLS.filter(t => t.accepts === other)) {
              expect(applicable.map(t => t.name)).not.toContain(tool.name)
            }
          }
        }
      ),
      { numRuns: 200 }
    )
  })

  it('classifies targets the way the backend validators do', () => {
    expect(classifyTarget('example.com')).toBe(TARGET_DOMAIN)
    expect(classifyTarget('sub.example.co.uk.')).toBe(TARGET_DOMAIN)
    expect(classifyTarget('1.2.3.4')).toBe(TARGET_IP)
    expect(classifyTarget('2001:db8::1')).toBe(TARGET_IP)
    expect(classifyTarget('')).toBe(TARGET_EMPTY)
    expect(classifyTarget('   ')).toBe(TARGET_EMPTY)
    expect(classifyTarget('not a domain')).toBe(TARGET_INVALID)
    expect(classifyTarget('-bad-.com')).toBe(TARGET_INVALID)
    expect(classifyTarget('1.2.3.999')).toBe(TARGET_INVALID)
    expect(classifyTarget('a..b')).toBe(TARGET_INVALID)
  })

  it('offers no tool that requires the other target class', () => {
    const domainTools = toolsForTarget(TARGET_DOMAIN).map(t => t.name)
    const ipTools = toolsForTarget(TARGET_IP).map(t => t.name)

    // IP-only backend tools must never appear for a domain, and vice versa.
    expect(domainTools).not.toContain('geoip')
    expect(domainTools).not.toContain('asn')
    expect(domainTools).not.toContain('reverse_dns')
    expect(ipTools).not.toContain('nsmx')
    expect(ipTools).not.toContain('tls_cert')
    expect(ipTools).not.toContain('website_preview')
    // dig is domain-only: A/AAAA/HTTPS/TXT for an address is meaningless, and
    // reverse_dns is the tool for an address.
    expect(ipTools).not.toContain('dig')
    expect(domainTools).toContain('dig')

    // Domain-or-IP tools appear for both.
    for (const name of ['rdap', 'ping', 'traceroute']) {
      expect(domainTools).toContain(name)
      expect(ipTools).toContain(name)
    }
  })
})
