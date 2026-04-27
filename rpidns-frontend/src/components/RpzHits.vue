<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="h-100 overflow-auto p-2">
    <BCard class="h-100 d-flex flex-column">
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fa fa-shield-alt"></i>&nbsp;&nbsp;RPZ log</span>
          </BCol>
          <BCol cols="12" lg="10" class="text-end">
            <BFormCheckbox
              v-model="autoRefreshEnabled"
              switch
              size="sm"
              class="d-inline-block ms-2 me-3"
              v-b-tooltip.hover
              title="Auto-refresh every 60s"
            >
              <small>Auto</small>
            </BFormCheckbox>
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
                v-for="opt in period_options" 
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

      <!-- Controls Row: Logs/Stats Toggle, Pagination, Filter -->
      <BRow class="d-none d-sm-flex">
        <BCol cols="1" lg="1">
          <BButtonGroup size="sm">
            <BButton 
              :variant="hits_ltype === 'logs' ? 'secondary' : 'outline-secondary'"
              @click="selectLtype('logs')"
            >Logs</BButton>
            <BButton 
              :variant="hits_ltype === 'stats' ? 'secondary' : 'outline-secondary'"
              @click="selectLtype('stats')"
            >Stats</BButton>
          </BButtonGroup>
        </BCol>
        <BCol cols="3" lg="3"></BCol>
        <BCol cols="3" lg="3">
          <BPagination 
            v-model="hits_cp" 
            :total-rows="hits_nrows" 
            :per-page="hits_pp" 
            aria-controls="hits" 
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

      <!-- RPZ Hits Table -->
      <BRow>
        <BCol sm="12">
          <div ref="refHitsDiv">
            <BTableSimple 
              id="hits" 
              :sticky-header="`${logs_height}px`" 
              striped 
              hover 
              small 
              responsive
            >
              <BThead>
                <BTr>
                  <BTh v-if="hits_ltype === 'logs'" sortable @click="sortBy('dtz')">Local Time</BTh>
                  <BTh class="d-none d-sm-table-cell">
                    <BFormCheckbox v-if="hits_ltype === 'stats'" v-model="hits_select_fields" value="cname">Client</BFormCheckbox>
                    <span v-else>Client</span>
                  </BTh>
                  <BTh>
                    <BFormCheckbox v-if="hits_ltype === 'stats'" v-model="hits_select_fields" value="fqdn">Request</BFormCheckbox>
                    <span v-else>Request</span>
                  </BTh>
                  <BTh class="d-none d-lg-table-cell">
                    <BFormCheckbox v-if="hits_ltype === 'stats'" v-model="hits_select_fields" value="action">Action</BFormCheckbox>
                    <span v-else>Action</span>
                  </BTh>
                  <BTh class="d-none d-lg-table-cell">
                    <BFormCheckbox v-if="hits_ltype === 'stats'" v-model="hits_select_fields" value="rule">Rule</BFormCheckbox>
                    <span v-else>Rule</span>
                  </BTh>
                  <BTh class="d-none d-lg-table-cell">
                    <BFormCheckbox v-if="hits_ltype === 'stats'" v-model="hits_select_fields" value="rule_type">Type</BFormCheckbox>
                    <span v-else>Type</span>
                  </BTh>
                  <BTh>Count</BTh>
                </BTr>
              </BThead>
              <BTbody>
                <BTr v-for="item in tableItems" :key="item.rowid">
                  <BTd v-if="hits_ltype === 'logs'">{{ formatDate(item.dtz) }}</BTd>
                  <BTd class="mw200 d-none d-sm-table-cell" @contextmenu.prevent="openColMenu($event, 'client_ip', 'client', item.cname)">
                    <span v-b-tooltip.hover :title="`Mac: ${item.mac || ''}\nIP: ${item.client_ip || ''}\nVendor: ${item.vendor || ''}`">
                      {{ item.cname }}
                    </span>
                  </BTd>
                  <BTd class="mw200" @contextmenu.prevent="openContextMenu($event, item)">{{ item.fqdn }}</BTd>
                  <BTd class="d-none d-lg-table-cell" @contextmenu.prevent="openColMenu($event, 'action', 'action', item.action)">{{ item.action }}</BTd>
                  <BTd class="mw300 d-none d-lg-table-cell" @contextmenu.prevent="openFeedFilterMenu($event, item)">{{ item.rule }}</BTd>
                  <BTd class="d-none d-lg-table-cell">{{ item.rule_type }}</BTd>
                  <BTd>{{ item.cnt }}</BTd>
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

    <!-- Context Menu (FQDN column — research + allow + filter) -->
    <ContextMenu
      :visible="ctxMenu.visible"
      :domain="ctxMenu.domain"
      :x="ctxMenu.x"
      :y="ctxMenu.y"
      :actions="ctxMenuActions"
      @update:visible="ctxMenu.visible = $event"
      @action="onCtxMenuAction"
    />

    <!-- Column Filter Menu (other columns — filter only) -->
    <ContextMenu
      :visible="colMenu.visible"
      :domain="colMenu.domain"
      :x="colMenu.x"
      :y="colMenu.y"
      :actions="colMenuActions"
      :show-research="false"
      @update:visible="colMenu.visible = $event"
      @action="onColMenuAction"
    />
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'
import ContextMenu from './ContextMenu.vue'
import CustomPeriodPicker from './CustomPeriodPicker.vue'
import { useAutoRefresh } from '../composables/useAutoRefresh'
import { useSmartActions } from '../composables/useSmartActions'

export default {
  name: 'RpzHits',
  components: { ResearchLinks, ContextMenu, CustomPeriodPicker },
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
    const localFilter = ref(props.filter)
    const localPeriod = ref(props.period)
    const hits_ltype = ref('logs')
    const hits_cp = ref(1)
    const hits_nrows = ref(0)
    const hits_pp = ref(100)
    const hits_select_fields = ref(['cname', 'fqdn', 'action', 'rule', 'rule_type'])
    const tableItems = ref([])
    const isLoading = ref(false)
    const hits_updatetime = ref(0)
    const sortField = ref('dtz')
    const sortDesc = ref(true)

    // Context menu state (for FQDN/Request column)
    const ctxMenu = ref({
      visible: false,
      domain: '',
      feed: '',
      x: 0,
      y: 0
    })

    // Column filter context menu state (for other columns)
    const colMenu = ref({
      visible: false,
      domain: '',
      x: 0,
      y: 0,
      field: '',
      label: '',
      value: ''
    })

    // Smart actions
    const { smartAllow } = useSmartActions()

    // Extract feed name from rule by removing the domain prefix
    const extractFeedFromRule = (item) => {
      if (item.rule && item.fqdn) {
        const fqdnPrefix = item.fqdn + '.'
        if (item.rule.startsWith(fqdnPrefix)) {
          return item.rule.substring(fqdnPrefix.length)
        }
        if (item.feed) return item.feed
      }
      return item.feed || ''
    }

    // Context menu actions for FQDN column — allow + filter by request
    const ctxMenuActions = computed(() => {
      return [
        { label: 'Allow', icon: 'fas fa-check-circle' },
        { label: 'Filter by request', icon: 'fas fa-filter' }
      ]
    })

    // Column filter menu actions
    const colMenuActions = computed(() => {
      return [{ label: `Filter by ${colMenu.value.label}`, icon: 'fas fa-filter' }]
    })

    // Open context menu on FQDN cell right-click
    const openContextMenu = (event, item) => {
      colMenu.value.visible = false
      ctxMenu.value = {
        visible: true,
        domain: item.fqdn,
        feed: item.feed || '',
        x: event.clientX,
        y: event.clientY
      }
    }

    // Open column filter menu on right-click of other columns
    const openColMenu = (event, field, label, value) => {
      ctxMenu.value.visible = false
      colMenu.value = {
        visible: true,
        domain: String(value || ''),
        x: event.clientX,
        y: event.clientY,
        field: field,
        label: label,
        value: String(value || '')
      }
    }

    // Open feed filter menu from rule column
    const openFeedFilterMenu = (event, item) => {
      ctxMenu.value.visible = false
      const feedName = extractFeedFromRule(item)
      colMenu.value = {
        visible: true,
        domain: feedName || item.rule || '',
        x: event.clientX,
        y: event.clientY,
        field: 'feed',
        label: 'feed',
        value: feedName
      }
    }

    // Handle FQDN context menu action clicks
    const onCtxMenuAction = async ({ actionName, domain }) => {
      if (actionName === 'Allow') {
        const result = await smartAllow(domain, ctxMenu.value.feed)
        if (result.action === 'removed') {
          emit('show-info', `Removed "${domain}" from block list`, 3)
          refreshTable()
        } else if (result.action === 'add-ioc') {
          emit('add-ioc', { ioc: domain, type: 'wl' })
        } else if (result.action === 'error') {
          emit('show-info', result.error || 'Error performing allow action', 3)
        }
      } else if (actionName === 'Filter by request') {
        localFilter.value = 'fqdn=' + domain
      }
    }

    // Handle column filter menu action clicks
    const onColMenuAction = ({ actionName }) => {
      if (actionName.startsWith('Filter by ')) {
        localFilter.value = colMenu.value.field + '=' + colMenu.value.value
      }
    }

    // Custom period state
    const customPeriodStart = ref(props.customStart)
    const customPeriodEnd = ref(props.customEnd)
    const showCustomPicker = ref(false)

    // Computed Date objects for CustomPeriodPicker initial values
    const customPeriodStartDate = computed(() => {
      return customPeriodStart.value ? new Date(customPeriodStart.value * 1000) : null
    })
    const customPeriodEndDate = computed(() => {
      return customPeriodEnd.value ? new Date(customPeriodEnd.value * 1000) : null
    })

    const period_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: false }
    ]

    const apiUrl = computed(() => {
      let url = '/rpi_admin/rpidata.php?req=hits_raw' +
        '&period=' + localPeriod.value +
        '&cp=' + hits_cp.value +
        '&filter=' + localFilter.value +
        '&pp=' + hits_pp.value +
        '&ltype=' + hits_ltype.value +
        '&fields=' + hits_select_fields.value.join(',') +
        '&sortBy=' + sortField.value +
        '&sortDesc=' + sortDesc.value
      if (localPeriod.value === 'custom' && customPeriodStart.value && customPeriodEnd.value) {
        url += '&start_dt=' + customPeriodStart.value + '&end_dt=' + customPeriodEnd.value
      }
      return url
    })

    const fetchData = async () => {
      isLoading.value = true
      try {
        const response = await axios.get(apiUrl.value)
        const items = response.data.data
        if (/DOCTYPE html/.test(items)) {
          window.location.reload(false)
        }
        tableItems.value = items || []
        hits_nrows.value = parseInt(response.data.records) || 0
      } catch (error) {
        tableItems.value = []
        hits_nrows.value = 0
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => {
      fetchData()
      hits_updatetime.value = Date.now()
    }

    // Auto-refresh setup
    const { autoRefreshEnabled } = useAutoRefresh(
      'rpidns_autorefresh_rpzhits',
      refreshTable,
      () => props.isActive
    )

    const onPeriodChange = () => { hits_cp.value = 1; fetchData() }
    const selectPeriod = (value) => {
      if (value === 'custom') {
        showCustomPicker.value = true
      } else {
        localPeriod.value = value
        hits_cp.value = 1
        fetchData()
      }
    }

    // Custom period handlers
    const onCustomPeriodApply = ({ start_dt, end_dt }) => {
      customPeriodStart.value = start_dt
      customPeriodEnd.value = end_dt
      localPeriod.value = 'custom'
      showCustomPicker.value = false
      hits_cp.value = 1
      // Emit custom period change to parent for persistence across tabs
      emit('custom-period-change', { start_dt, end_dt })
      fetchData()
    }

    const onCustomPeriodCancel = () => {
      showCustomPicker.value = false
    }

    const switchStats = () => { tableItems.value = []; fetchData() }
    const selectLtype = (value) => {
      hits_ltype.value = value
      tableItems.value = []
      fetchData()
    }
    const filterBy = (field, value) => { localFilter.value = field + '=' + value }
    const extractRuleDomain = (item) => {
      if (item.rule && item.feed) {
        const feedSuffix = '.' + item.feed
        const idx = item.rule.indexOf(feedSuffix)
        if (idx > 0) { return item.rule.substring(0, idx) }
      }
      return item.rule || ''
    }
    const allowDomain = (domain) => { emit('add-ioc', { ioc: domain, type: 'wl' }) }
    const allowRule = (item) => { const domain = extractRuleDomain(item); emit('add-ioc', { ioc: domain, type: 'wl' }) }
    const formatDate = (value) => { const date = new Date(value); return date.toLocaleString() }
    const sortByField = (field) => {
      if (sortField.value === field) { sortDesc.value = !sortDesc.value }
      else { sortField.value = field; sortDesc.value = true }
      fetchData()
    }

    watch(() => props.filter, (newVal) => { localFilter.value = newVal })
    watch(() => props.period, (newVal) => { 
      localPeriod.value = newVal
      // If switching away from custom, clear custom period state
      if (newVal !== 'custom') {
        customPeriodStart.value = null
        customPeriodEnd.value = null
      }
    })
    watch(() => props.customStart, (newVal) => { customPeriodStart.value = newVal })
    watch(() => props.customEnd, (newVal) => { customPeriodEnd.value = newVal })
    watch(localFilter, () => { hits_cp.value = 1; fetchData() })
    watch(hits_cp, () => { fetchData() })
    // Refresh when stats columns are enabled/disabled
    watch(hits_select_fields, () => { 
      if (hits_ltype.value === 'stats') {
        hits_cp.value = 1
        fetchData() 
      }
    }, { deep: true })
    // Refresh when tab becomes active
    watch(() => props.isActive, (newVal, oldVal) => {
      if (newVal && !oldVal) {
        fetchData()
      }
    })

    onMounted(() => { fetchData() })

    return {
      localFilter, localPeriod, hits_ltype, hits_cp, hits_nrows, hits_pp,
      hits_select_fields, tableItems, isLoading, period_options,
      autoRefreshEnabled, showCustomPicker, customPeriodStart, customPeriodEnd,
      customPeriodStartDate, customPeriodEndDate,
      ctxMenu, ctxMenuActions, openContextMenu, onCtxMenuAction, extractFeedFromRule,
      colMenu, colMenuActions, openColMenu, openFeedFilterMenu, onColMenuAction,
      refreshTable, onPeriodChange, selectPeriod, switchStats, selectLtype, filterBy, extractRuleDomain,
      allowDomain, allowRule, formatDate, sortBy: sortByField, onCustomPeriodApply, onCustomPeriodCancel
    }
  }
}
</script>

<style scoped>
.mw200 { max-width: 200px; }
.mw300 { max-width: 300px; }
</style>
