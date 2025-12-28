<template>
  <div fluid v-cloak>
    <div class="v-spacer"></div>
    <b-card no-body class="d-flex flex-column" style="max-height:calc(100vh - 100px)">
      <!-- Header with Refresh and Period Selection -->
      <template slot="header">
        <b-row>
          <b-col cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fas fa-tachometer-alt"></i>&nbsp;&nbsp;Dashboard</span>
          </b-col>
          <b-col cols="12" lg="10" class="text-right">
            <b-form-group class="m-0">
              <b-button 
                v-b-tooltip.hover 
                title="Refresh" 
                variant="outline-secondary" 
                size="sm" 
                @click.stop="refreshDash"
              >
                <i class="fa fa-sync"></i>
              </b-button>&nbsp;&nbsp;&nbsp;
              <b-form-radio-group 
                v-model="dash_period" 
                :options="period_options" 
                buttons 
                size="sm" 
                @input="onPeriodChange"
              ></b-form-radio-group>
            </b-form-group>
          </b-col>
        </b-row>
      </template>

      <div class="v-spacer"></div>

      <!-- First Row: Allowed Stats -->
      <div>
        <b-card-group deck>
          <!-- TopX Allowed Requests -->
          <b-card header="TopX Allowed Requests" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_req" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onAllowedRequestClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
                <template v-slot:cell(fname)="row">
                  <b-popover title="Actions" :target="'tip-good_requests' + row.item.fname" triggers="hover">
                    <a href="javascript:{}" @click.stop="showQueries('fqdn=' + row.item.fname)">Show queries</a><br>
                    <a href="javascript:{}" @click.stop="blockDomain(row.item.fname)">Block</a>
                    <hr class="m-1">
                    <strong>Research:</strong><br>
                    <research-links :domain="row.item.fname" />
                  </b-popover>
                  <span :id="'tip-good_requests' + row.item.fname">{{ row.item.fname }}</span>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- TopX Allowed Clients -->
          <b-card header="TopX Allowed Clients" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_client" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_client&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onAllowedClientClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
                <template v-slot:cell(fname)="row">
                  <b-popover title="Actions" :target="'tip-good_clients' + row.item.fname" triggers="hover">
                    <a href="javascript:{}" @click.stop="showQueriesForClient(row.item)">Show queries</a><br>
                    <a href="javascript:{}" @click.stop="showHitsForClient(row.item)">Show hits</a>
                  </b-popover>
                  <span :id="'tip-good_clients' + row.item.fname">{{ row.item.fname }}</span>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- TopX Allowed Request Types -->
          <b-card header="TopX Allowed Request Types" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_req_type" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_req_type&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onRequestTypeClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- RpiDNS Stats -->
          <b-card header="RpiDNS" body-class="p-2">
            <div>
              <b-table 
                id="dash_server_stats" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=server_stats'" 
                :fields="dash_stats_fields" 
                thead-class="hidden"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
                <template v-slot:cell(cnt)="row">
                  <div v-if="row.item.fname === 'CPU load'">
                    <b-popover :target="'tip-RpiDNS' + row.item.fname" triggers="hover" placement="topright">
                      Load in 1 minute, 5 minutes, 15 minutes
                    </b-popover>
                    <span :id="'tip-RpiDNS' + row.item.fname">{{ row.item.cnt }}</span>
                  </div>
                  <div v-else>{{ row.item.cnt }}</div>
                </template>
              </b-table>
            </div>
          </b-card>
        </b-card-group>
      </div>

      <div class="v-spacer"></div>

      <!-- Second Row: Blocked Stats -->
      <div>
        <b-card-group deck>
          <!-- TopX Blocked Requests -->
          <b-card header="TopX Blocked Requests" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_breq" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_breq&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onBlockedRequestClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
                <template v-slot:cell(fname)="row">
                  <b-popover title="Actions" :target="'tip-bad_requests' + row.item.fname" triggers="hover">
                    Show <a href="javascript:{}" @click.stop="showQueries('fqdn=' + row.item.fname)">queries</a>&nbsp;|&nbsp;
                    <a href="javascript:{}" @click.stop="showHits('fqdn=' + row.item.fname)">hits</a><br>
                    <a href="javascript:{}" @click.stop="allowDomain(row.item.fname)">Allow</a>
                    <hr class="m-1">
                    <strong>Research:</strong><br>
                    <research-links :domain="row.item.fname" />
                  </b-popover>
                  <span :id="'tip-bad_requests' + row.item.fname">{{ row.item.fname }}</span>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- TopX Blocked Clients -->
          <b-card header="TopX Blocked Clients" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_bclient" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_bclient&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onBlockedClientClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
                <template v-slot:cell(fname)="row">
                  <b-popover title="Actions" :target="'tip-bad_clients' + row.item.fname" triggers="hover">
                    Show <a href="javascript:{}" @click.stop="showQueriesForClient(row.item)">queries</a>&nbsp;|&nbsp;
                    <a href="javascript:{}" @click.stop="showHitsForClient(row.item)">hits</a>
                  </b-popover>
                  <span :id="'tip-bad_clients' + row.item.fname">{{ row.item.fname }}</span>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- TopX Feeds -->
          <b-card header="TopX Feeds" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_feeds" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_feeds&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onFeedClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
              </b-table>
            </div>
          </b-card>

          <!-- TopX Servers -->
          <b-card header="TopX Servers" body-class="p-2">
            <div>
              <b-table 
                id="dash_topX_server" 
                sticky-header="150px" 
                no-border-collapse 
                striped 
                hover 
                small 
                :items="getTables" 
                :api-url="'/rpi_admin/rpidata.php?req=dash_topX_server&period=' + dash_period" 
                :fields="dash_stats_fields" 
                thead-class="hidden" 
                @row-clicked="onServerClick"
              >
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>
              </b-table>
            </div>
          </b-card>
        </b-card-group>
      </div>

      <div class="v-spacer"></div>

      <!-- QPS Chart Row -->
      <div>
        <b-card-group deck>
          <b-card header="Queries per Minute">
            <apexchart 
              type="area" 
              height="200" 
              width="99%" 
              :options="qps_options" 
              :series="qps_series"
            ></apexchart>
          </b-card>
        </b-card-group>
      </div>
    </b-card>
  </div>
</template>

<script>
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'

export default {
  name: 'Dashboard',
  components: {
    ResearchLinks
  },
  data() {
    return {
      dash_period: '30m',
      
      // Table field definitions
      dash_stats_fields: [
        { key: 'fname', label: 'Name', tdClass: 'mw350 mouseoverpointer' },
        { key: 'cnt', label: 'Count' }
      ],
      
      // Period options for radio group
      period_options: [
        { text: '30m', value: '30m' },
        { text: '1h', value: '1h' },
        { text: '1d', value: '1d' },
        { text: '1w', value: '1w' },
        { text: '30d', value: '30d' },
        { text: 'custom', value: 'custom', disabled: true }
      ],
      
      // QPS Chart data
      qps_series: [],
      qps_options: {
        colors: ['#008FFB', '#FA4443'],
        chart: {
          id: 'qps-stats'
        },
        dataLabels: {
          enabled: false
        },
        xaxis: {
          type: 'datetime',
          labels: { datetimeUTC: false }
        },
        tooltip: {
          x: {
            format: 'dd MMM yyyy H:mm'
          }
        },
        yaxis: {
          min: 0
        },
        fill: {
          type: 'gradient',
          gradient: {
            opacityFrom: 0.6,
            opacityTo: 0.8
          }
        }
      }
    }
  },
  mounted() {
    this.refreshDashQPS()
  },
  methods: {
    // Fetch table data from API
    getTables(obj) {
      const URL = obj.apiUrl
      const promise = axios.get(obj.apiUrl + '&sortBy=' + obj.sortBy + '&sortDesc=' + obj.sortDesc)
      return promise.then((data) => {
        const items = data.data.data
        if (/DOCTYPE html/.test(items)) {
          window.location.reload(false)
        }
        return items
      }).catch(() => {
        return []
      })
    },
    
    // Refresh QPS chart data
    refreshDashQPS() {
      axios.get('/rpi_admin/rpidata.php?req=qps_chart&period=' + this.dash_period)
        .then((data) => {
          this.qps_series = data.data
        })
    },
    
    // Refresh all dashboard data
    refreshDash() {
      this.refreshDashQPS()
      this.$root.$emit('bv::refresh::table', 'dash_topX_req')
      this.$root.$emit('bv::refresh::table', 'dash_topX_req_type')
      this.$root.$emit('bv::refresh::table', 'dash_topX_client')
      this.$root.$emit('bv::refresh::table', 'dash_topX_breq')
      this.$root.$emit('bv::refresh::table', 'dash_topX_bclient')
      this.$root.$emit('bv::refresh::table', 'dash_topX_feeds')
      this.$root.$emit('bv::refresh::table', 'dash_topX_server')
    },
    
    // Handle period change
    onPeriodChange() {
      this.refreshDashQPS()
    },
    
    // Navigation helpers - emit events to parent
    showQueries(filter) {
      this.$emit('navigate', { tab: 1, filter: filter, period: this.dash_period, type: 'qlogs' })
    },
    
    showHits(filter) {
      this.$emit('navigate', { tab: 2, filter: filter, period: this.dash_period, type: 'hits' })
    },
    
    showQueriesForClient(item) {
      const filter = (item.mac == null || item.mac === '') 
        ? 'client_ip=' + item.fname 
        : 'mac=' + item.mac
      this.showQueries(filter)
    },
    
    showHitsForClient(item) {
      const filter = (item.mac == null || item.mac === '') 
        ? 'client_ip=' + item.fname 
        : 'mac=' + item.mac
      this.showHits(filter)
    },
    
    // Row click handlers
    onAllowedRequestClick(item) {
      this.showQueries('fqdn=' + item.fname)
    },
    
    onAllowedClientClick(item) {
      this.showQueriesForClient(item)
    },
    
    onRequestTypeClick(item) {
      this.showQueries('type=' + item.fname)
    },
    
    onBlockedRequestClick(item) {
      this.showHits('fqdn=' + item.fname)
    },
    
    onBlockedClientClick(item) {
      this.showHitsForClient(item)
    },
    
    onFeedClick(item) {
      this.showHits('feed=' + item.fname)
    },
    
    onServerClick(item) {
      this.showQueries('server=' + item.fname)
    },
    
    // Block/Allow actions - emit events to parent for modal handling
    blockDomain(domain) {
      this.$emit('add-ioc', { ioc: domain, type: 'bl' })
    },
    
    allowDomain(domain) {
      this.$emit('add-ioc', { ioc: domain, type: 'wl' })
    }
  }
}
</script>

<style scoped>
.v-spacer {
  height: 10px;
}
</style>
