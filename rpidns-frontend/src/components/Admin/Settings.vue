<template>
  <div>
    <BRow>
      <!-- Data Statistics and Retention Column -->
      <BCol cols="12" lg="7">
        <h4>Data statistics and retention</h4>
        <BTableSimple id="tbl_retention" striped hover small responsive>
          <BThead>
            <BTr>
              <BTh>Table</BTh>
              <BTh class="d-none d-md-table-cell">Size</BTh>
              <BTh class="d-none d-md-table-cell">Rows</BTh>
              <BTh class="d-none d-md-table-cell">From</BTh>
              <BTh class="d-none d-md-table-cell">To</BTh>
              <BTh>Retention</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="item in retention" :key="item[0]">
              <BTd>{{ item[0] }}</BTd>
              <BTd class="width100 d-none d-md-table-cell">{{ formatSize(item[1]) }}</BTd>
              <BTd class="width100 d-none d-md-table-cell">{{ item[2] }}</BTd>
              <BTd class="d-none d-md-table-cell">{{ formatDate(item[3]) }}</BTd>
              <BTd class="d-none d-md-table-cell">{{ formatDate(item[4]) }}</BTd>
              <BTd class="width050">
                <BFormInput 
                  v-model="retentionValues[item[0]]" 
                  min="1" 
                  max="1825" 
                  type="number" 
                  size="sm" 
                  v-b-tooltip.hover 
                  title="days"
                ></BFormInput>
              </BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
        <div v-if="db_stats_busy" class="text-center m-0 p-0">
          <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
        </div>
      </BCol>

      <!-- Miscellaneous Settings Column -->
      <BCol cols="12" lg="5">
        <h4>Miscellaneous</h4>
        <hr class="mt-0">
        <BFormCheckbox v-model="assets_autocreate" switch>
          Automatically create assets
        </BFormCheckbox>
        <div class="v-spacer"></div>
        <BForm inline class="mw350">
          <label for="assets_by">Track assets by&nbsp;&nbsp;&nbsp;</label>
          <BFormSelect id="assets_by" v-model="assets_by" size="sm">
            <BFormSelectOption value="mac">MAC Address</BFormSelectOption>
            <BFormSelectOption value="ip">IP Address</BFormSelectOption>
          </BFormSelect>
          <br><br>
          <label for="dashboard_topx">Dashboard show Top &nbsp;&nbsp;&nbsp;</label>
          <BFormInput id="dashboard_topx" min="1" max="200" type="number" size="sm" v-model="dashboard_topx"></BFormInput>
        </BForm>
      </BCol>
    </BRow>

    <!-- Save Button Row -->
    <BRow>
      <BCol cols="12">
        <BButton size="sm" @click="setSettings">Save</BButton>
      </BCol>
    </BRow>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'Settings',
  emits: ['show-info'],
  setup(props, { emit }) {
    const api = useApi()
    const retention = ref([])
    const retentionValues = reactive({})
    const assets_by = ref('mac')
    const assets_autocreate = ref(true)
    const dashboard_topx = ref(100)
    const db_stats_busy = ref(false)

    const formatSize = (value) => {
      if (value < 1024) return value + ' b'
      if (value < 1024 * 1024) return Math.round(value / 1024 * 100) / 100 + ' Kb'
      if (value < 1024 * 1024 * 1024) return Math.round(value / 1024 / 1024 * 100) / 100 + ' Mb'
      return Math.round(value / 1024 / 1024 / 1024 * 100) / 100 + ' Gb'
    }

    const formatDate = (value) => {
      if (!value) return ''
      const date = new Date(value)
      return date.toLocaleString()
    }

    const getSettings = async () => {
      db_stats_busy.value = true
      try {
        const data = await api.get({ req: 'RPIsettings' })
        db_stats_busy.value = false
        retention.value = data.retention || []
        assets_autocreate.value = data.assets_autocreate === '1'
        assets_by.value = data.assets_by || 'mac'
        dashboard_topx.value = parseInt(data.dashboard_topx) || 100
        // Initialize retention values
        retention.value.forEach(item => {
          retentionValues[item[0]] = item[5]
        })
      } catch (error) {
        db_stats_busy.value = false
        emit('show-info', { msg: 'Failed to load settings', time: 3 })
      }
    }

    const setSettings = async () => {
      const data = {
        dash_topx: dashboard_topx.value,
        assets_by: assets_by.value,
        assets_autocreate: assets_autocreate.value,
        queries_raw: retentionValues['queries_raw'] || 30,
        queries_5m: retentionValues['queries_5m'] || 30,
        queries_1h: retentionValues['queries_1h'] || 30,
        queries_1d: retentionValues['queries_1d'] || 30,
        hits_raw: retentionValues['hits_raw'] || 30,
        hits_5m: retentionValues['hits_5m'] || 30,
        hits_1h: retentionValues['hits_1h'] || 30,
        hits_1d: retentionValues['hits_1d'] || 30
      }

      try {
        const result = await api.put({ req: 'RPIsettings' }, data)
        if (result.status !== 'success') {
          emit('show-info', { msg: result.reason, time: 3 })
        } else {
          emit('show-info', { msg: 'Settings were saved', time: 3 })
        }
      } catch (error) {
        emit('show-info', { msg: 'Unknown error!!!', time: 3 })
      }
    }

    onMounted(() => { getSettings() })

    return {
      retention, retentionValues, assets_by, assets_autocreate, dashboard_topx, db_stats_busy,
      formatSize, formatDate, setSettings
    }
  }
}
</script>

<style scoped>
.mw350 { max-width: 350px; }
.width050 { width: 50px; }
.width100 { width: 100px; }
</style>
