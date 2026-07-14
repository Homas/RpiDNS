/**
 * Research Navigation - Unit / Integration Tests
 *
 * Covers the Research tab integration into App.vue (task 10.2):
 *   - tab order: Research sits after "RPZ log" and before "Admin" (Req 1.1)
 *   - collapsed-sidebar markup: fa-flask icon always shown, label hidden
 *     when the sidebar is collapsed (Req 1.4)
 *   - hash #i2r/3 activates the Research tab on load (Req 1.5)
 *   - an out-of-range hash index falls back to the default landing tab (Req 1.6)
 *
 * Feature: research-tools (task 10.4)
 * Validates: Requirements 1.1, 1.4, 1.5, 1.6
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'
import App from '@/App.vue'

// Tab index scheme (mirrors App.vue): 0 Dashboard, 1 Query log, 2 RPZ log,
// 3 Research, 4 Admin, 5 Donate, 6 Help.
const EXPECTED_TAB_LABELS = [
  'Dashboard',
  'Query log',
  'RPZ log',
  'Research',
  'Admin',
  'Donate',
  'Help'
]
const RESEARCH_TAB_INDEX = 3
const DEFAULT_TAB_INDEX = 0

// Stub every child component so we exercise App.vue's own nav/tab template
// without pulling in the full page components and their API calls.
const childStubs = {
  Dashboard: true,
  QueryLog: true,
  RpzHits: true,
  AdminTabs: true,
  Research: true,
  AddAsset: true,
  AddIOC: true,
  ImportDB: true,
  LoginPage: true,
  PasswordChange: true,
  HelpContent: true,
  DonateContent: true
}

function installAuthenticatedFetch() {
  global.fetch = vi.fn((url) => {
    if (typeof url === 'string' && url.includes('auth.php')) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          status: 'success',
          authenticated: true,
          user: { username: 'admin', is_admin: true }
        })
      })
    }
    // getSettings() -> RPIsettings
    return Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ assets_by: 'mac' })
    })
  })
}

async function mountApp(hash) {
  window.location.hash = hash || ''
  const wrapper = mountWithBootstrap(App, {
    attachTo: document.body,
    global: { stubs: childStubs }
  })
  // Let checkSession() resolve and the onMounted hash logic (queued in a
  // nextTick) run.
  await flushPromises()
  await nextTick()
  await flushPromises()
  await nextTick()
  return wrapper
}

// Collect the visible nav label texts in DOM order.
function navLabels(wrapper) {
  return wrapper
    .findAll('span.d-lg-inline')
    .map(n => n.text().replace(/\u00a0/g, ' ').trim())
    .filter(Boolean)
}

describe('App.vue Research tab navigation', () => {
  beforeEach(() => {
    installAuthenticatedFetch()
    window.location.hash = ''
  })

  afterEach(() => {
    window.location.hash = ''
    vi.restoreAllMocks()
  })

  it('renders authenticated main view with all tabs', async () => {
    const wrapper = await mountApp('')
    expect(wrapper.vm.isAuthenticated).toBe(true)
    const labels = navLabels(wrapper)
    for (const label of EXPECTED_TAB_LABELS) {
      expect(labels).toContain(label)
    }
    wrapper.unmount()
  })

  it('places the Research tab after "RPZ log" and before "Admin" (Req 1.1)', async () => {
    const wrapper = await mountApp('')
    const labels = navLabels(wrapper)

    const rpzIdx = labels.indexOf('RPZ log')
    const researchIdx = labels.indexOf('Research')
    const adminIdx = labels.indexOf('Admin')

    expect(rpzIdx).toBeGreaterThanOrEqual(0)
    expect(researchIdx).toBe(rpzIdx + 1)
    expect(adminIdx).toBe(researchIdx + 1)
    wrapper.unmount()
  })

  it('renders the Research tab with a fa-flask icon and a label (Req 1.4)', async () => {
    const wrapper = await mountApp('')
    expect(wrapper.find('i.fa-flask').exists()).toBe(true)
    expect(navLabels(wrapper)).toContain('Research')
    wrapper.unmount()
  })

  it('hides the Research label when the sidebar is collapsed (Req 1.4)', async () => {
    const wrapper = await mountApp('')

    // Expanded: label span is not marked hidden.
    const labelSpan = wrapper
      .findAll('span.d-lg-inline')
      .find(n => n.text().replace(/\u00a0/g, ' ').trim() === 'Research')
    expect(labelSpan).toBeTruthy()
    expect(labelSpan.classes()).not.toContain('hidden')

    // Collapse the sidebar; label gains the `hidden` class while the icon stays.
    wrapper.vm.toggleMenu = 1
    await nextTick()
    const collapsedLabel = wrapper
      .findAll('span.d-lg-inline')
      .find(n => n.text().replace(/\u00a0/g, ' ').trim() === 'Research')
    expect(collapsedLabel.classes()).toContain('hidden')
    expect(wrapper.find('i.fa-flask').exists()).toBe(true)
    wrapper.unmount()
  })

  it('activates the Research tab for hash #i2r/3 on load (Req 1.5)', async () => {
    const wrapper = await mountApp('#i2r/3')
    expect(wrapper.vm.cfgTab).toBe(RESEARCH_TAB_INDEX)
    wrapper.unmount()
  })

  it('falls back to the default tab for an out-of-range hash index (Req 1.6)', async () => {
    const wrapper = await mountApp('#i2r/99')
    expect(wrapper.vm.cfgTab).toBe(DEFAULT_TAB_INDEX)
    wrapper.unmount()
  })

  it('falls back to the default tab for a non-numeric hash index (Req 1.6)', async () => {
    const wrapper = await mountApp('#i2r/abc')
    expect(wrapper.vm.cfgTab).toBe(DEFAULT_TAB_INDEX)
    wrapper.unmount()
  })

  it('activates the RPZ log tab for a valid in-range hash index', async () => {
    const wrapper = await mountApp('#i2r/2')
    expect(wrapper.vm.cfgTab).toBe(2)
    wrapper.unmount()
  })
})
