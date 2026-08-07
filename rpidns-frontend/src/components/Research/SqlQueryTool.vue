<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="p-2">
    <BCard>
      <template #header>
        <span class="bold"><i class="fas fa-database"></i>&nbsp;&nbsp;SQL query</span>
      </template>

      <!-- SQL editor bound to the sql ref -->
      <BFormGroup label-size="sm">
        <BFormTextarea
          v-model="sql"
          placeholder="Enter a read-only SELECT statement…"
          rows="6"
          max-rows="16"
          spellcheck="false"
          class="sql-editor"
        ></BFormTextarea>
      </BFormGroup>

      <!-- Action row: Run + CSV copy + available tables toggle -->
      <BRow class="align-items-center mb-2">
        <BCol cols="12" md="6">
          <BButton
            variant="primary"
            size="sm"
            :disabled="isLoading || !sql.trim()"
            @click="runQuery"
          >
            <BSpinner v-if="isLoading" small></BSpinner>
            <i v-else class="fas fa-play"></i>
            &nbsp;Run
          </BButton>

          <BButton
            variant="outline-secondary"
            size="sm"
            class="ms-2"
            :disabled="!hasResults"
            v-b-tooltip.hover
            title="Copy results as CSV"
            @click="copyCsv"
          >
            <i class="fas fa-copy"></i>&nbsp;Copy CSV
          </BButton>
        </BCol>
        <BCol cols="12" md="6" class="text-md-end mt-2 mt-md-0">
          <BButton
            variant="outline-secondary"
            size="sm"
            @click="toggleTables"
          >
            <i class="fas" :class="showTables ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            &nbsp;Available tables
          </BButton>
        </BCol>
      </BRow>

      <!-- Collapsible list of available table names (Req 4.9) -->
      <BCollapse v-model="showTables" class="mb-2">
        <div class="border rounded p-2 bg-light">
          <div v-if="tablesLoading" class="text-muted small">
            <BSpinner small></BSpinner>&nbsp;Loading tables…
          </div>
          <div v-else-if="tablesError" class="text-danger small">
            {{ tablesError }}
          </div>
          <div v-else-if="tables.length === 0" class="text-muted small">
            No tables available.
          </div>
          <div v-else>
            <BBadge
              v-for="name in tables"
              :key="name"
              variant="secondary"
              class="me-1 mb-1 table-badge"
              @click="insertTableName(name)"
            >
              {{ name }}
            </BBadge>
          </div>
        </div>
      </BCollapse>

      <!-- Error region (Req 5.7) -->
      <BAlert :model-value="!!errorMessage" variant="danger" class="py-2">
        <i class="fas fa-triangle-exclamation"></i>&nbsp;{{ errorMessage }}
      </BAlert>

      <!-- Result meta: total row count + truncation notice -->
      <div v-if="hasRun && !errorMessage" class="d-flex align-items-center mb-2">
        <span v-if="!isLoading" class="small text-muted">
          {{ totalRows }} row{{ totalRows === 1 ? '' : 's' }}
          <span v-if="truncated"> (capped)</span>
        </span>
        <BBadge v-if="truncated" variant="warning" class="ms-2">
          <i class="fas fa-scissors"></i>&nbsp;Results truncated at {{ totalRows }}
        </BBadge>
      </div>

      <!-- Loading indicator (Req 5.3, 5.4) -->
      <div v-if="isLoading" class="text-center m-2">
        <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Running query…</strong>
      </div>

      <!-- Pagination controls: each page is fetched from the server via
           LIMIT/OFFSET, so the browser only ever holds one page. Changing the
           page or page size re-fetches that page for the last-run query. -->
      <div
        v-if="!isLoading && hasResults && totalRows > 0"
        class="d-flex align-items-center justify-content-between flex-wrap mb-2"
      >
        <div class="d-flex align-items-center">
          <label class="small text-muted me-2 mb-0">Per page</label>
          <BFormSelect
            :model-value="resultsPp"
            @update:model-value="onPerPageChange"
            :options="perPageOptions"
            size="sm"
            style="width: auto"
          ></BFormSelect>
        </div>
        <BPagination
          :model-value="resultsCp"
          @update:model-value="onPageChange"
          :total-rows="totalRows"
          :per-page="resultsPp"
          size="sm"
          pills
          align="center"
          first-number
          last-number
          limit="7"
          class="mb-0"
        ></BPagination>
      </div>

      <!-- Results table (Req 5.1). `responsive` wraps the table in a
           horizontally-scrollable container so wide result sets scroll instead
           of overflowing the page; `sticky-header` keeps headers visible while
           scrolling vertically. Only `pagedRows` is rendered. -->
      <div v-if="!isLoading && hasResults">
        <BTableSimple striped hover small responsive sticky-header="420px">
          <BThead>
            <BTr>
              <BTh v-for="(col, ci) in columns" :key="ci">{{ col }}</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="(row, ri) in rows" :key="pageRowKey(ri)">
              <BTd v-for="(cell, ci) in row" :key="ci">{{ formatCell(cell) }}</BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
      </div>

      <!-- Zero-rows indication (Req 5.2) -->
      <div
        v-if="!isLoading && hasRun && !errorMessage && totalRows === 0"
        class="text-center text-muted p-3"
      >
        <i class="fas fa-circle-info"></i>&nbsp;The query returned no rows.
      </div>
    </BCard>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '../../composables/useApi'
import { copyDatasetAsCsv } from '../../composables/useCsvExport'

export default {
  name: 'SqlQueryTool',
  emits: ['show-info'],
  setup(props, { emit }) {
    const api = useApi()

    // Editor state
    const sql = ref('')

    // Available tables (collapsible list)
    const showTables = ref(false)
    const tables = ref([])
    const tablesLoading = ref(false)
    const tablesError = ref('')
    const tablesLoaded = ref(false)

    // Result state (holds ONLY the current page)
    const columns = ref([])
    const rows = ref([])
    const truncated = ref(false)
    const isLoading = ref(false)
    const hasRun = ref(false)
    const errorMessage = ref('')

    const hasResults = computed(() => columns.value.length > 0)

    // Server-side pagination state. The executed statement is cached in
    // `runSql` so page navigation re-fetches the same query (not the possibly
    // edited editor contents). Only one page is ever held in memory.
    const CAP = 10000
    const runSql = ref('')
    const totalRows = ref(0)     // total rows across all pages (capped at CAP)
    const resultsCp = ref(1)
    const resultsPp = ref(100)
    const perPageOptions = [50, 100, 250, 500, 1000]
    const pageRowKey = (i) => (resultsCp.value - 1) * resultsPp.value + i

    // Fetch the available table names once, on first expand (Req 4.9)
    const fetchTables = async () => {
      tablesLoading.value = true
      tablesError.value = ''
      try {
        const res = await api.get({ req: 'research_tables' })
        if (res && res.status === 'ok' && Array.isArray(res.data)) {
          tables.value = res.data
          tablesLoaded.value = true
        } else {
          tablesError.value = (res && res.reason) || 'Failed to load tables.'
        }
      } catch (e) {
        tablesError.value = 'Failed to load tables.'
      } finally {
        tablesLoading.value = false
      }
    }

    const toggleTables = () => {
      showTables.value = !showTables.value
      if (showTables.value && !tablesLoaded.value && !tablesLoading.value) {
        fetchTables()
      }
    }

    const insertTableName = (name) => {
      // An empty editor gets a complete, runnable statement so a single click
      // is enough to look at a table. Once there is something to build on, only
      // the name is appended, since the click is then part of a statement the
      // user is composing.
      if (sql.value.trim() === '') {
        sql.value = `select * from ${name}`
        return
      }
      sql.value = `${sql.value} ${name}`
    }

    // Clear the current result set (used before a run and on error, so prior
    // results are never presented as the result of the current submission).
    const clearResults = () => {
      columns.value = []
      rows.value = []
      totalRows.value = 0
      truncated.value = false
    }

    // Fetch a single page of the last-run query from the backend. When
    // `withCount` is true (a new query submission) the server also computes the
    // bounded total-row count; page navigation omits it and reuses the cached
    // total.
    const fetchPage = async (withCount) => {
      if (!runSql.value || isLoading.value) return
      isLoading.value = true
      errorMessage.value = ''
      try {
        const body = { sql: runSql.value, cp: resultsCp.value, pp: resultsPp.value }
        if (withCount) body.count = 1
        const res = await api.post({ req: 'research_sql' }, body)
        if (res && res.status === 'ok' && res.data) {
          const data = res.data
          columns.value = Array.isArray(data.columns) ? data.columns : []
          rows.value = Array.isArray(data.rows) ? data.rows : []
          if (typeof data.totalRows === 'number') {
            totalRows.value = data.totalRows
            truncated.value = data.truncated === true
          }
        } else {
          // Error: clear results so a prior page is never shown as the current
          // result (Req 5.7).
          clearResults()
          errorMessage.value = (res && res.reason) || 'Query failed.'
        }
      } catch (e) {
        clearResults()
        errorMessage.value =
          (e && e.message) || 'Query failed due to a network or server error.'
      } finally {
        hasRun.value = true
        isLoading.value = false
      }
    }

    // Submit the SQL statement to the read-only backend (Req 5.x). Runs page 1
    // and requests the bounded total row count.
    const runQuery = async () => {
      const statement = sql.value.trim()
      if (!statement || isLoading.value) return
      runSql.value = statement
      resultsCp.value = 1
      clearResults()
      await fetchPage(true)
    }

    // Pagination handlers: re-fetch the requested page for the cached query.
    const onPageChange = (page) => {
      resultsCp.value = page
      fetchPage(false)
    }
    const onPerPageChange = (pp) => {
      resultsPp.value = pp
      resultsCp.value = 1
      fetchPage(false)
    }

    // Copy the FULL result dataset (up to the 10,000-row cap) as CSV (Req 3.2).
    // A dedicated request fetches all rows at once; they are serialized and
    // discarded without being rendered, so the browser is not affected.
    const copyCsv = async () => {
      if (!hasResults.value || !runSql.value) return
      try {
        const res = await api.post(
          { req: 'research_sql' },
          { sql: runSql.value, cp: 1, pp: CAP }
        )
        if (res && res.status === 'ok' && res.data && Array.isArray(res.data.rows)) {
          const cols = Array.isArray(res.data.columns) ? res.data.columns : columns.value
          await copyDatasetAsCsv(cols, res.data.rows)
          emit('show-info', 'Results copied to clipboard as CSV', 3)
        } else {
          emit('show-info', 'Failed to copy results to clipboard', 3)
        }
      } catch (e) {
        emit('show-info', 'Failed to copy results to clipboard', 3)
      }
    }

    const formatCell = (value) => (value === null || value === undefined ? '' : value)

    return {
      sql,
      showTables,
      tables,
      tablesLoading,
      tablesError,
      columns,
      rows,
      totalRows,
      truncated,
      isLoading,
      hasRun,
      errorMessage,
      hasResults,
      resultsCp,
      resultsPp,
      perPageOptions,
      pageRowKey,
      toggleTables,
      insertTableName,
      runQuery,
      onPageChange,
      onPerPageChange,
      copyCsv,
      formatCell
    }
  }
}
</script>

<style scoped>
.sql-editor {
  font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: 0.875rem;
}
.table-badge {
  cursor: pointer;
}
.bold {
  font-weight: bold;
}
</style>
