<template>
  <div>
    <b-row>
      <!-- Data Statistics and Retention Column -->
      <b-col cols="12" lg="7">
        <h4>Data statistics and retention</h4>
        <b-table 
          id="tbl_retention" 
          :busy="db_stats_busy" 
          no-border-collapse 
          responsive 
          striped 
          hover 
          small 
          :items="retention" 
          :fields="retention_fields"
        >
          <template v-slot:table-busy>
            <div class="text-center text-second m-0 p-0">
              <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;
              <strong>Loading...</strong>
            </div>
          </template>
          <template v-slot:cell(5)="row">
            <b-form-input 
              :ref="'ret_' + row.item[0]" 
              min="1" 
              max="1825" 
              type="number" 
              size="sm" 
              :value="row.item[5]" 
              v-b-tooltip.hover 
              title="days"
            ></b-form-input>
          </template>
        </b-table>
      </b-col>

      <!-- Miscellaneous Settings Column -->
      <b-col cols="12" lg="5">
        <h4>Miscellaneous</h4>
        <hr class="mt-0">
        <b-form-checkbox v-model="assets_autocreate" switch>
          Automatically create assets
        </b-form-checkbox>
        <div class="v-spacer"></div>
        <b-form inline class="mw350">
          <label for="assets_by">Track assets by&nbsp;&nbsp;&nbsp;</label>
          <b-form-select id="assets_by" v-model="assets_by" size="sm">
            <b-form-select-option value="mac">MAC Address</b-form-select-option>
            <b-form-select-option value="ip">IP Address</b-form-select-option>
          </b-form-select>
          <br><br>
          <label for="dashboard_topx">Dashboard show Top &nbsp;&nbsp;&nbsp;</label>
          <b-form-input 
            id="dashboard_topx" 
            min="1" 
            max="200" 
            type="number" 
            size="sm" 
            v-model="dashboard_topx"
          ></b-form-input>
        </b-form>
      </b-col>
    </b-row>

    <!-- Save Button Row -->
    <b-row>
      <b-col cols="12">
        <b-button size="sm" @click="setSettings">Save</b-button>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'Settings',
  data() {
    return {
      // Settings data
      retention: [],
      assets_by: 'mac',
      assets_autocreate: true,
      dashboard_topx: 100,
      db_stats_busy: false,

      // Table field definitions
      retention_fields: [
        { key: '0', label: 'Table' },
        { 
          key: '1', 
          label: 'Size', 
          tdClass: 'width100 d-none d-md-table-cell', 
          thClass: 'width100 d-none d-md-table-cell',
          formatter: (value) => {
            if (value < 1024) return value + ' b'
            if (value < 1024 * 1024) return Math.round(value / 1024 * 100) / 100 + ' Kb'
            if (value < 1024 * 1024 * 1024) return Math.round(value / 1024 / 1024 * 100) / 100 + ' Mb'
            return Math.round(value / 1024 / 1024 / 1024 * 100) / 100 + ' Gb'
          }
        },
        { 
          key: '2', 
          label: 'Rows', 
          tdClass: 'width100 d-none d-md-table-cell', 
          thClass: 'width100 d-none d-md-table-cell'
        },
        { 
          key: '3', 
          label: 'From', 
          tdClass: 'd-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell',
          formatter: (value) => {
            const date = new Date(value)
            return date.toLocaleString()
          }
        },
        { 
          key: '4', 
          label: 'To', 
          tdClass: 'd-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell',
          formatter: (value) => {
            const date = new Date(value)
            return date.toLocaleString()
          }
        },
        { 
          key: '5', 
          label: 'Retention', 
          tdClass: 'width050', 
          thClass: 'width050'
        }
      ]
    }
  },
  mounted() {
    this.getSettings()
  },
  methods: {
    async getSettings() {
      const api = useApi()
      this.db_stats_busy = true
      try {
        const data = await api.get({ req: 'RPIsettings' })
        this.db_stats_busy = false
        this.retention = data.retention
        this.assets_autocreate = data.assets_autocreate === '1'
        this.assets_by = data.assets_by
        this.dashboard_topx = parseInt(data.dashboard_topx)
      } catch (error) {
        this.db_stats_busy = false
        this.$emit('show-info', { msg: 'Failed to load settings', time: 3 })
      }
    },
    async setSettings() {
      const api = useApi()
      
      // Build settings data including retention values from refs
      const data = {
        dash_topx: this.dashboard_topx,
        assets_by: this.assets_by,
        assets_autocreate: this.assets_autocreate,
        queries_raw: this.$refs.ret_queries_raw?.localValue || this.getRetentionValue('queries_raw'),
        queries_5m: this.$refs.ret_queries_5m?.localValue || this.getRetentionValue('queries_5m'),
        queries_1h: this.$refs.ret_queries_1h?.localValue || this.getRetentionValue('queries_1h'),
        queries_1d: this.$refs.ret_queries_1d?.localValue || this.getRetentionValue('queries_1d'),
        hits_raw: this.$refs.ret_hits_raw?.localValue || this.getRetentionValue('hits_raw'),
        hits_5m: this.$refs.ret_hits_5m?.localValue || this.getRetentionValue('hits_5m'),
        hits_1h: this.$refs.ret_hits_1h?.localValue || this.getRetentionValue('hits_1h'),
        hits_1d: this.$refs.ret_hits_1d?.localValue || this.getRetentionValue('hits_1d')
      }

      try {
        const result = await api.put({ req: 'RPIsettings' }, data)
        if (result.status !== 'success') {
          this.$emit('show-info', { msg: result.reason, time: 3 })
        } else {
          this.$emit('show-info', { msg: 'Settings were saved', time: 3 })
        }
      } catch (error) {
        this.$emit('show-info', { msg: 'Unknown error!!!', time: 3 })
      }
    },
    getRetentionValue(tableName) {
      // Find retention value from the retention array
      const row = this.retention.find(r => r[0] === tableName)
      return row ? row[5] : 30
    }
  }
}
</script>

<style scoped>
.mw350 {
  max-width: 350px;
}

.width050 {
  width: 50px;
}

.width100 {
  width: 100px;
}
</style>
