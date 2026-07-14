/**
 * Context Menu Tools Group - Unit / Integration Tests
 *
 * Covers the network-tools group added to ContextMenu.vue (task 10.3):
 *   - three separately labeled groups (Research links, Tools, Actions)
 *   - one Tools entry per NETWORK_TOOLS definition
 *   - selecting a tool executes research_tool via useApi (loading -> output)
 *   - a failed tool shows an error indication and leaves the originating row
 *     unchanged (no add-ioc / action / refresh emitted, menu stays open)
 *
 * Feature: research-tools (task 10.4)
 * Validates: Requirements 7.2, 7.3, 7.5
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'

// Mock useApi so tool execution hits a controllable stub — vi.mock is hoisted.
const mockPost = vi.fn()
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: mockPost,
    put: vi.fn(),
    del: vi.fn()
  }),
  default: () => ({
    get: vi.fn(),
    post: mockPost,
    put: vi.fn(),
    del: vi.fn()
  })
}))

// Import after the mock so the component picks up the stubbed useApi.
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
  beforeEach(() => {
    mockPost.mockReset()
  })

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

describe('ContextMenu tools group — execution (Req 7.2, 7.4)', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('shows a loading indication then the output when a tool succeeds', async () => {
    // Deferred promise so we can observe the loading state before resolution.
    let resolveFn
    mockPost.mockReturnValue(new Promise(resolve => { resolveFn = resolve }))

    const wrapper = mountMenu()
    const pingBtn = toolButtons(wrapper).find(b => b.text().trim() === 'ping')
    expect(pingBtn).toBeTruthy()

    await pingBtn.trigger('click')
    await nextTick()

    // Loading indication while in flight (Req 7.4)
    expect(wrapper.find('.context-menu-tool-loading').exists()).toBe(true)

    // Endpoint invoked correctly (Req 7.2)
    expect(mockPost).toHaveBeenCalledTimes(1)
    expect(mockPost).toHaveBeenCalledWith(
      { req: 'research_tool' },
      { tool: 'ping', target: DOMAIN }
    )

    resolveFn({ status: 'ok', data: { output: 'PING example.com 56 bytes' } })
    await flushPromises()
    await nextTick()

    // Output displayed, loading cleared
    expect(wrapper.find('.context-menu-tool-loading').exists()).toBe(false)
    const output = wrapper.find('.context-menu-tool-output')
    expect(output.exists()).toBe(true)
    expect(output.text()).toContain('PING example.com')

    wrapper.unmount()
  })

  it('targets the right-clicked domain for each tool action', async () => {
    mockPost.mockResolvedValue({ status: 'ok', data: { output: 'ok' } })
    const wrapper = mountMenu({ domain: 'evil.test' })

    const digBtn = toolButtons(wrapper).find(b => b.text().trim() === 'dig')
    await digBtn.trigger('click')
    await flushPromises()

    expect(mockPost).toHaveBeenCalledWith(
      { req: 'research_tool' },
      { tool: 'dig', target: 'evil.test' }
    )
    wrapper.unmount()
  })
})

describe('ContextMenu tools group — error handling (Req 7.5)', () => {
  beforeEach(() => {
    mockPost.mockReset()
  })

  it('shows an error indication on an error status and leaves the row unchanged', async () => {
    mockPost.mockResolvedValue({ status: 'error', reason: 'tool_start_failed' })

    const wrapper = mountMenu()
    const traceBtn = toolButtons(wrapper).find(b => b.text().trim() === 'traceroute')
    await traceBtn.trigger('click')
    await flushPromises()
    await nextTick()

    const err = wrapper.find('.context-menu-tool-error')
    expect(err.exists()).toBe(true)
    expect(err.text()).toContain('tool_start_failed')
    expect(wrapper.find('.context-menu-tool-output').exists()).toBe(false)

    // Originating row unchanged: no action / add-ioc emitted, menu stays open.
    expect(wrapper.emitted('action')).toBeFalsy()
    expect(wrapper.emitted('add-ioc')).toBeFalsy()
    expect(wrapper.emitted('update:visible')).toBeFalsy()

    wrapper.unmount()
  })

  it('shows an error indication when the request rejects', async () => {
    mockPost.mockRejectedValue(new Error('network down'))

    const wrapper = mountMenu()
    const rdapBtn = toolButtons(wrapper).find(b => b.text().trim() === 'RDAP / WHOIS')
    await rdapBtn.trigger('click')
    await flushPromises()
    await nextTick()

    const err = wrapper.find('.context-menu-tool-error')
    expect(err.exists()).toBe(true)
    expect(err.text()).toContain('network down')

    expect(wrapper.emitted('action')).toBeFalsy()
    expect(wrapper.emitted('add-ioc')).toBeFalsy()

    wrapper.unmount()
  })
})
