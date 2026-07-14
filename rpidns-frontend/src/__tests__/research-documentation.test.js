/**
 * Research Documentation Verification Tests
 *
 * Verifies that the Research feature is documented in the frontend docs,
 * the backend API docs, and the built-in Help content, per Requirement 10.
 *
 * Feature: research-tools, Documentation verification
 */
import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

// Resolve project root (rpidns-frontend is one level down from repo root)
const PROJECT_ROOT = path.resolve(__dirname, '..', '..', '..')

/** Helper: read a file relative to project root */
function readProjectFile(relPath) {
  return fs.readFileSync(path.join(PROJECT_ROOT, relPath), 'utf-8')
}

describe('Feature: research-tools, Documentation verification', () => {

  // ─── docs/frontend.md (Req 10.4) ─────────────────────────────────────
  describe('docs/frontend.md documents the Research page (Req 10.4)', () => {
    const frontendDoc = readProjectFile('docs/frontend.md')

    it('mentions the Research component / Research.vue', () => {
      // Component name and its source file
      expect(frontendDoc).toMatch(/Research\.vue/)
      expect(frontendDoc).toMatch(/###\s+Research\b/)
    })

    it('documents the navigation position (after "RPZ log", before "Admin")', () => {
      // Robust to minor wording: require "after ... RPZ log ... before ... Admin"
      const positionRegex = /after[^.]*RPZ ?log[^.]*before[^.]*Admin/i
      expect(frontendDoc).toMatch(positionRegex)
    })

    it('documents the Context_Menu integration (Tools group / useNetworkTools)', () => {
      expect(frontendDoc).toMatch(/Context_?Menu/i)
      expect(frontendDoc).toMatch(/useNetworkTools/)
      // The network tool actions are the shared "Tools" group
      expect(frontendDoc).toMatch(/Tools['"]? group/i)
    })
  })

  // ─── docs/backend-api.md (Req 10.5) ──────────────────────────────────
  describe('docs/backend-api.md documents each Research endpoint (Req 10.5)', () => {
    const backendDoc = readProjectFile('docs/backend-api.md')

    const endpoints = ['research_unique', 'research_tables', 'research_sql', 'research_tool']

    it.each(endpoints)('documents the %s endpoint by name', (endpoint) => {
      expect(backendDoc).toContain(endpoint)
    })

    it('documents accepted inputs, validation, and error responses', () => {
      expect(backendDoc).toMatch(/Accepted inputs/i)
      expect(backendDoc).toMatch(/Validation behavior/i)
      expect(backendDoc).toMatch(/Error responses/i)
    })

    it('documents authentication-required behavior', () => {
      expect(backendDoc).toMatch(/authentication[- ]required/i)
    })

    it('documents the read-only / SELECT-only query behavior', () => {
      expect(backendDoc).toMatch(/read-only/i)
      expect(backendDoc).toMatch(/SELECT/)
    })

    it('documents the execution timeout behavior', () => {
      expect(backendDoc).toMatch(/timeout/i)
      expect(backendDoc).toMatch(/30-second|30 second/i)
    })
  })

  // ─── HelpContent.vue (Req 10.1, 10.2, 10.3) ──────────────────────────
  describe('HelpContent.vue documents the Research page (Req 10.1, 10.2, 10.3)', () => {
    const help = readProjectFile('rpidns-frontend/src/components/HelpContent.vue')

    // Req 10.1 — separately identifiable content items
    it('contains a Research page section (Req 10.1)', () => {
      expect(help).toMatch(/id="research"/)
      expect(help).toMatch(/Research/)
    })

    it('describes the Unique Queries view (Req 10.1)', () => {
      expect(help).toMatch(/Unique Queries/i)
    })

    it('describes the SQL Query Tool (Req 10.1)', () => {
      expect(help).toMatch(/SQL Query Tool/i)
    })

    it('describes the CSV copy capability (Req 10.1)', () => {
      expect(help).toMatch(/Copy (?:Dataset )?as CSV|CSV/i)
      expect(help).toMatch(/clipboard/i)
    })

    it('describes the Network Tools including RDAP/WHOIS, dig, ping, traceroute (Req 10.1)', () => {
      expect(help).toMatch(/Network Tools/i)
      expect(help).toMatch(/RDAP\/?WHOIS/i)
      expect(help).toMatch(/\bdig\b/)
      expect(help).toMatch(/\bping\b/)
      expect(help).toMatch(/\btraceroute\b/)
    })

    // Req 10.2 — SQL tool read-only / SELECT-only and rejects writes/multi-statements
    it('states the SQL tool is read-only and SELECT-only (Req 10.2)', () => {
      expect(help).toMatch(/read-only/i)
      expect(help).toMatch(/SELECT/)
    })

    it('states writes and multi-statement submissions are rejected without executing (Req 10.2)', () => {
      // rejection without execution
      expect(help).toMatch(/rejected without[^<]*execut/i)
      // multi-statement submissions specifically called out
      expect(help).toMatch(/multi-statement/i)
    })

    // Req 10.3 — Network tools validate input, bounded execution w/ timeout, no state change
    it('states Network Tools validate input (Req 10.3)', () => {
      expect(help).toMatch(/validate[s]?[^<]*input/i)
    })

    it('states Network Tools apply bounded execution with termination on timeout (Req 10.3)', () => {
      expect(help).toMatch(/bounded execution/i)
      expect(help).toMatch(/terminated on timeout|timeout/i)
    })

    it('states Network Tools do not modify database or system state (Req 10.3)', () => {
      expect(help).toMatch(/do not modify[^<]*(database|system)/i)
    })
  })
})
