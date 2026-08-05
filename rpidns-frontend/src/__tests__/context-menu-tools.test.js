/**
 * Context Menu Tools Group - Unit / Integration Tests
 *
 * Covers the tools group in ContextMenu.vue:
 *   - three separately labeled groups (Research links, Tools, Actions)
 *   - a single "Analyze" entry (not one entry per tool), identical on every page
 *   - selecting it dispatches the global `open-research-tools` event with the
 *     right-clicked target and no specific tool, so the shared ToolsModal runs
 *     the full applicable tool set
 *   - selecting it leaves the originating log row unchanged (no add-ioc /
 *     action emitted)
 *
 * Feature: research-tools
 * Validates: Requirements 7.2, 7.3, 7.5
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'

import ContextMenu from '@/components/ContextMenu.vue'

const DOMAIN = 'example.com'

function mountMenu(props = {}) {
  return mountWithBootstrap(ContextMenu, {
    props: {
      visible: true,
      domain: DOMAIN,
      x: 10,
      y: 10,
      actions: [{ label: 'Block domain', handler: vi.fn() }],
      ...props
    },
    attachTo: document.body
  })
}

// Return the labels of the section headers, in DOM order.
function sectionLabels(wrapper) {
  return wrapper
    .findAll('.context-menu-section-label')
    .map(n => n.text().trim())
}

// The Analyze entry that opens the Research tools panel.
function analyzeButtons(wrapper) {
  return wrapper
    .findAll('button.context-menu-action')
    .filter(btn => btn.text().trim() === 'Analyze')
}

describe('ContextMenu tools group — group structure (Req 7.3)', () => {
  it('renders three separately labeled groups: Research, Tools, Actions', () => {
    const wrapper = mountMenu()
    const labels = sectionLabels(wrapper)

    expect(labels.some(l => /Research/i.test(l))).toBe(true)
    expect(labels.some(l => /Tools/i.test(l))).toBe(true)
    expect(labels.some(l => /Actions/i.test(l))).toBe(true)

    // Tools group sits between the Research links group and the Actions group.
    const researchIdx = labels.findIndex(l => /Research/i.test(l))
    const toolsIdx = labels.findIndex(l => /Tools/i.test(l))
    const actionsIdx = labels.findIndex(l => /Actions/i.test(l))
    expect(researchIdx).toBeLessThan(toolsIdx)
    expect(toolsIdx).toBeLessThan(actionsIdx)

    wrapper.unmount()
  })

  it('renders exactly one Analyze entry, not one entry per tool', () => {
    const wrapper = mountMenu()
    expect(analyzeButtons(wrapper)).toHaveLength(1)

    // Per-tool entries are gone: the panel owns tool selection now.
    const labels = wrapper.findAll('button.context-menu-action').map(b => b.text().trim())
    expect(labels).not.toContain('ping')
    expect(labels).not.toContain('traceroute')
    expect(labels).not.toContain('RDAP / WHOIS')

    wrapper.unmount()
  })

  it('hides the Tools group when show-research is false (column-filter menus)', () => {
    const wrapper = mountMenu({ showResearch: false })
    const labels = sectionLabels(wrapper)
    expect(labels.some(l => /Tools/i.test(l))).toBe(false)
    expect(analyzeButtons(wrapper)).toHaveLength(0)
    wrapper.unmount()
  })

  it('hides the Tools group when there is no target', () => {
    const wrapper = mountMenu({ domain: '' })
    expect(analyzeButtons(wrapper)).toHaveLength(0)
    wrapper.unmount()
  })
})

describe('ContextMenu tools group — launch modal (Req 7.2, 7.4)', () => {
  let openEvents

  beforeEach(() => {
    openEvents = []
    window.addEventListener('open-research-tools', captureEvent)
  })

  afterEach(() => {
    window.removeEventListener('open-research-tools', captureEvent)
  })

  function captureEvent(e) {
    openEvents.push(e.detail)
  }

  it('dispatches open-research-tools with the target and closes the menu', async () => {
    const wrapper = mountMenu()
    const analyze = analyzeButtons(wrapper)[0]
    expect(analyze).toBeTruthy()

    await analyze.trigger('click')

    // The shared ToolsModal is driven via a global window event carrying the
    // right-clicked target. An empty tool means "run every applicable tool".
    expect(openEvents).toHaveLength(1)
    expect(openEvents[0]).toEqual({ tool: '', target: DOMAIN })

    // Selecting Analyze closes the context menu (the modal takes over).
    const visEvents = wrapper.emitted('update:visible')
    expect(visEvents).toBeTruthy()
    expect(visEvents[visEvents.length - 1]).toEqual([false])

    wrapper.unmount()
  })

  it('targets the right-clicked domain', async () => {
    const wrapper = mountMenu({ domain: 'evil.test' })

    await analyzeButtons(wrapper)[0].trigger('click')

    expect(openEvents).toHaveLength(1)
    expect(openEvents[0]).toEqual({ tool: '', target: 'evil.test' })

    wrapper.unmount()
  })

  it('does not mutate the originating row (no action / add-ioc emitted)', async () => {
    const wrapper = mountMenu()
    await analyzeButtons(wrapper)[0].trigger('click')

    expect(openEvents).toHaveLength(1)
    expect(wrapper.emitted('action')).toBeFalsy()
    expect(wrapper.emitted('add-ioc')).toBeFalsy()

    wrapper.unmount()
  })
})
