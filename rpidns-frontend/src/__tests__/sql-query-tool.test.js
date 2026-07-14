/*
 * (c) Vadim Pavlov 2020 - 2026
 * Unit tests for SqlQueryTool.vue (SQL results presentation + CSV copy control).
 *
 * Covers:
 *  - Header order matches returned columns (Req 5.1)
 *  - Zero-rows indication on a successful empty result (Req 5.2)
 *  - Loading indicator toggles during / after an in-flight query (Req 5.3, 5.4)
 *  - Row count display (Req 5.5)
 *  - Truncation notice when data.truncated is true (Req 5.6)
 *  - Error region shows the returned reason and hides prior rows (Req 5.7)
 *  - CSV copy control present (Req 3.2), disabled when no dataset (Req 3.9),
 *    and emits a confirmation show-info on success (Req 3.6)
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountWithBootstrap } from './helpers/mountWithBootstrap'

// --- Mock the API composable (mirrors the vi.mock pattern used elsewhere) ---
const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    del: vi.fn()
  })
}))

// The component imports useApi via the relative '../../composables/useApi'
// path; alias that to the same '@/composables/useApi' mock above.
vi.mock('../../composables/useApi', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    del: vi.fn()
  })
}))

import SqlQueryTool from '@/components/Research/SqlQueryTool.vue'

/** Create an externally-resolvable promise for controlling in-flight state. */
function deferred() {
  let resolve
  let reject
  const promise = new Promise((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

/** Enter a SQL statement and click Run. */
async function runWith(wrapper, statement = 'SELECT 1') {
  await wrapper.find('textarea').setValue(statement)
  const runBtn = wrapper.findAll('button').find(b => b.text().includes('Run'))
  await runBtn.trigger('click')
}

describe('SqlQueryTool.vue - SQL results presentation', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders table headers equal to the returned columns array in order (Req 5.1)', async () => {
    const columns = ['fqdn', 'cnt', 'last_seen']
    mockPost.mockResolvedValue({
      status: 'ok',
      data: { columns, rows: [['a.com', 3, '2024-01-01']], rowCount: 1, page: 1, perPage: 100, totalRows: 1, truncated: false }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)
    await flushPromises()

    const headers = wrapper.findAll('th').map(th => th.text())
    expect(headers).toEqual(columns)
  })

  it('shows a zero-rows indication when a successful run returns 0 rows (Req 5.2)', async () => {
    mockPost.mockResolvedValue({
      status: 'ok',
      data: { columns: ['fqdn'], rows: [], rowCount: 0, page: 1, perPage: 100, totalRows: 0, truncated: false }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('The query returned no rows.')
  })

  it('shows the loading indicator during an in-flight query and hides it after (Req 5.3, 5.4)', async () => {
    const d = deferred()
    mockPost.mockReturnValue(d.promise)

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)

    // In flight: loading indication is visible.
    expect(wrapper.text()).toContain('Running query…')

    // Resolve the query and let the DOM update.
    d.resolve({
      status: 'ok',
      data: { columns: ['x'], rows: [[1]], rowCount: 1, page: 1, perPage: 100, totalRows: 1, truncated: false }
    })
    await flushPromises()

    // Completed: loading indication is gone.
    expect(wrapper.text()).not.toContain('Running query…')
  })

  it('displays the number of rows returned (Req 5.5)', async () => {
    mockPost.mockResolvedValue({
      status: 'ok',
      data: {
        columns: ['x'],
        rows: [[1], [2], [3]],
        rowCount: 3,
        page: 1,
        perPage: 100,
        totalRows: 3,
        truncated: false
      }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('3 rows')
  })

  it('shows a truncation notice when data.truncated is true (Req 5.6)', async () => {
    mockPost.mockResolvedValue({
      status: 'ok',
      data: {
        columns: ['x'],
        rows: [[1]],
        rowCount: 100,
        page: 1,
        perPage: 100,
        totalRows: 10000,
        truncated: true
      }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Results truncated')
  })

  it('shows the returned error reason and does not show prior rows (Req 5.7)', async () => {
    // First run succeeds and renders a row.
    mockPost.mockResolvedValueOnce({
      status: 'ok',
      data: { columns: ['fqdn'], rows: [['keepme.example']], rowCount: 1, page: 1, perPage: 100, totalRows: 1, truncated: false }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper, 'SELECT fqdn FROM q')
    await flushPromises()
    expect(wrapper.text()).toContain('keepme.example')

    // Second run returns an error; prior rows must not be presented.
    mockPost.mockResolvedValueOnce({
      status: 'error',
      reason: 'only read-only SELECT queries are permitted'
    })
    await runWith(wrapper, 'DELETE FROM q')
    await flushPromises()

    expect(wrapper.text()).toContain('only read-only SELECT queries are permitted')
    expect(wrapper.text()).not.toContain('keepme.example')
  })
})

describe('SqlQueryTool.vue - CSV copy control', () => {
  beforeEach(() => {
    mockGet.mockReset()
    mockPost.mockReset()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  function csvButton(wrapper) {
    return wrapper.findAll('button').find(b => b.text().includes('Copy CSV'))
  }

  it('provides a CSV copy control (Req 3.2)', () => {
    const wrapper = mountWithBootstrap(SqlQueryTool)
    expect(csvButton(wrapper)).toBeTruthy()
  })

  it('disables the CSV copy control while no dataset is loaded (Req 3.9)', () => {
    const wrapper = mountWithBootstrap(SqlQueryTool)
    const btn = csvButton(wrapper)
    expect(btn.attributes('disabled')).toBeDefined()
  })

  it('emits a confirmation show-info when the copy succeeds (Req 3.6)', async () => {
    // Provide a resolving clipboard mock.
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText } })

    mockPost.mockResolvedValue({
      status: 'ok',
      data: { columns: ['fqdn'], rows: [['a.com']], rowCount: 1, page: 1, perPage: 100, totalRows: 1, truncated: false }
    })

    const wrapper = mountWithBootstrap(SqlQueryTool)
    await runWith(wrapper)
    await flushPromises()

    // Control is now enabled; activate it (a dedicated request fetches all rows).
    await csvButton(wrapper).trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledTimes(1)
    const emitted = wrapper.emitted('show-info')
    expect(emitted).toBeTruthy()
    expect(emitted[emitted.length - 1][0]).toContain('CSV')
  })
})
