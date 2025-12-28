<template>
  <div>
    <div class="v-spacer"></div>
    <b-card>
      <!-- Header with Refresh and Period Selection -->
      <template slot="header">
        <b-row>
          <b-col cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fas fa-shoe-prints"></i>&nbsp;&nbsp;Query logs</span>
          </b-col>
          <b-col cols="12" lg="10" class="text-right">
            <b-form-group class="m-0">
              <b-button 
                v-b-tooltip.hover 
                title="Refresh" 
                variant="outline-secondary" 
                size="sm" 
                @click.stop="refreshTable"
              >
                <i class="fa fa-sync"></i>
              </b-button>
              <b-form-radio-group 
                v-model="localPeriod" 
                :options="qperiod_options" 
                buttons 
                size="sm" 
                @change="onPeriodChange"
              ></b-form-radio-group>
            </b-form-group>
          </b-col>
        </b-row>
      </template>

      <!-- Controls Row: Logs/Stats Toggle, Pagination, Filter -->
      <b-row class="d-none d-sm-flex">
        <b-col cols="1" lg="1">
          <b-form-radio-group 
            buttons 
            size="sm" 
            v-model="query_ltype" 
            @change="switchStats"
          >
            <b-form-radio value="logs">Logs</b-form-radio>
            <b-form-radio value="stats">Stats</b-form-radio>
          </b-form-radio-group>
        </b-col>
        <b-col cols="3" lg="3"></b-col>
        <b-col cols="3" lg="3">
          <b-pagination 
            v-model="qlogs_cp" 
            :total-rows="qlogs_nrows" 
            :per-page="qlogs_pp" 
            aria-controls="qlogs" 
            size="sm" 
            pills 
            align="center" 
            first-number 
            last-number
          ></b-pagination>
        </b-col>
        <b-col cols="5" lg="5">
          <b-form-group label-cols-md="4" label-size="sm">
            <b-input-group>
              <b-input-group-text slot="prepend" size="sm">
                <i class="fas fa-filter fa-fw" size="sm"></i>
              </b-input-group-text>
              <b-form-input 
                v-model="localFilter" 
                placeholder="Type to search" 
                size="sm" 
                debounce="300"
              ></b-form-input>
              <b-button 
                size="sm" 
                slot="append" 
                :disabled="!localFilter" 
                @click="localFilter = ''"
              >Clear</b-button>
            </b-input-group>
          </b-form-group>
        </b-col>
      </b-row>

      <!-- Query Log Table -->
      <b-row>
        <b-col sm="12">
          <template>
            <div ref="refLogsDiv">
              <b-table 
                id="qlogs" 
                ref="refLogs" 
                :sticky-header="`${logs_height}px`" 
                :sort-icon-left="true" 
                :per-page="qlogs_pp" 
                :current-page="qlogs_cp" 
                no-border-collapse 
                striped 
                hover 
                :items="getTables" 
                :api-url="apiUrl" 
                :fields="qlogs_fields" 
                small 
                responsive 
                :filter="localFilter" 
                sort-by="dtz" 
                :sort-desc="true"
              >
                <!-- Loading Spinner -->
                <template v-slot:table-busy>
                  <div class="text-center text-second m-0 p-0">
                    <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong>
                  </div>
                </template>

                <!-- Column Headers with Checkboxes for Stats Mode -->
                <template v-slot:head(cname)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(server)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(fqdn)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(type)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(class)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(options)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(action)="row">
                  <div v-if="query_ltype === 'stats'">
                    <b-form-checkbox 
                      name="qlog_head" 
                      :value="row.column" 
                      v-model="qlogs_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <!-- Cell Templates with Popovers -->
                <!-- Client Name Cell -->
                <template v-slot:cell(cname)="row">
                  <b-popover 
                    title="Info" 
                    :target="'tip-qlogs_cname' + row.item.rowid" 
                    triggers="hover"
                  >
                    <strong>Mac:</strong> {{ row.item.mac }}<br>
                    <strong>IP:</strong> {{ row.item.client_ip }}<br>
                    <strong>Vendor:</strong> {{ row.item.vendor }}<br>
                    <span v-if="row.item.comment !== ''">
                      <strong>Comment:</strong> {{ row.item.comment }}
                    </span>
                  </b-popover>
                  <span :id="'tip-qlogs_cname' + row.item.rowid">{{ row.item.cname }}</span>
                </template>

                <!-- Action Cell -->
                <template v-slot:cell(action)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-action' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('action', row.item.action)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-qlogs-action' + row.item.rowid">
                    <div v-if="row.item.action === 'blocked'">
                      <i class="fas fa-hand-paper salmon"></i> Block
                    </div>
                    <div v-if="row.item.action === 'allowed'">
                      <i class="fas fa-check green"></i> Allow
                    </div>
                  </span>
                </template>

                <!-- FQDN Cell with Actions -->
                <template v-slot:cell(fqdn)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-fqdn' + row.item.rowid" 
                    triggers="hover"
                  >
                    <div v-if="row.item.action === 'blocked'">
                      <a href="javascript:{}" @click.stop="allowDomain(row.item.fqdn)">Allow</a>
                    </div>
                    <div v-else>
                      <a href="javascript:{}" @click.stop="blockDomain(row.item.fqdn)">Block</a>
                    </div>
                    <a href="javascript:{}" @click.stop="filterBy('fqdn', row.item.fqdn)">Filter by</a>
                    <hr class="m-1">
                    <strong>Research:</strong><br>
                    <research-links :domain="row.item.fqdn" />
                  </b-popover>
                  <span :id="'tip-qlogs-fqdn' + row.item.rowid">{{ row.item.fqdn }}</span>
                </template>

                <!-- Server Cell -->
                <template v-slot:cell(server)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-server' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('server', row.item.server)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-qlogs-server' + row.item.rowid">{{ row.item.server }}</span>
                </template>

                <!-- Class Cell -->
                <template v-slot:cell(class)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-class' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('class', row.item.class)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-qlogs-class' + row.item.rowid">{{ row.item.class }}</span>
                </template>

                <!-- Type Cell -->
                <template v-slot:cell(type)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-type' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('type', row.item.type)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-qlogs-type' + row.item.rowid">{{ row.item.type }}</span>
                </template>

                <!-- Options Cell -->
                <template v-slot:cell(options)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-qlogs-options' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('options', row.item.options)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-qlogs-options' + row.item.rowid">{{ row.item.options }}</span>
                </template>
              </b-table>
            </div>
          </template>
        </b-col>
      </b-row>
    </b-card>
  </div>
</template>


<script>
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'

export default {
  name: 'QueryLog',
  components: {
    ResearchLinks
  },
  props: {
    // Filter passed from parent (e.g., from Dashboard navigation)
    filter: {
      type: String,
      default: ''
    },
    // Period passed from parent
    period: {
      type: String,
      default: '30m'
    },
    // Logs height for sticky header
    logs_height: {
      type: Number,
      default: 150
    }
  },
  data() {
    return {
      // Local state
      localFilter: this.filter,
      localPeriod: this.period,
      query_ltype: 'logs',
      
      // Pagination
      qlogs_cp: 1,
      qlogs_nrows: 0,
      qlogs_pp: 100,
      
      // Selected fields for stats mode
      qlogs_select_fields: ['cname', 'server', 'fqdn', 'type', 'class', 'options', 'action'],
      
      // Current fields (switches between logs and stats)
      qlogs_fields: [],
      
      // Field definitions for logs mode
      qlogs_fields_logs: [
        { 
          key: 'dtz', 
          label: 'Local Time', 
          sortable: true, 
          formatter: (value) => { 
            const date = new Date(value)
            return date.toLocaleString()
          }
        },
        { key: 'cname', label: 'Client', sortable: true, tdClass: 'mw200 d-none d-sm-table-cell', thClass: 'd-none d-sm-table-cell' },
        { key: 'server', label: 'Server', sortable: true, tdClass: 'mw200 d-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'fqdn', label: 'Request', sortable: true, tdClass: 'mw250' },
        { key: 'type', label: 'Type', sortable: true },
        { key: 'class', label: 'Class', sortable: true, tdClass: 'd-none d-xl-table-cell', thClass: 'd-none d-xl-table-cell' },
        { key: 'options', label: 'Options', sortable: true, tdClass: 'd-none d-xl-table-cell', thClass: 'd-none d-xl-table-cell' },
        { key: 'cnt', label: 'Count', sortable: true },
        { key: 'action', label: 'Action', sortable: true }
      ],
      
      // Field definitions for stats mode (no timestamp)
      qlogs_fields_stats: [
        { key: 'cname', label: 'Client', sortable: true, tdClass: 'mw200 d-none d-sm-table-cell', thClass: 'd-none d-sm-table-cell' },
        { key: 'server', label: 'Server', sortable: true, tdClass: 'mw200 d-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'fqdn', label: 'Request', sortable: true, tdClass: 'mw250' },
        { key: 'type', label: 'Type', sortable: true },
        { key: 'class', label: 'Class', sortable: true, tdClass: 'd-none d-xl-table-cell', thClass: 'd-none d-xl-table-cell' },
        { key: 'options', label: 'Options', sortable: true, tdClass: 'd-none d-xl-table-cell', thClass: 'd-none d-xl-table-cell' },
        { key: 'cnt', label: 'Count', sortable: true },
        { key: 'action', label: 'Action', sortable: true }
      ],
      
      // Period options
      qperiod_options: [
        { text: '30m', value: '30m' },
        { text: '1h', value: '1h' },
        { text: '1d', value: '1d' },
        { text: '1w', value: '1w' },
        { text: '30d', value: '30d' },
        { text: 'custom', value: 'custom', disabled: true }
      ],
      
      // Track last update time for rate limiting
      logs_updatetime: 0
    }
  },
  computed: {
    // Build API URL dynamically
    apiUrl() {
      return '/rpi_admin/rpidata.php?req=queries_raw' +
        '&period=' + this.localPeriod +
        '&cp=' + this.qlogs_cp +
        '&filter=' + this.localFilter +
        '&pp=' + this.qlogs_pp +
        '&ltype=' + this.query_ltype +
        '&fields=' + this.qlogs_select_fields.join(',')
    }
  },
  watch: {
    // Watch for external filter changes
    filter(newVal) {
      this.localFilter = newVal
    },
    // Watch for external period changes
    period(newVal) {
      this.localPeriod = newVal
    }
  },
  mounted() {
    // Initialize fields to logs mode
    this.qlogs_fields = this.qlogs_fields_logs
  },
  methods: {
    // Fetch table data from API
    getTables(ctx) {
      const URL = ctx.apiUrl
      const promise = axios.get(ctx.apiUrl + '&sortBy=' + ctx.sortBy + '&sortDesc=' + ctx.sortDesc)
      return promise.then((data) => {
        const items = data.data.data
        // Check for HTML response (session expired)
        if (/DOCTYPE html/.test(items)) {
          window.location.reload(false)
        }
        // Update row count for pagination
        this.qlogs_nrows = parseInt(data.data.records) || 0
        return items
      }).catch(() => {
        this.qlogs_nrows = 0
        return []
      })
    },
    
    // Refresh the table
    refreshTable() {
      // Rate limit: only refresh once per minute
      if ((Date.now() - this.logs_updatetime) > 60 * 1000) {
        this.$root.$emit('bv::refresh::table', 'qlogs')
        this.logs_updatetime = Date.now()
      }
    },
    
    // Handle period change
    onPeriodChange() {
      this.qlogs_cp = 1
    },
    
    // Switch between logs and stats mode
    switchStats() {
      // Clear current items
      if (this.$refs.refLogs) {
        this.$refs.refLogs.$data.localItems = []
      }
      // Toggle field definitions
      this.qlogs_fields = this.query_ltype !== 'logs' 
        ? this.qlogs_fields_logs 
        : this.qlogs_fields_stats
    },
    
    // Filter by a specific field value
    filterBy(field, value) {
      this.localFilter = field + '=' + value
    },
    
    // Block a domain - emit event to parent
    blockDomain(domain) {
      this.$emit('add-ioc', { ioc: domain, type: 'bl' })
    },
    
    // Allow a domain - emit event to parent
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

.salmon {
  color: salmon;
}

.green {
  color: green;
}
</style>
