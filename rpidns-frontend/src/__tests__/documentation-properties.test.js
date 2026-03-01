/**
 * Documentation Property Tests
 *
 * Property-based and unit tests verifying that project documentation
 * is consistent with the actual codebase.
 *
 * Feature: project-documentation
 */
import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'
import * as fs from 'fs'
import * as path from 'path'

// Resolve project root (rpidns-frontend is one level down)
const PROJECT_ROOT = path.resolve(__dirname, '..', '..', '..')

/** Helper: read a file relative to project root */
function readProjectFile(relPath) {
  return fs.readFileSync(path.join(PROJECT_ROOT, relPath), 'utf-8')
}

/** Helper: check if a path exists relative to project root */
function projectPathExists(relPath) {
  return fs.existsSync(path.join(PROJECT_ROOT, relPath))
}

/** Helper: list files in a directory (non-recursive) */
function listDir(relPath) {
  const full = path.join(PROJECT_ROOT, relPath)
  if (!fs.existsSync(full)) return []
  return fs.readdirSync(full).filter(f => !f.startsWith('.'))
}

/** Helper: recursively list files matching a pattern */
function listFilesRecursive(relDir, ext) {
  const results = []
  const full = path.join(PROJECT_ROOT, relDir)
  if (!fs.existsSync(full)) return results
  function walk(dir, rel) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const entryRel = path.join(rel, entry.name)
      if (entry.isDirectory()) {
        walk(path.join(dir, entry.name), entryRel)
      } else if (entry.name.endsWith(ext)) {
        results.push(entryRel)
      }
    }
  }
  walk(full, '')
  return results
}

// ─── Artifact extraction helpers ───────────────────────────────────────

/** Extract script filenames from scripts/ directory */
function getScriptFiles() {
  return listDir('scripts').filter(f => f.endsWith('.sh') || f.endsWith('.php'))
}

/** Extract script names mentioned in README Scripts table */
function getReadmeScripts() {
  const readme = readProjectFile('README.md')
  const scriptNames = []
  // Match backtick-wrapped script names in the Scripts section
  const scriptsSection = readme.split(/^## Scripts$/m)[1]
  if (!scriptsSection) return scriptNames
  const sectionContent = scriptsSection.split(/^## /m)[0]
  const matches = sectionContent.matchAll(/`([^`]+\.(sh|php))`/g)
  for (const m of matches) {
    scriptNames.push(m[1])
  }
  return [...new Set(scriptNames)]
}

/** Extract environment variable names from docker-compose.yml */
function getDockerComposeEnvVars() {
  const compose = readProjectFile('rpidns-docker/docker-compose.yml')
  const vars = new Set()
  // Match environment variable definitions like RPIDNS_HOSTNAME, PHP_FPM_VERSION
  const matches = compose.matchAll(/- ([A-Z][A-Z0-9_]+)=/g)
  for (const m of matches) {
    vars.add(m[1])
  }
  // Also match ${VAR_NAME:-default} patterns
  const refMatches = compose.matchAll(/\$\{([A-Z][A-Z0-9_]+)(?::?-[^}]*)?\}/g)
  for (const m of refMatches) {
    vars.add(m[1])
  }
  return [...vars]
}

/** Extract API endpoint request names from rpidata.php */
function getApiEndpoints() {
  const php = readProjectFile('www/rpi_admin/rpidata.php')
  const endpoints = new Set()
  // Match case "METHOD request_name": patterns
  const matches = php.matchAll(/case\s+"((?:GET|POST|PUT|DELETE)\s+\w+)"\s*:/g)
  for (const m of matches) {
    endpoints.add(m[1])
  }
  return [...endpoints]
}

/** Extract request names (without HTTP method) from API endpoints */
function getApiRequestNames() {
  return [...new Set(getApiEndpoints().map(e => e.split(' ')[1]))]
}

/** Get all .vue component filenames (without extension) from components/ */
function getVueComponentNames() {
  const files = listFilesRecursive('rpidns-frontend/src/components', '.vue')
  return files.map(f => path.basename(f, '.vue'))
}

/** Get all composable filenames (without extension) from composables/ */
function getComposableNames() {
  const files = listDir('rpidns-frontend/src/composables')
  return files.filter(f => f.endsWith('.js')).map(f => path.basename(f, '.js'))
}

/** Extract CREATE TABLE names from init_db.php */
function getDbTableNames() {
  const php = readProjectFile('scripts/init_db.php')
  const tables = new Set()
  const matches = php.matchAll(/create\s+table\s+if\s+not\s+exists\s+(\w+)/gi)
  for (const m of matches) {
    tables.add(m[1])
  }
  return [...tables]
}

/** Extract CREATE INDEX names from init_db.php */
function getDbIndexNames() {
  const php = readProjectFile('scripts/init_db.php')
  const indexes = new Set()
  const matches = php.matchAll(/create\s+index\s+if\s+not\s+exists\s+(\w+)/gi)
  for (const m of matches) {
    indexes.add(m[1])
  }
  return [...indexes]
}

/** Get all docs/ markdown files */
function getDocsFiles() {
  return listDir('docs').filter(f => f.endsWith('.md'))
}

/** Extract file paths referenced in a markdown document */
function extractReferencedPaths(content) {
  const paths = new Set()
  // Match paths like `some/path/file.ext` (backtick-wrapped)
  const backtickMatches = content.matchAll(/`([a-zA-Z0-9_./-]+\/[a-zA-Z0-9_./-]+)`/g)
  for (const m of backtickMatches) {
    const p = m[1]
    // Filter out things that are clearly not file paths
    if (p.includes('://') || p.startsWith('http') || p.includes('@')) continue
    // Must have a file extension or be a known directory
    if (/\.\w+$/.test(p) || p.endsWith('/')) {
      paths.add(p)
    }
  }
  return [...paths]
}

// ─── Property Tests ────────────────────────────────────────────────────

describe('Feature: project-documentation', () => {

  describe('Property 1: Script listing completeness', () => {
    /**
     * Validates: Requirements 1.2
     *
     * For any script file in scripts/, that filename should appear in README.md
     * scripts section, and vice versa.
     */
    it('every script file in scripts/ appears in README', () => {
      const scriptFiles = getScriptFiles()
      const readmeScripts = getReadmeScripts()
      expect(scriptFiles.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...scriptFiles),
          (scriptFile) => {
            expect(readmeScripts).toContain(scriptFile)
          }
        ),
        { numRuns: Math.max(100, scriptFiles.length * 10) }
      )
    })

    it('every script listed in README exists in scripts/ directory', () => {
      const scriptFiles = getScriptFiles()
      const readmeScripts = getReadmeScripts()
      expect(readmeScripts.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...readmeScripts),
          (scriptName) => {
            expect(scriptFiles).toContain(scriptName)
          }
        ),
        { numRuns: Math.max(100, readmeScripts.length * 10) }
      )
    })
  })


  describe('Property 2: README environment variable consistency', () => {
    /**
     * Validates: Requirements 1.3
     *
     * For any environment variable defined in docker-compose.yml,
     * that variable name should appear in the README.md environment variables section.
     */
    it('every docker-compose env var appears in README', () => {
      const envVars = getDockerComposeEnvVars()
      const readme = readProjectFile('README.md')
      expect(envVars.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...envVars),
          (envVar) => {
            expect(readme).toContain(envVar)
          }
        ),
        { numRuns: Math.max(100, envVars.length * 10) }
      )
    })
  })

  describe('Property 3: API endpoint documentation coverage', () => {
    /**
     * Validates: Requirements 3.1, 3.2
     *
     * For any request name handled in rpidata.php, that endpoint name
     * should appear in docs/backend-api.md.
     */
    it('every API request name appears in backend-api.md', () => {
      const requestNames = getApiRequestNames()
      const backendDoc = readProjectFile('docs/backend-api.md')
      expect(requestNames.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...requestNames),
          (reqName) => {
            expect(backendDoc).toContain(reqName)
          }
        ),
        { numRuns: Math.max(100, requestNames.length * 10) }
      )
    })
  })

  describe('Property 4: Frontend component documentation coverage', () => {
    /**
     * Validates: Requirements 4.1, 4.2, 4.3, 4.4
     *
     * For any .vue file in components/ or .js file in composables/,
     * the component/composable name should appear in docs/frontend.md.
     */
    it('every Vue component appears in frontend.md', () => {
      const componentNames = getVueComponentNames()
      const frontendDoc = readProjectFile('docs/frontend.md')
      expect(componentNames.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...componentNames),
          (name) => {
            expect(frontendDoc).toContain(name)
          }
        ),
        { numRuns: Math.max(100, componentNames.length * 10) }
      )
    })

    it('every composable appears in frontend.md', () => {
      const composableNames = getComposableNames()
      const frontendDoc = readProjectFile('docs/frontend.md')
      expect(composableNames.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...composableNames),
          (name) => {
            expect(frontendDoc).toContain(name)
          }
        ),
        { numRuns: Math.max(100, composableNames.length * 10) }
      )
    })
  })

  describe('Property 5: Database schema documentation coverage', () => {
    /**
     * Validates: Requirements 5.1, 5.4
     *
     * For any table or index defined in init_db.php, that name should
     * appear in docs/database.md.
     */
    it('every DB table from init_db.php appears in database.md', () => {
      const tables = getDbTableNames()
      const dbDoc = readProjectFile('docs/database.md')
      expect(tables.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...tables),
          (tableName) => {
            expect(dbDoc).toContain(tableName)
          }
        ),
        { numRuns: Math.max(100, tables.length * 10) }
      )
    })

    it('every DB index from init_db.php appears in database.md', () => {
      const indexes = getDbIndexNames()
      const dbDoc = readProjectFile('docs/database.md')
      expect(indexes.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...indexes),
          (indexName) => {
            expect(dbDoc).toContain(indexName)
          }
        ),
        { numRuns: Math.max(100, indexes.length * 10) }
      )
    })
  })

  describe('Property 6: Environment variable configuration documentation', () => {
    /**
     * Validates: Requirements 9.3
     *
     * For any environment variable referenced in docker-compose.yml,
     * that variable name should appear in docs/configuration-files.md.
     */
    it('every docker-compose env var appears in configuration-files.md', () => {
      const envVars = getDockerComposeEnvVars()
      const configDoc = readProjectFile('docs/configuration-files.md')
      expect(envVars.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...envVars),
          (envVar) => {
            expect(configDoc).toContain(envVar)
          }
        ),
        { numRuns: Math.max(100, envVars.length * 10) }
      )
    })
  })

  describe('Property 7: Documentation cross-referencing', () => {
    /**
     * Validates: Requirements 10.1
     *
     * For any documentation file in docs/, that file should contain at least
     * one relative Markdown link to another doc file or to the README.
     */
    it('every docs/ file has at least one cross-reference link', () => {
      const docsFiles = getDocsFiles()
      expect(docsFiles.length).toBeGreaterThan(0)

      fc.assert(
        fc.property(
          fc.constantFrom(...docsFiles),
          (docFile) => {
            const content = readProjectFile(`docs/${docFile}`)
            // Check for markdown links to other .md files
            const hasLink = /\[.*?\]\(.*?\.md.*?\)/.test(content)
            expect(hasLink).toBe(true)
          }
        ),
        { numRuns: Math.max(100, docsFiles.length * 10) }
      )
    })
  })

  describe('Property 8: File path reference accuracy', () => {
    /**
     * Validates: Requirements 10.2
     *
     * For any repository file path referenced in documentation,
     * that path should correspond to an actual file or directory.
     */
    it('file paths in docs match actual repo paths', () => {
      const docsFiles = ['README.md', ...getDocsFiles().map(f => `docs/${f}`)]
      // Collect all referenced paths from all doc files
      const allPaths = []
      for (const docFile of docsFiles) {
        const content = readProjectFile(docFile)
        const paths = extractReferencedPaths(content)
        for (const p of paths) {
          allPaths.push({ docFile, refPath: p })
        }
      }

      // Filter to paths that look like real repo file references
      const repoFilePaths = allPaths.filter(({ refPath }) => {
        // Skip paths that start with /opt, /etc, /var, /bin, /usr (container paths)
        if (/^\/(?:opt|etc|var|bin|usr|tmp)/.test(refPath)) return false
        // Skip URLs
        if (refPath.includes('://')) return false
        // Skip version-like strings
        if (/^\d+\.\d+/.test(refPath)) return false
        // Skip paths with template variables
        if (refPath.includes('${') || refPath.includes('$')) return false
        // Skip build output and runtime-only directories
        if (refPath.includes('/dist/') || refPath.endsWith('/dist')) return false
        // Only check paths that point to actual source files (have a file extension)
        // Skip directory-only references (e.g., www/db/) as they may be runtime-only
        if (refPath.endsWith('/')) return false
        if (!/\.\w+$/.test(refPath)) return false
        // Only check source/config file types that should exist in the repo
        // Skip runtime data files (.db, .sqlite, .log, .gzip, .crt, .key, .pid, .txt)
        const sourceExts = [
          '.php', '.js', '.vue', '.sh', '.yml', '.yaml', '.md', '.conf',
          '.json', '.html', '.css', '.template', '.Dockerfile'
        ]
        const ext = path.extname(refPath).toLowerCase()
        // Also match Dockerfile (no extension)
        const basename = path.basename(refPath)
        if (!sourceExts.includes(ext) && basename !== 'Dockerfile' && basename !== 'crontab') return false
        // Must start with a known project directory or file
        const topLevel = refPath.split('/')[0]
        const knownDirs = [
          'rpidns-frontend', 'rpidns-docker', 'www', 'scripts', 'docs',
          'containers', 'config', 'README.md'
        ]
        return knownDirs.some(d => topLevel === d || topLevel === d.replace('.md', ''))
      })

      if (repoFilePaths.length === 0) return // nothing to test

      fc.assert(
        fc.property(
          fc.constantFrom(...repoFilePaths),
          ({ refPath }) => {
            const exists = projectPathExists(refPath)
            expect(exists).toBe(true)
          }
        ),
        { numRuns: Math.max(100, repoFilePaths.length * 5) }
      )
    })
  })

  // ─── Unit Tests (Task 11.9) ────────────────────────────────────────────

  describe('Unit tests: specific content requirements', () => {

    describe('README content requirements', () => {
      const readme = readProjectFile('README.md')

      it('README contains a Prerequisites section (Req 1.6)', () => {
        expect(readme).toMatch(/## Prerequisites/)
      })

      it('README Built With lists Vue 3, Bootstrap Vue Next, Vite, Axios, ApexCharts, FontAwesome (Req 1.5)', () => {
        const builtWithSection = readme.split(/## Built With/)[1]
        expect(builtWithSection).toBeDefined()
        const section = builtWithSection.split(/^## /m)[0]
        expect(section).toContain('Vue 3')
        expect(section).toContain('Bootstrap Vue Next')
        expect(section).toContain('Vite')
        expect(section).toContain('Axios')
        expect(section).toContain('ApexCharts')
        expect(section).toContain('FontAwesome')
      })

      it('README references authentication system (Req 1.8)', () => {
        // Should mention session-based auth, bcrypt, rate limiting, multi-user
        expect(readme).toMatch(/session-based auth/i)
        expect(readme).toMatch(/bcrypt/i)
        expect(readme).toMatch(/rate limit/i)
        expect(readme).toMatch(/multi-user/i)
      })
    })

    describe('BIND configuration documentation', () => {
      const bindDoc = readProjectFile('docs/bind-configuration.md')

      it('documents all four RPZ zones (Req 8.2)', () => {
        const zones = [
          'allow.ioc2rpz.rpidns',
          'allow-ip.ioc2rpz.rpidns',
          'block.ioc2rpz.rpidns',
          'block-ip.ioc2rpz.rpidns'
        ]
        for (const zone of zones) {
          expect(bindDoc).toContain(zone)
        }
      })

      it('documents all six RPZ policy actions (Req 8.3)', () => {
        const actions = ['NXDOMAIN', 'NODATA', 'PASSTHRU', 'DROP', 'CNAME', 'GIVEN']
        for (const action of actions) {
          expect(bindDoc).toContain(action)
        }
      })
    })

    describe('Docker deployment documentation', () => {
      const dockerDoc = readProjectFile('docs/docker-deployment.md')

      it('marks legacy containers/ directory as deprecated (Req 10.4)', () => {
        expect(dockerDoc).toMatch(/deprecated/i)
        expect(dockerDoc).toMatch(/containers\//)
      })
    })

    describe('docs/ directory structure', () => {
      it('docs/ directory exists with expected files (Req 10.5)', () => {
        const expectedFiles = [
          'architecture.md',
          'backend-api.md',
          'frontend.md',
          'database.md',
          'scripts.md',
          'docker-deployment.md',
          'bind-configuration.md',
          'configuration-files.md'
        ]
        const actualFiles = getDocsFiles()
        for (const expected of expectedFiles) {
          expect(actualFiles).toContain(expected)
        }
      })
    })
  })
})
