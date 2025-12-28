<template>
  <div>
    <div class="v-spacer"></div>
    <b-card>
      <!-- Header with Refresh and Period Selection -->
      <template slot="header">
        <b-row>
          <b-col cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fa fa-shield-alt"></i>&nbsp;&nbsp;RPZ hits</span>
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
                :options="period_options" 
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
            v-model="hits_ltype" 
            @change="switchStats"
          >
            <b-form-radio value="logs">Logs</b-form-radio>
            <b-form-radio value="stats">Stats</b-form-radio>
          </b-form-radio-group>
        </b-col>
        <b-col cols="3" lg="3"></b-col>
        <b-col cols="3" lg="3">
          <b-pagination 
            v-model="hits_cp" 
            :total-rows="hits_nrows" 
            :per-page="hits_pp" 
            aria-controls="hits" 
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

      <!-- RPZ Hits Table -->
      <b-row>
        <b-col sm="12">
          <template>
            <div ref="refHitsDiv">
              <b-table 
                id="hits" 
                ref="refHits" 
                :sticky-header="`${logs_height}px`" 
                :sort-icon-left="true" 
                :per-page="hits_pp" 
                :current-page="hits_cp" 
                no-border-collapse 
                striped 
                hover 
                :items="getTables" 
                :api-url="apiUrl" 
                :fields="hits_fields" 
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
                  <div v-if="hits_ltype === 'stats'">
                    <b-form-checkbox 
                      name="hits_head" 
                      :value="row.column" 
                      v-model="hits_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(fqdn)="row">
                  <div v-if="hits_ltype === 'stats'">
                    <b-form-checkbox 
                      name="hits_head" 
                      :value="row.column" 
                      v-model="hits_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(action)="row">
                  <div v-if="hits_ltype === 'stats'">
                    <b-form-checkbox 
                      name="hits_head" 
                      :value="row.column" 
                      v-model="hits_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(rule)="row">
                  <div v-if="hits_ltype === 'stats'">
                    <b-form-checkbox 
                      name="hits_head" 
                      :value="row.column" 
                      v-model="hits_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <template v-slot:head(rule_type)="row">
                  <div v-if="hits_ltype === 'stats'">
                    <b-form-checkbox 
                      name="hits_head" 
                      :value="row.column" 
                      v-model="hits_select_fields"
                    >{{ row.label }}</b-form-checkbox>
                  </div>
                  <div v-else>{{ row.label }}</div>
                </template>

                <!-- Cell Templates with Popovers -->
                <!-- Client Name Cell -->
                <template v-slot:cell(cname)="row">
                  <b-popover 
                    title="Info" 
                    :target="'tip-hits_cname' + row.item.rowid" 
                    triggers="hover"
                  >
                    <strong>Mac:</strong> {{ row.item.mac }}<br>
                    <strong>IP:</strong> {{ row.item.client_ip }}<br>
                    <strong>Vendor:</strong> {{ row.item.vendor }}<br>
                    <span v-if="row.item.comment !== ''">
                      <strong>Comment:</strong> {{ row.item.comment }}
                    </span>
                  </b-popover>
                  <span :id="'tip-hits_cname' + row.item.rowid">{{ row.item.cname }}</span>
                </template>

                <!-- FQDN Cell with Actions -->
                <template v-slot:cell(fqdn)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-hits-fqdn' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="allowDomain(row.item.fqdn)">Allow</a><br>
                    <a href="javascript:{}" @click.stop="filterBy('fqdn', row.item.fqdn)">Filter by</a>
                    <hr class="m-1">
                    <strong>Research:</strong><br>
                    <research-links :domain="row.item.fqdn" />
                  </b-popover>
                  <span :id="'tip-hits-fqdn' + row.item.rowid">{{ row.item.fqdn }}</span>
                </template>

                <!-- Rule Cell with Actions -->
                <template v-slot:cell(rule)="row">
                  <template v-if="typeof row.item.rule !== 'undefined'">
                    <b-popover 
                      title="Actions" 
                      :target="'tip-hits-rule' + row.item.rowid" 
                      triggers="hover"
                    >
                      <a href="javascript:{}" @click.stop="allowRule(row.item)">Allow</a><br>
                      <a href="javascript:{}" @click.stop="filterBy('rule', row.item.rule)">Filter by</a>
                      <hr class="m-1">
                      <strong>Research:</strong><br>
                      <research-links :domain="extractRuleDomain(row.item)" />
                    </b-popover>
                    <span :id="'tip-hits-rule' + row.item.rowid">{{ row.item.rule }}</span>
                  </template>
                </template>

                <!-- Rule Type Cell -->
                <template v-slot:cell(rule_type)="row">
                  <b-popover 
                    title="Actions" 
                    :target="'tip-hits-rule_type' + row.item.rowid" 
                    triggers="hover"
                  >
                    <a href="javascript:{}" @click.stop="filterBy('rule_type', row.item.rule_type)">Filter by</a>
                  </b-popover>
                  <span :id="'tip-hits-rule_type' + row.item.rowid">{{ row.item.rule_type }}</span>
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
  name: 'RpzHits',
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
      hits_ltype: 'logs',
      
      // Pagination
      hits_cp: 1,
      hits_nrows: 0,
      hits_pp: 100,
      
      // Selected fields for stats mode
      hits_select_fields: ['cname', 'fqdn', 'action', 'rule', 'rule_type'],
      
      // Current fields (switches between logs and stats)
      hits_fields: [],
      
      // Field definitions for logs mode
      hits_fields_logs: [
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
        { key: 'fqdn', label: 'Request', sortable: true, tdClass: 'mw200' },
        { key: 'action', label: 'Action', sortable: true, tdClass: 'd-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'rule', label: 'Rule', sortable: true, tdClass: 'mw300 d-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'rule_type', label: 'Type', sortable: true, tdClass: 'd-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'cnt', label: 'Count', sortable: true }
      ],
      
      // Field definitions for stats mode (no timestamp)
      hits_fields_stats: [
        { key: 'cname', label: 'Client', sortable: true, tdClass: 'mw200 d-none d-sm-table-cell', thClass: 'd-none d-sm-table-cell' },
        { key: 'fqdn', label: 'Request', sortable: true, tdClass: 'mw200' },
        { key: 'action', label: 'Action', sortable: true, tdClass: 'd-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'rule', label: 'Rule', sortable: true, tdClass: 'mw300 d-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'rule_type', label: 'Type', sortable: true, tdClass: 'd-none d-lg-table-cell', thClass: 'd-none d-lg-table-cell' },
        { key: 'cnt', label: 'Count', sortable: true }
      ],
      
      // Period options
      period_options: [
        { text: '30m', value: '30m' },
        { text: '1h', value: '1h' },
        { text: '1d', value: '1d' },
        { text: '1w', value: '1w' },
        { text: '30d', value: '30d' },
        { text: 'custom', value: 'custom', disabled: true }
      ],
      
      // Track last update time for rate limiting
      hits_updatetime: 0
    }
  },
  computed: {
    // Build API URL dynamically
    apiUrl() {
      return '/rpi_admin/rpidata.php?req=hits_raw' +
        '&period=' + this.localPeriod +
        '&cp=' + this.hits_cp +
        '&filter=' + this.localFilter +
        '&pp=' + this.hits_pp +
        '&ltype=' + this.hits_ltype +
        '&fields=' + this.hits_select_fields.join(',')
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
    this.hits_fields = this.hits_fields_logs
  },
  methods: {
    // Fetch table data from API
    getTables(ctx) {
      const promise = axios.get(ctx.apiUrl + '&sortBy=' + ctx.sortBy + '&sortDesc=' + ctx.sortDesc)
      return promise.then((data) => {
        const items = data.data.data
        // Check for HTML response (session expired)
        if (/DOCTYPE html/.test(items)) {
          window.location.reload(false)
        }
        // Update row count for pagination
        this.hits_nrows = parseInt(data.data.records) || 0
        return items
      }).catch(() => {
        this.hits_nrows = 0
        return []
      })
    },
    
    // Refresh the table
    refreshTable() {
      // Rate limit: only refresh once per minute
      if ((Date.now() - this.hits_updatetime) > 60 * 1000) {
        this.$root.$emit('bv::refresh::table', 'hits')
        this.hits_updatetime = Date.now()
      }
    },
    
    // Handle period change
    onPeriodChange() {
      this.hits_cp = 1
    },
    
    // Switch between logs and stats mode
    switchStats() {
      // Clear current items
      if (this.$refs.refHits) {
        this.$refs.refHits.$data.localItems = []
      }
      // Toggle field definitions
      this.hits_fields = this.hits_ltype !== 'logs' 
        ? this.hits_fields_logs 
        : this.hits_fields_stats
    },
    
    // Filter by a specific field value
    filterBy(field, value) {
      this.localFilter = field + '=' + value
    },
    
    // Extract domain from rule (removes feed suffix)
    extractRuleDomain(item) {
      if (item.rule && item.feed) {
        const feedSuffix = '.' + item.feed
        const idx = item.rule.indexOf(feedSuffix)
        if (idx > 0) {
          return item.rule.substring(0, idx)
        }
      }
      return item.rule || ''
    },
    
    // Allow a domain - emit event to parent
    allowDomain(domain) {
      this.$emit('add-ioc', { ioc: domain, type: 'wl' })
    },
    
    // Allow a rule domain - emit event to parent
    allowRule(item) {
      const domain = this.extractRuleDomain(item)
      this.$emit('add-ioc', { ioc: domain, type: 'wl' })
    }
  }
}
</script>
