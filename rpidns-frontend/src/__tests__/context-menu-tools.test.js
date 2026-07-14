/**
 * Context Menu Tools Group - Unit / Integration Tests
 *
 * Covers the network-tools group in ContextMenu.vue:
 *   - three separately labeled groups (Research links, Tools, Actions)
 *   - one Tools entry per NETWORK_TOOLS definition
 *   - selecting a tool dispatches the global `open-research-tools` event with the
 *     right-clicked domain + tool name and closes the menu (the shared
 *     ToolsModal then prefills the domain and runs the tool)
 *   - selecting a tool leaves the originating log row unchanged (no add-ioc /
 *     action emitted)
 *
 * Feature: research-tools
 * Validates: Requirements 7.2, 7.3, 7.5
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'

import ContextMenu from '@/components/ContextMenu.vue'
import { NETWORK_TOOLS } from '@/composables/useNetworkTools'

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

// Return the buttons that correspond to NETWORK_TOOLS entries (by label text).
function toolButtons(wrapper) {
  const labels = NETWORK_TOOLS.map(t => t.label)
  return wrapper
    .findAll('button.context-menu-action')
    .filter(btn => labels.includes(btn.text().trim()))
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

  it('renders exactly one Tools entry per NETWORK_TOOLS definition', () => {
    const wrapper = mountMenu()
    const btns = toolButtons(wrapper)

    expect(btns).toHaveLength(NETWORK_TOOLS.length)
    const rendered = btns.map(b => b.text().trim())
    for (const tool of NETWORK_TOOLS) {
      expect(rendered).toContain(tool.label)
    }

    wrapper.unmount()
  })

  it('hides the Tools group when show-research is false (column-filter menus)', () => {
    const wrapper = mountMenu({ showResearch: false })
    const labels = sectionLabels(wrapper)
    expect(labels.some(l => /Tools/i.test(l))).toBe(false)
    expect(toolButtons(wrapper)).toHaveLength(0)
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

  it('dispatches open-research-tools with the tool + domain and closes the menu', async () => {
    const wrapper = mountMenu()
    const pingBtn = toolButtons(wrapper).find(b => b.text().trim() === 'ping')
    expect(pingBtn).toBeTruthy()

    await pingBtn.trigger('click')

    // The shared ToolsModal is driven via a global window event carrying the
    // right-clicked domain and the selected tool (Req 7.2).
    expect(openEvents).toHaveLength(1)
    expect(openEvents[0]).toEqual({ tool: 'ping', target: DOMAIN })

    // Selecting a tool closes the context menu (the modal takes over).
    const visEvents = wrapper.emitted('update:visible')
    expect(visEvents).toBeTruthy()
    expect(visEvents[visEvents.length - 1]).toEqual([false])

    wrapper.unmount()
  })

  it('targets the right-clicked domain for each tool action', async () => {
    const wrapper = mountMenu({ domain: 'evil.test' })

    const digBtn = toolButtons(wrapper).find(b => b.text().trim() === 'dig')
    await digBtn.trigger('click')

    expect(openEvents).toHaveLength(1)
    expect(openEvents[0]).toEqual({ tool: 'dig', target: 'evil.test' })

    wrapper.unmount()
  })

  it('does not mutate the originating row (no action / add-ioc emitted)', async () => {
    const wrapper = mountMenu()
    const rdapBtn = toolButtons(wrapper).find(b => b.text().trim() === 'RDAP / WHOIS')
    await rdapBtn.trigger('click')

    expect(openEvents).toHaveLength(1)
    expect(openEvents[0]).toEqual({ tool: 'rdap', target: DOMAIN })
    expect(wrapper.emitted('action')).toBeFalsy()
    expect(wrapper.emitted('add-ioc')).toBeFalsy()

    wrapper.unmount()
  })
})
