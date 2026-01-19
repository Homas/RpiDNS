<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="h-100 overflow-auto p-2">
    <BCard class="h-100 d-flex flex-column">
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span>
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

      <!-- Controls Row: Logs/Stats Toggle, Pagination, Filter -->
      <BRow class="d-none d-sm-flex">
        <BCol cols="1" lg="1">
          <BButtonGroup size="sm">
            <BButton 
              :variant="query_ltype === 'logs' ? 'secondary' : 'outline-secondary'"
              @click="selectLtype('logs')"
            >Logs</BButton>
            <BButton 
              :variant="query_ltype === 'stats' ? 'secondary' : 'outline-secondary'"
              @click="selectLtype('stats')"
            >Stats</BButton>
          </BButtonGroup>
        </BCol>
        <BCol cols="3" lg="3"></BCol>
        <BCol cols="3" lg="3">
          <BPagination 
            v-model="qlogs_cp" 
            :total-rows="qlogs_nrows" 
            :per-page="qlogs_pp" 
            aria-controls="qlogs" 
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

      <!-- Query Log Table -->
      <BRow>
        <BCol sm="12">
          <div ref="refLogsDiv">
            <BTableSimple 
              id="qlogs" 
              :sticky-header="`${logs_height}px`" 
              striped 
              hover 
              small 
              responsive
            >
              <BThead>
                <BTr>
                  <BTh v-if="query_ltype === 'logs'" sortable @click="sortBy('dtz')">Local Time</BTh>
                  <BTh class="d-none d-sm-table-cell">
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="cname">Client</BFormCheckbox>
                    <span v-else>Client</span>
                  </BTh>
                  <BTh class="d-none d-lg-table-cell">
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="server">Server</BFormCheckbox>
                    <span v-else>Server</span>
                  </BTh>
                  <BTh>
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="fqdn">Request</BFormCheckbox>
                    <span v-else>Request</span>
                  </BTh>
                  <BTh>
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="type">Type</BFormCheckbox>
                    <span v-else>Type</span>
                  </BTh>
                  <BTh class="d-none d-xl-table-cell">
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="class">Class</BFormCheckbox>
                    <span v-else>Class</span>
                  </BTh>
                  <BTh class="d-none d-xl-table-cell">
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="options">Options</BFormCheckbox>
                    <span v-else>Options</span>
                  </BTh>
                  <BTh>Count</BTh>
                  <BTh>
                    <BFormCheckbox v-if="query_ltype === 'stats'" v-model="qlogs_select_fields" value="action">Action</BFormCheckbox>
                    <span v-else>Action</span>
                  </BTh>
                </BTr>
              </BThead>
              <BTbody>
                <BTr v-for="item in tableItems" :key="item.rowid">
                  <BTd v-if="query_ltype === 'logs'">{{ formatDate(item.dtz) }}</BTd>
                  <BTd class="mw200 d-none d-sm-table-cell">
                    <span v-b-tooltip.hover :title="`Mac: ${item.mac || ''}\nIP: ${item.client_ip || ''}\nVendor: ${item.vendor || ''}`">
                      {{ item.cname }}
                    </span>
                  </BTd>
                  <BTd class="mw200 d-none d-lg-table-cell">{{ item.server }}</BTd>
                  <BTd class="mw250">{{ item.fqdn }}</BTd>
                  <BTd>{{ item.type }}</BTd>
                  <BTd class="d-none d-xl-table-cell">{{ item.class }}</BTd>
                  <BTd class="d-none d-xl-table-cell">{{ item.options }}</BTd>
                  <BTd>{{ item.cnt }}</BTd>
                  <BTd>
                    <span v-if="item.action === 'blocked'"><i class="fas fa-hand-paper salmon"></i> Block</span>
                    <span v-if="item.action === 'allowed'"><i class="fas fa-check green"></i> Allow</span>
                  </BTd>
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
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'
import CustomPeriodPicker from './CustomPeriodPicker.vue'
import { useAutoRefresh } from '../composables/useAutoRefresh'

export default {
  name: 'QueryLog',
  components: { ResearchLinks, CustomPeriodPicker },
  props: {
    filter: { type: String, default: '' },
    period: { type: String, default: '30m' },
    logs_height: { type: Number, default: 150 },
    isActive: { type: Boolean, default: false },
    customStart: { type: Number, default: null },
    customEnd: { type: Number, default: null }
  },
  emits: ['add-ioc', 'custom-period-change'],
  setup(props, { emit }) {
    const localFilter = ref(props.filter)
    const localPeriod = ref(props.period)
    const query_ltype = ref('logs')
    const qlogs_cp = ref(1)
    const qlogs_nrows = ref(0)
    const qlogs_pp = ref(100)
    const qlogs_select_fields = ref(['cname', 'server', 'fqdn', 'type', 'class', 'options', 'action'])
    const tableItems = ref([])
    const isLoading = ref(false)
    const logs_updatetime = ref(0)
    const sortField = ref('dtz')
    const sortDesc = ref(true)

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

    const qperiod_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: false }
    ]

    const apiUrl = computed(() => {
      let url = '/rpi_admin/rpidata.php?req=queries_raw' +
        '&period=' + localPeriod.value +
        '&cp=' + qlogs_cp.value +
        '&filter=' + localFilter.value +
        '&pp=' + qlogs_pp.value +
        '&ltype=' + query_ltype.value +
        '&fields=' + qlogs_select_fields.value.join(',') +
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
        qlogs_nrows.value = parseInt(response.data.records) || 0
      } catch (error) {
        tableItems.value = []
        qlogs_nrows.value = 0
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => {
      fetchData()
      logs_updatetime.value = Date.now()
    }

    // Auto-refresh setup
    const { autoRefreshEnabled } = useAutoRefresh(
      'rpidns_autorefresh_querylog',
      refreshTable,
      () => props.isActive
    )

    const onPeriodChange = () => { qlogs_cp.value = 1; fetchData() }
    const selectPeriod = (value) => {
      if (value === 'custom') {
        showCustomPicker.value = true
      } else {
        localPeriod.value = value
        qlogs_cp.value = 1
        fetchData()
      }
    }

    // Custom period handlers
    const onCustomPeriodApply = ({ start_dt, end_dt }) => {
      customPeriodStart.value = start_dt
      customPeriodEnd.value = end_dt
      localPeriod.value = 'custom'
      showCustomPicker.value = false
      qlogs_cp.value = 1
      // Emit custom period change to parent for persistence across tabs
      emit('custom-period-change', { start_dt, end_dt })
      fetchData()
    }

    const onCustomPeriodCancel = () => {
      showCustomPicker.value = false
    }

    const switchStats = () => { tableItems.value = []; fetchData() }
    const selectLtype = (value) => {
      query_ltype.value = value
      tableItems.value = []
      fetchData()
    }
    const filterBy = (field, value) => { localFilter.value = field + '=' + value }
    const blockDomain = (domain) => { emit('add-ioc', { ioc: domain, type: 'bl' }) }
    const allowDomain = (domain) => { emit('add-ioc', { ioc: domain, type: 'wl' }) }
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
    watch(localFilter, () => { qlogs_cp.value = 1; fetchData() })
    watch(qlogs_cp, () => { fetchData() })
    // Refresh when stats columns are enabled/disabled
    watch(qlogs_select_fields, () => { 
      if (query_ltype.value === 'stats') {
        qlogs_cp.value = 1
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
      localFilter, localPeriod, query_ltype, qlogs_cp, qlogs_nrows, qlogs_pp,
      qlogs_select_fields, tableItems, isLoading, qperiod_options,
      autoRefreshEnabled, showCustomPicker, customPeriodStart, customPeriodEnd,
      customPeriodStartDate, customPeriodEndDate,
      refreshTable, onPeriodChange, selectPeriod, switchStats, selectLtype, filterBy, blockDomain, allowDomain,
      formatDate, sortBy: sortByField, onCustomPeriodApply, onCustomPeriodCancel
    }
  }
}
</script>

<style scoped>
.salmon { color: salmon; }
.green { color: green; }
.mw200 { max-width: 200px; }
.mw250 { max-width: 250px; }
</style>
