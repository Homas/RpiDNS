/**
 * Context Menu Research Actions - Unit Tests
 *
 * Example-based unit tests for the context menu composables.
 *
 * Feature: context-menu-research-actions
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { RESEARCH_LINKS, getResearchUrls } from '@/composables/useResearchLinks'

// Mock useApi for useSmartActions tests — vi.mock is hoisted automatically
const mockGet = vi.fn()
const mockDel = vi.fn()
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, del: mockDel }),
  default: () => ({ get: mockGet, del: mockDel })
}))

// ---------------------------------------------------------------------------
// 10.1 — useResearchLinks composable
// Validates: Requirements 3.1, 3.2, 3.4
// ---------------------------------------------------------------------------

describe('useResearchLinks', () => {
  describe('RESEARCH_LINKS constant', () => {
    it('has exactly 6 entries', () => {
      expect(RESEARCH_LINKS).toHaveLength(6)
    })

    it('contains the correct service names in order', () => {
      const names = RESEARCH_LINKS.map(l => l.name)
      expect(names).toEqual([
        'DuckDuckGo',
        'Google',
        'VirusTotal',
        'DomainTools Whois',
        'Robtex',
        'ThreatMiner'
      ])
    })

    it('each entry has name, urlTemplate, and icon fields', () => {
      for (const link of RESEARCH_LINKS) {
        expect(link).toHaveProperty('name')
        expect(link).toHaveProperty('urlTemplate')
        expect(link).toHaveProperty('icon')
        expect(typeof link.name).toBe('string')
        expect(typeof link.urlTemplate).toBe('string')
      }
    })

    it('each urlTemplate contains the {domain} placeholder', () => {
      for (const link of RESEARCH_LINKS) {
        expect(link.urlTemplate).toContain('{domain}')
      }
    })
  })

  describe('getResearchUrls', () => {
    it('returns 6 link objects for a known domain', () => {
      const urls = getResearchUrls('example.com')
      expect(urls).toHaveLength(6)
    })

    it('each returned object has name, url, and icon properties', () => {
      const urls = getResearchUrls('example.com')
      for (const link of urls) {
        expect(link).toHaveProperty('name')
        expect(link).toHaveProperty('url')
        expect(link).toHaveProperty('icon')
      }
    })

    it('generates correct URLs for example.com', () => {
      const urls = getResearchUrls('example.com')
      const byName = Object.fromEntries(urls.map(l => [l.name, l.url]))

      expect(byName['DuckDuckGo']).toBe('https://duckduckgo.com/?q=%22example.com%22')
      expect(byName['Google']).toBe('https://www.google.com/search?q=%22example.com%22')
      expect(byName['VirusTotal']).toBe('https://www.virustotal.com/gui/search/example.com')
      expect(byName['DomainTools Whois']).toBe('http://whois.domaintools.com/example.com')
      expect(byName['Robtex']).toBe('https://www.robtex.com/dns-lookup/example.com')
      expect(byName['ThreatMiner']).toBe('https://www.threatminer.org/domain.php?q=example.com')
    })

    it('DuckDuckGo and Google URLs include quoted search encoding (%22)', () => {
      const urls = getResearchUrls('malware.test.org')
      const ddg = urls.find(l => l.name === 'DuckDuckGo')
      const google = urls.find(l => l.name === 'Google')

      expect(ddg.url).toContain('%22malware.test.org%22')
      expect(google.url).toContain('%22malware.test.org%22')
    })

    it('replaces {domain} correctly for a subdomain', () => {
      const urls = getResearchUrls('sub.deep.example.co.uk')
      for (const link of urls) {
        expect(link.url).toContain('sub.deep.example.co.uk')
        expect(link.url).not.toContain('{domain}')
      }
    })
  })
})


// ---------------------------------------------------------------------------
// 10.2 — useSmartActions composable
// Validates: Requirements 1.4, 1.5, 2.4, 2.5, 4.1, 4.2, 4.3, 4.4
// ---------------------------------------------------------------------------

// Import after vi.mock so the mock is applied
import { useSmartActions, isLocalBlockFeed, isLocalFeed } from '@/composables/useSmartActions'

describe('useSmartActions', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockDel.mockReset()
  })

  // --- isLocalBlockFeed ---------------------------------------------------

  describe('isLocalBlockFeed', () => {
    it('returns true for block.ioc2rpz.rpidns', () => {
      expect(isLocalBlockFeed('block.ioc2rpz.rpidns')).toBe(true)
    })

    it('returns false for allow.ioc2rpz.rpidns', () => {
      expect(isLocalBlockFeed('allow.ioc2rpz.rpidns')).toBe(false)
    })

    it('returns false for a third-party feed', () => {
      expect(isLocalBlockFeed('some-third-party.feed.example')).toBe(false)
    })

    it('returns false for an empty string', () => {
      expect(isLocalBlockFeed('')).toBe(false)
    })
  })

  // --- isLocalFeed --------------------------------------------------------

  describe('isLocalFeed', () => {
    it('returns true for allow.ioc2rpz.rpidns', () => {
      expect(isLocalFeed('allow.ioc2rpz.rpidns')).toBe(true)
    })

    it('returns true for block.ioc2rpz.rpidns', () => {
      expect(isLocalFeed('block.ioc2rpz.rpidns')).toBe(true)
    })

    it('returns true for allow-ip.ioc2rpz.rpidns', () => {
      expect(isLocalFeed('allow-ip.ioc2rpz.rpidns')).toBe(true)
    })

    it('returns true for block-ip.ioc2rpz.rpidns', () => {
      expect(isLocalFeed('block-ip.ioc2rpz.rpidns')).toBe(true)
    })

    it('returns false for a third-party feed', () => {
      expect(isLocalFeed('malwaredomains.rpz.example')).toBe(false)
    })

    it('returns false for an empty string', () => {
      expect(isLocalFeed('')).toBe(false)
    })
  })

  // --- smartBlock ---------------------------------------------------------

  describe('smartBlock', () => {
    it('removes domain from allow list when domain is present', async () => {
      mockGet.mockResolvedValue({
        data: [
          { ioc: 'other.com', rowid: 1 },
          { ioc: 'evil.com', rowid: 42 }
        ]
      })
      mockDel.mockResolvedValue({ success: true })

      const { smartBlock } = useSmartActions()
      const result = await smartBlock('evil.com')

      expect(mockGet).toHaveBeenCalledWith({ req: 'whitelist', sortBy: 'ioc', sortDesc: false })
      expect(mockDel).toHaveBeenCalledWith({ req: 'whitelist', id: 42 })
      expect(result).toEqual({ action: 'removed', list: 'whitelist' })
    })

    it('returns add-ioc when domain is not in allow list', async () => {
      mockGet.mockResolvedValue({ data: [] })

      const { smartBlock } = useSmartActions()
      const result = await smartBlock('new-bad.com')

      expect(mockGet).toHaveBeenCalledWith({ req: 'whitelist', sortBy: 'ioc', sortDesc: false })
      expect(mockDel).not.toHaveBeenCalled()
      expect(result).toEqual({ action: 'add-ioc', type: 'bl' })
    })

    it('returns error when API call fails', async () => {
      mockGet.mockRejectedValue(new Error('Network error'))

      const { smartBlock } = useSmartActions()
      const result = await smartBlock('fail.com')

      expect(result).toEqual({ action: 'error', error: 'Network error' })
    })
  })

  // --- smartAllow ---------------------------------------------------------

  describe('smartAllow', () => {
    it('removes domain from block list when feed is local block feed and domain is present', async () => {
      mockGet.mockResolvedValue({
        data: [
          { ioc: 'legit.com', rowid: 7 },
          { ioc: 'false-positive.com', rowid: 99 }
        ]
      })
      mockDel.mockResolvedValue({ success: true })

      const { smartAllow } = useSmartActions()
      const result = await smartAllow('false-positive.com', 'block.ioc2rpz.rpidns')

      expect(mockGet).toHaveBeenCalledWith({ req: 'blacklist', sortBy: 'ioc', sortDesc: false })
      expect(mockDel).toHaveBeenCalledWith({ req: 'blacklist', id: 99 })
      expect(result).toEqual({ action: 'removed', list: 'blacklist' })
    })

    it('returns add-ioc for third-party feed without calling API', async () => {
      const { smartAllow } = useSmartActions()
      const result = await smartAllow('blocked-by-third-party.com', 'malwaredomains.rpz.example')

      expect(mockGet).not.toHaveBeenCalled()
      expect(mockDel).not.toHaveBeenCalled()
      expect(result).toEqual({ action: 'add-ioc', type: 'wl' })
    })

    it('returns add-ioc as fallback when local block feed but domain not found', async () => {
      mockGet.mockResolvedValue({ data: [{ ioc: 'other.com', rowid: 1 }] })

      const { smartAllow } = useSmartActions()
      const result = await smartAllow('already-removed.com', 'block.ioc2rpz.rpidns')

      expect(mockGet).toHaveBeenCalledWith({ req: 'blacklist', sortBy: 'ioc', sortDesc: false })
      expect(mockDel).not.toHaveBeenCalled()
      expect(result).toEqual({ action: 'add-ioc', type: 'wl' })
    })

    it('returns error when API call fails', async () => {
      mockGet.mockRejectedValue(new Error('Server unavailable'))

      const { smartAllow } = useSmartActions()
      const result = await smartAllow('fail.com', 'block.ioc2rpz.rpidns')

      expect(result).toEqual({ action: 'error', error: 'Server unavailable' })
    })
  })
})
