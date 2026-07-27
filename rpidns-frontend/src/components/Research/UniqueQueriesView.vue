<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="h-100 overflow-auto p-2">
    <BCard class="h-100 d-flex flex-column">
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="3">
            <span
              v-b-tooltip.hover
              title="FQDNs requested for the first time within the selected period, and never requested before it"
              class="bold"
            ><i class="fas fa-fingerprint"></i>&nbsp;&nbsp;Unique queries</span>
          </BCol>
          <BCol cols="12" lg="9" class="text-end">
            <BButton
              v-b-tooltip.hover
              title="Refresh"
              variant="outline-secondary"
              size="sm"
              @click.stop="refreshTable"
            >
              <i class="fa fa-sync"></i>
            </BButton>

            <BButtonGroup size="sm">
              <BButton
                v-for="opt in qperiod_options"
                :key="opt.value"
                :variant="localPeriod === opt.value ? 'secondary' : 'outline-secondary'"
                :disabled="opt.disabled"
                @click="selectPeriod(opt.value)"
              >
                {{ opt.text }}
              </BButton>
            </BButtonGroup>
          </BCol>
        </BRow>
      </template>

      <!-- Custom Period Picker Modal -->
      <CustomPeriodPicker
        v-model:show="showCustomPicker"
        :initial-start="customPeriodStartDate"
        :initial-end="customPeriodEndDate"
        @apply="onCustomPeriodApply"
        @cancel="onCustomPeriodCancel"
      />

      <!-- Controls Row: CSV copy, Pagination, Filter -->
      <BRow class="d-none d-sm-flex">
        <BCol cols="2" lg="2">
          <BButton
            v-b-tooltip.hover
            title="Copy visible dataset as CSV"
            variant="outline-secondary"
            size="sm"
            :disabled="!hasDataset"
            @click="copyAsCsv"
          >
            <i class="fas fa-copy"></i>&nbsp;CSV
          </BButton>
        </BCol>
        <BCol cols="2" lg="2"></BCol>
        <BCol cols="3" lg="3">
          <BPagination
            v-model="uq_cp"
            :total-rows="uq_nrows"
            :per-page="uq_pp"
            aria-controls="uqlogs"
            size="sm"
            pills
            align="center"
            first-number
            last-number
          ></BPagination>
        </BCol>
        <BCol cols="5" lg="5">
          <BFormGroup label-cols-md="4" label-size="sm">
            <BInputGroup>
              <template #prepend>
                <BInputGroupText size="sm">
                  <i class="fas fa-filter fa-fw"></i>
                </BInputGroupText>
              </template>
              <BFormInput
                v-model="localFilter"
                placeholder="Type to search"
                size="sm"
                debounce="300"
              ></BFormInput>
              <template #append>
                <BButton
                  size="sm"
                  :disabled="!localFilter"
                  @click="localFilter = ''"
                >Clear</BButton>
              </template>
            </BInputGroup>
          </BFormGroup>
        </BCol>
      </BRow>

      <!-- Unique Queries Table -->
      <BRow>
        <BCol sm="12">
          <div ref="refLogsDiv">
            <BTableSimple
              id="uqlogs"
              :sticky-header="`${logs_height}px`"
              striped
              hover
              small
              responsive
            >
              <BThead>
                <BTr>
                  <BTh class="sortable-col" @click="sortByColumn('fqdn')">
                    Newly seen request {{ sortIndicator('fqdn') }}
                  </BTh>
                  <BTh class="sortable-col" @click="sortByColumn('cnt')">
                    Count {{ sortIndicator('cnt') }}
                  </BTh>
                  <BTh class="sortable-col" @click="sortByColumn('last_seen')">
                    Last seen {{ sortIndicator('last_seen') }}
                  </BTh>
                </BTr>
              </BThead>
              <BTbody>
                <!-- Error indication (Req 2.10) -->
                <BTr v-if="hasError">
                  <BTd colspan="3" class="text-center text-danger">
                    <i class="fas fa-triangle-exclamation"></i>&nbsp;
                    Failed to retrieve unique queries. Results may be incomplete.
                  </BTd>
                </BTr>
                <!-- Empty-state indication (Req 2.11) -->
                <BTr v-else-if="!isLoading && pagedItems.length === 0">
                  <BTd colspan="3" class="text-center text-muted">
                    No newly seen allowed FQDNs were found for the selected range
                    (every requested FQDN had already been requested before it).
                  </BTd>
                </BTr>
                <!-- Data rows -->
                <BTr v-for="item in pagedItems" :key="item.fqdn">
                  <BTd class="mw250" @contextmenu.prevent="openContextMenu($event, item)">{{ item.fqdn }}</BTd>
                  <BTd>{{ item.cnt }}</BTd>
                  <BTd>{{ formatDate(item.last_seen) }}</BTd>
                </BTr>
              </BTbody>
            </BTableSimple>
            <div v-if="isLoading" class="text-center m-0 p-0">
              <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
            </div>
          </div>
        </BCol>
      </BRow>
    </BCard>

    <!-- Context Menu (FQDN column — research links + network tool actions) -->
    <ContextMenu
      :visible="ctxMenu.visible"
      :domain="ctxMenu.domain"
      :x="ctxMenu.x"
      :y="ctxMenu.y"
      :actions="ctxMenuActions"
      @update:visible="ctxMenu.visible = $event"
      @action="onCtxMenuAction"
    />
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import ContextMenu from '../ContextMenu.vue'
import CustomPeriodPicker from '../CustomPeriodPicker.vue'
import { useApi } from '../../composables/useApi'
import { copyDatasetAsCsv } from '../../composables/useCsvExport'
import {
  getToolActions,
  filterByFqdn,
  sortRows,
  nextSortState
} from '../../composables/useNetworkTools'

export default {
  name: 'UniqueQueriesView',
  components: { ContextMenu, CustomPeriodPicker },
  props: {
    filter: { type: String, default: '' },
    period: { type: String, default: '30m' },
    logs_height: { type: Number, default: 150 },
    isActive: { type: Boolean, default: false },
    customStart: { type: Number, default: null },
    customEnd: { type: Number, default: null }
  },
  emits: ['add-ioc', 'custom-period-change', 'show-info'],
  setup(props, { emit }) {
    const { get, post } = useApi()

    const localFilter = ref(props.filter)
    const localPeriod = ref(props.period)
    const uq_cp = ref(1)
    const uq_pp = ref(100)
    const rawItems = ref([])          // full dataset loaded from the API
    const isLoading = ref(false)
    const hasError = ref(false)
    const datasetLoaded = ref(false)  // true once a successful fetch has completed

    // Sort state (Req 2.9) — starts on Count descending (most active first)
    const sort = ref({ column: 'cnt', descending: true })

    // CSV column order for export (Req 3.1)
    const csvColumns = ['FQDN', 'Count', 'Last seen']

    // --- Context menu state (FQDN cell) ---
    const ctxMenu = ref({ visible: false, domain: '', x: 0, y: 0 })

    // Research links (via ContextMenu showResearch) + network tool actions (Req 2.8)
    const ctxMenuActions = computed(() => {
      const actions = [
        { label: 'Block', icon: 'fas fa-ban' },
        { label: 'Allow', icon: 'fas fa-check-circle' }
      ]
      // Network tool actions from the single-source definitions (Req 7.1/7.6)
      for (const tool of getToolActions(ctxMenu.value.domain)) {
        actions.push({ label: tool.label, icon: 'fas fa-network-wired', name: tool.name })
      }
      return actions
    })

    const openContextMenu = (event, item) => {
      ctxMenu.value = {
        visible: true,
        domain: item.fqdn,
        x: event.clientX,
        y: event.clientY
      }
    }

    const onCtxMenuAction = async ({ actionName, domain }) => {
      if (actionName === 'Block') {
        emit('add-ioc', { ioc: domain, type: 'bl' })
        return
      }
      if (actionName === 'Allow') {
        emit('add-ioc', { ioc: domain, type: 'wl' })
        return
      }
      // Otherwise it is a network tool action — resolve by label
      const tool = getToolActions(domain).find(t => t.label === actionName)
      if (tool) {
        await runTool(tool.name, domain)
      }
    }

    // Run a network tool for a domain and surface the result (Req 7.2)
    const runTool = async (toolName, target) => {
      try {
        const resp = await post({ req: 'research_tool' }, { tool: toolName, target })
        if (resp && resp.status === 'ok') {
          const out = resp.data && resp.data.output ? String(resp.data.output) : ''
          emit('show-info', `${toolName} (${target}):\n${out}`, 6)
        } else {
          emit('show-info', `${toolName} failed for "${target}"`, 4)
        }
      } catch (e) {
        emit('show-info', `${toolName} failed for "${target}"`, 4)
      }
    }

    // --- Custom period state ---
    const customPeriodStart = ref(props.customStart)
    const customPeriodEnd = ref(props.customEnd)
    const showCustomPicker = ref(false)

    const customPeriodStartDate = computed(() =>
      customPeriodStart.value ? new Date(customPeriodStart.value * 1000) : null
    )
    const customPeriodEndDate = computed(() =>
      customPeriodEnd.value ? new Date(customPeriodEnd.value * 1000) : null
    )

    const qperiod_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: false }
    ]

    // --- Derived (client-side filter, sort, pagination) ---
    const filteredItems = computed(() => filterByFqdn(rawItems.value, localFilter.value))
    const sortedItems = computed(() =>
      sortRows(filteredItems.value, sort.value.column, sort.value.descending)
    )
    const uq_nrows = computed(() => sortedItems.value.length)
    const pagedItems = computed(() => {
      const start = (uq_cp.value - 1) * uq_pp.value
      return sortedItems.value.slice(start, start + uq_pp.value)
    })
    // The CSV copy control is enabled once a dataset has been successfully
    // loaded (Req 3.9). A loaded-but-empty range still yields a header-only
    // export (Req 3.8), so the control is not gated on row count.
    const hasDataset = computed(() => datasetLoaded.value && !hasError.value)

    // --- Data fetch (Req 2.1–2.5) ---
    const fetchData = async () => {
      isLoading.value = true
      hasError.value = false
      try {
        const params = {
          req: 'research_unique',
          period: localPeriod.value,
          filter: localFilter.value
        }
        if (localPeriod.value === 'custom' && customPeriodStart.value && customPeriodEnd.value) {
          params.start_dt = customPeriodStart.value
          params.end_dt = customPeriodEnd.value
        }
        const resp = await get(params)
        if (!resp || resp.status !== 'ok') {
          throw new Error(resp && resp.reason ? resp.reason : 'request failed')
        }
        rawItems.value = Array.isArray(resp.data) ? resp.data : []
        datasetLoaded.value = true
      } catch (error) {
        // Do not present partial results as complete (Req 2.10)
        rawItems.value = []
        hasError.value = true
        datasetLoaded.value = false
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => { uq_cp.value = 1; fetchData() }

    const selectPeriod = (value) => {
      if (value === 'custom') {
        showCustomPicker.value = true
      } else {
        localPeriod.value = value
        uq_cp.value = 1
        fetchData()
      }
    }

    const onCustomPeriodApply = ({ start_dt, end_dt }) => {
      customPeriodStart.value = start_dt
      customPeriodEnd.value = end_dt
      localPeriod.value = 'custom'
      showCustomPicker.value = false
      uq_cp.value = 1
      emit('custom-period-change', { start_dt, end_dt })
      fetchData()
    }

    const onCustomPeriodCancel = () => { showCustomPicker.value = false }

    // --- Sorting (Req 2.9) ---
    const sortByColumn = (column) => {
      sort.value = nextSortState(sort.value, column)
      uq_cp.value = 1
    }
    const sortIndicator = (column) => {
      if (sort.value.column !== column) return ''
      return sort.value.descending ? '▾' : '▴'
    }

    // --- CSV copy (Req 3.1, 3.6, 3.7, 3.9) ---
    const copyAsCsv = async () => {
      if (!hasDataset.value) return
      const rows = sortedItems.value.map(item => [item.fqdn, item.cnt, formatDate(item.last_seen)])
      try {
        await copyDatasetAsCsv(csvColumns, rows)
        emit('show-info', 'Dataset copied to clipboard as CSV', 3)
      } catch (e) {
        emit('show-info', 'Failed to copy dataset to clipboard', 3)
      }
    }

    const formatDate = (value) => {
      if (value == null || value === '') return ''
      const date = new Date(value)
      return isNaN(date.getTime()) ? String(value) : date.toLocaleString()
    }

    // --- Watchers ---
    watch(() => props.filter, (newVal) => { localFilter.value = newVal })
    watch(() => props.period, (newVal) => {
      localPeriod.value = newVal
      if (newVal !== 'custom') {
        customPeriodStart.value = null
        customPeriodEnd.value = null
      }
    })
    watch(() => props.customStart, (newVal) => { customPeriodStart.value = newVal })
    watch(() => props.customEnd, (newVal) => { customPeriodEnd.value = newVal })
    watch(localFilter, () => { uq_cp.value = 1 })
    watch(() => props.isActive, (newVal, oldVal) => {
      if (newVal && !oldVal) fetchData()
    })

    onMounted(() => { fetchData() })

    return {
      localFilter, localPeriod, uq_cp, uq_pp, uq_nrows,
      pagedItems, hasDataset, isLoading, hasError, qperiod_options,
      showCustomPicker, customPeriodStartDate, customPeriodEndDate,
      ctxMenu, ctxMenuActions, openContextMenu, onCtxMenuAction,
      refreshTable, selectPeriod, onCustomPeriodApply, onCustomPeriodCancel,
      sortByColumn, sortIndicator, copyAsCsv, formatDate
    }
  }
}
</script>

<style scoped>
.mw250 { max-width: 250px; }
.sortable-col { cursor: pointer; user-select: none; }
</style>
