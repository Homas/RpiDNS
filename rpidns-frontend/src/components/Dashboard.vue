<template>
  <div class="h-100 overflow-auto p-2">
    <BCard class="h-100 d-flex flex-column">
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fas fa-tachometer-alt"></i>&nbsp;&nbsp;Dashboard</span>
          </BCol>
          <BCol cols="12" lg="10" class="text-end">
            <BButton 
              v-b-tooltip.hover 
              title="Refresh" 
              variant="outline-secondary" 
              size="sm" 
              @click.stop="refreshDash"
            >
              <i class="fa fa-sync"></i>
            </BButton>&nbsp;&nbsp;&nbsp;
            <BButtonGroup size="sm">
              <BButton 
                v-for="opt in period_options" 
                :key="opt.value"
                :variant="dash_period === opt.value ? 'secondary' : 'outline-secondary'"
                :disabled="opt.disabled"
                @click="selectPeriod(opt.value)"
              >
                {{ opt.text }}
              </BButton>
            </BButtonGroup>
          </BCol>
        </BRow>
      </template>

      <!-- First Row: Allowed Stats -->
      <div class="row g-2 mb-2">
        <!-- TopX Allowed Requests -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Allowed Requests</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXReq" :key="item.fname" class="mouseoverpointer" @click="onAllowedRequestClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXReq" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- TopX Allowed Clients -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Allowed Clients</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXClient" :key="item.fname" class="mouseoverpointer" @click="onAllowedClientClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXClient" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- TopX Allowed Request Types -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Allowed Request Types</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXReqType" :key="item.fname" class="mouseoverpointer" @click="onRequestTypeClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXReqType" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- RpiDNS Stats -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>RpiDNS</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in serverStats" :key="item.fname">
                    <BTd>{{ item.fname }}</BTd>
                    <BTd class="text-end">
                      <span v-if="item.fname === 'CPU load'" v-b-tooltip.hover="'Load in 1 minute, 5 minutes, 15 minutes'">{{ item.cnt }}</span>
                      <span v-else>{{ item.cnt }}</span>
                    </BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.serverStats" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <!-- Second Row: Blocked Stats -->
      <div class="row g-2 mb-2">
        <!-- TopX Blocked Requests -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Blocked Requests</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXBreq" :key="item.fname" class="mouseoverpointer" @click="onBlockedRequestClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXBreq" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- TopX Blocked Clients -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Blocked Clients</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXBclient" :key="item.fname" class="mouseoverpointer" @click="onBlockedClientClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXBclient" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- TopX Feeds -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Feeds</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXFeeds" :key="item.fname" class="mouseoverpointer" @click="onFeedClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXFeeds" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>

        <!-- TopX Servers -->
        <div class="col-12 col-md-6 col-lg-3">
          <BCard class="widget-card">
            <template #header><small>TopX Servers</small></template>
            <div class="widget-body">
              <BTableSimple striped hover small class="mb-0">
                <BTbody>
                  <BTr v-for="item in topXServer" :key="item.fname" class="mouseoverpointer" @click="onServerClick(item)">
                    <BTd class="text-truncate" style="max-width: 200px;">{{ item.fname }}</BTd>
                    <BTd class="text-end">{{ item.cnt }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
              <div v-if="loading.topXServer" class="text-center p-2">
                <BSpinner small></BSpinner>&nbsp;Loading...
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <!-- QPS Chart Row -->
      <BCard class="flex-grow-1">
        <template #header><small>Queries per Minute</small></template>
        <apexchart type="area" height="200" width="99%" :options="qps_options" :series="qps_series"></apexchart>
      </BCard>
    </BCard>
  </div>
</template>


<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'

export default {
  name: 'Dashboard',
  components: {
    ResearchLinks
  },
  emits: ['navigate', 'add-ioc'],
  setup(props, { emit }) {
    const dash_period = ref('30m')

    // Data for tables
    const topXReq = ref([])
    const topXClient = ref([])
    const topXReqType = ref([])
    const serverStats = ref([])
    const topXBreq = ref([])
    const topXBclient = ref([])
    const topXFeeds = ref([])
    const topXServer = ref([])

    // Loading states
    const loading = reactive({
      topXReq: false,
      topXClient: false,
      topXReqType: false,
      serverStats: false,
      topXBreq: false,
      topXBclient: false,
      topXFeeds: false,
      topXServer: false
    })

    // Period options for radio group
    const period_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: true }
    ]

    // QPS Chart data
    const qps_series = ref([])
    const qps_options = {
      colors: ['#008FFB', '#FA4443'],
      chart: { id: 'qps-stats' },
      dataLabels: { enabled: false },
      xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
      tooltip: { x: { format: 'dd MMM yyyy H:mm' } },
      yaxis: { min: 0 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.8 } }
    }

    // Fetch table data from API
    const fetchTableData = async (endpoint, loadingKey, dataRef) => {
      loading[loadingKey] = true
      try {
        const response = await axios.get(`/rpi_admin/rpidata.php?req=${endpoint}&period=${dash_period.value}&sortBy=cnt&sortDesc=true`)
        const items = response.data.data
        if (/DOCTYPE html/.test(items)) {
          window.location.reload(false)
        }
        dataRef.value = items || []
      } catch (error) {
        dataRef.value = []
      } finally {
        loading[loadingKey] = false
      }
    }

    // Refresh QPS chart data
    const refreshDashQPS = async () => {
      try {
        const response = await axios.get('/rpi_admin/rpidata.php?req=qps_chart&period=' + dash_period.value)
        qps_series.value = response.data
      } catch (error) {
        console.error('Error fetching QPS data:', error)
      }
    }

    // Refresh all dashboard data
    const refreshDash = () => {
      refreshDashQPS()
      fetchTableData('dash_topX_req', 'topXReq', topXReq)
      fetchTableData('dash_topX_client', 'topXClient', topXClient)
      fetchTableData('dash_topX_req_type', 'topXReqType', topXReqType)
      fetchTableData('server_stats', 'serverStats', serverStats)
      fetchTableData('dash_topX_breq', 'topXBreq', topXBreq)
      fetchTableData('dash_topX_bclient', 'topXBclient', topXBclient)
      fetchTableData('dash_topX_feeds', 'topXFeeds', topXFeeds)
      fetchTableData('dash_topX_server', 'topXServer', topXServer)
    }

    const onPeriodChange = () => { refreshDash() }

    const selectPeriod = (value) => {
      if (value !== 'custom') {
        dash_period.value = value
        refreshDash()
      }
    }

    // Navigation helpers
    const showQueries = (filter) => { emit('navigate', { tab: 1, filter, period: dash_period.value, type: 'qlogs' }) }
    const showHits = (filter) => { emit('navigate', { tab: 2, filter, period: dash_period.value, type: 'hits' }) }
    const showQueriesForClient = (item) => {
      const filter = (item.mac == null || item.mac === '') ? 'client_ip=' + item.fname : 'mac=' + item.mac
      showQueries(filter)
    }
    const showHitsForClient = (item) => {
      const filter = (item.mac == null || item.mac === '') ? 'client_ip=' + item.fname : 'mac=' + item.mac
      showHits(filter)
    }

    // Row click handlers
    const onAllowedRequestClick = (item) => { showQueries('fqdn=' + item.fname) }
    const onAllowedClientClick = (item) => { showQueriesForClient(item) }
    const onRequestTypeClick = (item) => { showQueries('type=' + item.fname) }
    const onBlockedRequestClick = (item) => { showHits('fqdn=' + item.fname) }
    const onBlockedClientClick = (item) => { showHitsForClient(item) }
    const onFeedClick = (item) => { showHits('feed=' + item.fname) }
    const onServerClick = (item) => { showQueries('server=' + item.fname) }

    // Block/Allow actions
    const blockDomain = (domain) => { emit('add-ioc', { ioc: domain, type: 'bl' }) }
    const allowDomain = (domain) => { emit('add-ioc', { ioc: domain, type: 'wl' }) }

    onMounted(() => { refreshDash() })

    return {
      dash_period, period_options, topXReq, topXClient, topXReqType, serverStats,
      topXBreq, topXBclient, topXFeeds, topXServer, loading, qps_series, qps_options,
      refreshDash, refreshDashQPS, onPeriodChange, selectPeriod, showQueries, showHits,
      showQueriesForClient, showHitsForClient, onAllowedRequestClick, onAllowedClientClick,
      onRequestTypeClick, onBlockedRequestClick, onBlockedClientClick, onFeedClick,
      onServerClick, blockDomain, allowDomain
    }
  }
}
</script>

<style scoped>
.mw350 { max-width: 350px; }
.mouseoverpointer { cursor: pointer; }

/* Widget cards with fixed height */
.widget-card {
  height: 100%;
}
.widget-card :deep(.card-body) {
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.widget-card :deep(.card-header) {
  padding: 0.25rem 0.5rem;
}
.widget-body {
  height: 150px;
  overflow-y: auto;
  overflow-x: hidden;
}
</style>
