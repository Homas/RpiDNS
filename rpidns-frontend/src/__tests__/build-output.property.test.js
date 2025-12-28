/**
 * Property-Based Tests for Build Output
 * 
 * Feature: vite-vue3-migration
 * 
 * These tests verify correctness properties of the Vite build output.
 */

import { describe, it, expect, beforeAll } from 'vitest'
import * as fc from 'fast-check'
import fs from 'fs'
import path from 'path'

const DIST_DIR = path.resolve(__dirname, '../../dist')
const ASSETS_DIR = path.join(DIST_DIR, 'assets')

// CDN URL patterns that should NOT appear in bundled files
const CDN_PATTERNS = [
  /https?:\/\/unpkg\.com/gi,
  /https?:\/\/cdn\.jsdelivr\.net/gi,
  /https?:\/\/cdnjs\.cloudflare\.com/gi,
  /https?:\/\/use\.fontawesome\.com/gi,
  /https?:\/\/stackpath\.bootstrapcdn\.com/gi,
  /https?:\/\/maxcdn\.bootstrapcdn\.com/gi,
  /https?:\/\/cdn\.bootcss\.com/gi,
  /https?:\/\/ajax\.googleapis\.com/gi,
  /https?:\/\/code\.jquery\.com/gi
]

// Content hash pattern: filename-[8+ hex chars].(js|css)
const CONTENT_HASH_PATTERN = /^.+-[a-zA-Z0-9_-]{8,}\.(js|css)$/

describe('Build Output Properties', () => {
  let assetFiles = []
  let jsFiles = []
  let cssFiles = []

  beforeAll(() => {
    // Verify dist directory exists
    if (!fs.existsSync(DIST_DIR)) {
      throw new Error(`Dist directory not found: ${DIST_DIR}. Run 'npm run build' first.`)
    }
    if (!fs.existsSync(ASSETS_DIR)) {
      throw new Error(`Assets directory not found: ${ASSETS_DIR}. Run 'npm run build' first.`)
    }

    // Get all asset files
    assetFiles = fs.readdirSync(ASSETS_DIR)
    jsFiles = assetFiles.filter(f => f.endsWith('.js'))
    cssFiles = assetFiles.filter(f => f.endsWith('.css'))
  })

  /**
   * Property 1: Build Output Contains Content Hashes
   * 
   * *For any* production build output, all JavaScript and CSS asset filenames 
   * SHALL contain a content hash pattern (e.g., `index-[a-f0-9]+\.(js|css)`).
   * 
   * **Validates: Requirements 3.3**
   * 
   * Tag: Feature: vite-vue3-migration, Property 1: Build Output Contains Content Hashes
   */
  describe('Property 1: Build Output Contains Content Hashes', () => {
    it('all JS files should have content hashes in filenames', () => {
      fc.assert(
        fc.property(
          fc.constantFrom(...jsFiles),
          (filename) => {
            const hasHash = CONTENT_HASH_PATTERN.test(filename)
            expect(hasHash).toBe(true)
            return hasHash
          }
        ),
        { numRuns: Math.max(100, jsFiles.length * 10) }
      )
    })

    it('all CSS files should have content hashes in filenames', () => {
      fc.assert(
        fc.property(
          fc.constantFrom(...cssFiles),
          (filename) => {
            const hasHash = CONTENT_HASH_PATTERN.test(filename)
            expect(hasHash).toBe(true)
            return hasHash
          }
        ),
        { numRuns: Math.max(100, cssFiles.length * 10) }
      )
    })

    it('should have at least one JS and one CSS file with content hashes', () => {
      expect(jsFiles.length).toBeGreaterThan(0)
      expect(cssFiles.length).toBeGreaterThan(0)
      
      const jsWithHashes = jsFiles.filter(f => CONTENT_HASH_PATTERN.test(f))
      const cssWithHashes = cssFiles.filter(f => CONTENT_HASH_PATTERN.test(f))
      
      expect(jsWithHashes.length).toBe(jsFiles.length)
      expect(cssWithHashes.length).toBe(cssFiles.length)
    })
  })


  /**
   * Property 2: No External Network Dependencies
   * 
   * *For any* production build output, the bundled JavaScript and CSS files 
   * SHALL NOT contain references to external CDN URLs (unpkg.com, cdn.jsdelivr.net, 
   * use.fontawesome.com, etc.).
   * 
   * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7**
   * 
   * Tag: Feature: vite-vue3-migration, Property 2: No External Network Dependencies
   */
  describe('Property 2: No External Network Dependencies', () => {
    it('JS files should not contain external CDN URLs', () => {
      const allJsFiles = jsFiles.map(f => ({
        name: f,
        content: fs.readFileSync(path.join(ASSETS_DIR, f), 'utf-8')
      }))

      fc.assert(
        fc.property(
          fc.constantFrom(...allJsFiles),
          fc.constantFrom(...CDN_PATTERNS),
          (file, pattern) => {
            const matches = file.content.match(pattern)
            if (matches) {
              console.log(`Found CDN URL in ${file.name}: ${matches[0]}`)
            }
            expect(matches).toBeNull()
            return matches === null
          }
        ),
        { numRuns: Math.max(100, allJsFiles.length * CDN_PATTERNS.length) }
      )
    })

    it('CSS files should not contain external CDN URLs', () => {
      const allCssFiles = cssFiles.map(f => ({
        name: f,
        content: fs.readFileSync(path.join(ASSETS_DIR, f), 'utf-8')
      }))

      fc.assert(
        fc.property(
          fc.constantFrom(...allCssFiles),
          fc.constantFrom(...CDN_PATTERNS),
          (file, pattern) => {
            const matches = file.content.match(pattern)
            if (matches) {
              console.log(`Found CDN URL in ${file.name}: ${matches[0]}`)
            }
            expect(matches).toBeNull()
            return matches === null
          }
        ),
        { numRuns: Math.max(100, allCssFiles.length * CDN_PATTERNS.length) }
      )
    })

    it('should verify all bundled files are free of external dependencies', () => {
      const allFiles = [...jsFiles, ...cssFiles]
      let externalDepsFound = []

      for (const filename of allFiles) {
        const content = fs.readFileSync(path.join(ASSETS_DIR, filename), 'utf-8')
        for (const pattern of CDN_PATTERNS) {
          const matches = content.match(pattern)
          if (matches) {
            externalDepsFound.push({ file: filename, url: matches[0] })
          }
        }
      }

      expect(externalDepsFound).toEqual([])
    })
  })
})
