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

      <!-- Result meta: row count + truncation notice -->
      <div v-if="hasRun && !errorMessage" class="d-flex align-items-center mb-2">
        <span v-if="!isLoading" class="small text-muted">
          {{ rowCount }} row{{ rowCount === 1 ? '' : 's' }}
        </span>
        <BBadge v-if="truncated" variant="warning" class="ms-2">
          <i class="fas fa-scissors"></i>&nbsp;Results truncated
        </BBadge>
      </div>

      <!-- Loading indicator (Req 5.3, 5.4) -->
      <div v-if="isLoading" class="text-center m-2">
        <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Running query…</strong>
      </div>

      <!-- Results table (Req 5.1) -->
      <div v-if="!isLoading && hasResults" class="table-responsive">
        <BTableSimple striped hover small responsive sticky-header="420px">
          <BThead>
            <BTr>
              <BTh v-for="(col, ci) in columns" :key="ci">{{ col }}</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="(row, ri) in rows" :key="ri">
              <BTd v-for="(cell, ci) in row" :key="ci">{{ formatCell(cell) }}</BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
      </div>

      <!-- Zero-rows indication (Req 5.2) -->
      <div
        v-if="!isLoading && hasRun && !errorMessage && rowCount === 0"
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

    // Result state
    const columns = ref([])
    const rows = ref([])
    const rowCount = ref(0)
    const truncated = ref(false)
    const isLoading = ref(false)
    const hasRun = ref(false)
    const errorMessage = ref('')

    const hasResults = computed(() => columns.value.length > 0)

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
      // Append the table name to the editor for convenience.
      sql.value = sql.value ? `${sql.value} ${name}` : name
    }

    // Clear the current result set (used before a run and on error, so prior
    // results are never presented as the result of the current submission).
    const clearResults = () => {
      columns.value = []
      rows.value = []
      rowCount.value = 0
      truncated.value = false
    }

    // Submit the SQL statement to the read-only backend (Req 5.x)
    const runQuery = async () => {
      const statement = sql.value.trim()
      if (!statement || isLoading.value) return

      isLoading.value = true
      errorMessage.value = ''
      // Do not show prior results while the current query is in flight or on error.
      clearResults()

      try {
        const res = await api.post({ req: 'research_sql' }, { sql: statement })
        if (res && res.status === 'ok' && res.data) {
          const data = res.data
          columns.value = Array.isArray(data.columns) ? data.columns : []
          rows.value = Array.isArray(data.rows) ? data.rows : []
          rowCount.value =
            typeof data.rowCount === 'number' ? data.rowCount : rows.value.length
          truncated.value = data.truncated === true
        } else {
          // Error region shows the returned reason; prior results stay cleared (Req 5.7)
          errorMessage.value = (res && res.reason) || 'Query failed.'
        }
      } catch (e) {
        errorMessage.value =
          (e && e.message) || 'Query failed due to a network or server error.'
      } finally {
        hasRun.value = true
        isLoading.value = false
      }
    }

    // Copy the loaded result dataset as CSV (Req 3.2)
    const copyCsv = async () => {
      if (!hasResults.value) return
      try {
        await copyDatasetAsCsv(columns.value, rows.value)
        emit('show-info', 'Results copied to clipboard as CSV', 3)
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
      rowCount,
      truncated,
      isLoading,
      hasRun,
      errorMessage,
      hasResults,
      toggleTables,
      insertTableName,
      runQuery,
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
