/*
 * (c) Vadim Pavlov 2020 - 2026
 * Unit tests for the CSV copy control in UniqueQueriesView.vue.
 *
 * Covers:
 *  - CSV copy control present (Req 3.1)
 *  - Control disabled while no dataset is loaded (Req 3.9)
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: vi.fn(), del: vi.fn() })
}))
vi.mock('../../composables/useApi', () => ({
  useApi: () => ({ get: mockGet, post: mockPost, put: vi.fn(), del: vi.fn() })
}))

import UniqueQueriesView from '@/components/Research/UniqueQueriesView.vue'

// Stub the child components not under test so the view mounts in isolation.
const childStubs = {
  ContextMenu: { name: 'ContextMenu', template: '<div />' },
  CustomPeriodPicker: { name: 'CustomPeriodPicker', template: '<div />' }
}

function csvButton(wrapper) {
  return wrapper.findAll('button').find(b => b.text().includes('CSV'))
}

describe('UniqueQueriesView.vue - CSV copy control', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('provides a CSV copy control (Req 3.1)', async () => {
    mockGet.mockResolvedValue({ status: 'ok', data: [] })
    const wrapper = mountWithBootstrap(UniqueQueriesView, {
      global: { stubs: childStubs }
    })
    await flushPromises()
    expect(csvButton(wrapper)).toBeTruthy()
  })

  it('disables the CSV copy control when no dataset is loaded (Req 3.9)', async () => {
    // A failed fetch means no dataset is loaded (datasetLoaded stays false).
    mockGet.mockRejectedValue(new Error('network error'))
    const wrapper = mountWithBootstrap(UniqueQueriesView, {
      global: { stubs: childStubs }
    })
    await flushPromises()

    const btn = csvButton(wrapper)
    expect(btn.attributes('disabled')).toBeDefined()
  })

  it('enables the CSV copy control once a dataset has loaded successfully (Req 3.9)', async () => {
    mockGet.mockResolvedValue({
      status: 'ok',
      data: [{ fqdn: 'a.com', cnt: 2, last_seen: '2024-01-01T00:00:00Z' }]
    })
    const wrapper = mountWithBootstrap(UniqueQueriesView, {
      global: { stubs: childStubs }
    })
    await flushPromises()

    const btn = csvButton(wrapper)
    expect(btn.attributes('disabled')).toBeUndefined()
  })
})
