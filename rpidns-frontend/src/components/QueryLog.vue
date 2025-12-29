<template>
  <div>
    <div class="v-spacer"></div>
    <BCard>
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span>
          </BCol>
          <BCol cols="12" lg="10" class="text-right">
            <BFormGroup class="m-0">
              <BButton 
                v-b-tooltip.hover 
                title="Refresh" 
                variant="outline-secondary" 
                size="sm" 
                @click.stop="refreshTable"
              >
                <i class="fa fa-sync"></i>
              </BButton>
              <BFormRadioGroup 
                v-model="localPeriod" 
                :options="qperiod_options" 
                buttons 
                size="sm" 
                @update:model-value="onPeriodChange"
              ></BFormRadioGroup>
            </BFormGroup>
          </BCol>
        </BRow>
      </template>

      <!-- Controls Row: Logs/Stats Toggle, Pagination, Filter -->
      <BRow class="d-none d-sm-flex">
        <BCol cols="1" lg="1">
          <BFormRadioGroup 
            buttons 
            size="sm" 
            v-model="query_ltype" 
            @update:model-value="switchStats"
          >
            <BFormRadio value="logs">Logs</BFormRadio>
            <BFormRadio value="stats">Stats</BFormRadio>
          </BFormRadioGroup>
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

export default {
  name: 'QueryLog',
  components: { ResearchLinks },
  props: {
    filter: { type: String, default: '' },
    period: { type: String, default: '30m' },
    logs_height: { type: Number, default: 150 }
  },
  emits: ['add-ioc'],
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

    const qperiod_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: true }
    ]

    const apiUrl = computed(() => {
      return '/rpi_admin/rpidata.php?req=queries_raw' +
        '&period=' + localPeriod.value +
        '&cp=' + qlogs_cp.value +
        '&filter=' + localFilter.value +
        '&pp=' + qlogs_pp.value +
        '&ltype=' + query_ltype.value +
        '&fields=' + qlogs_select_fields.value.join(',') +
        '&sortBy=' + sortField.value +
        '&sortDesc=' + sortDesc.value
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
      if ((Date.now() - logs_updatetime.value) > 60 * 1000) {
        fetchData()
        logs_updatetime.value = Date.now()
      }
    }

    const onPeriodChange = () => { qlogs_cp.value = 1; fetchData() }
    const switchStats = () => { tableItems.value = []; fetchData() }
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
    watch(() => props.period, (newVal) => { localPeriod.value = newVal })
    watch(localFilter, () => { qlogs_cp.value = 1; fetchData() })
    watch(qlogs_cp, () => { fetchData() })

    onMounted(() => { fetchData() })

    return {
      localFilter, localPeriod, query_ltype, qlogs_cp, qlogs_nrows, qlogs_pp,
      qlogs_select_fields, tableItems, isLoading, qperiod_options,
      refreshTable, onPeriodChange, switchStats, filterBy, blockDomain, allowDomain,
      formatDate, sortBy: sortByField
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
