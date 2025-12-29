<template>
  <div class="h-100 overflow-auto p-2">
    <BCard class="h-100 d-flex flex-column">
      <!-- Header with Refresh and Period Selection -->
      <template #header>
        <BRow>
          <BCol cols="0" class="d-none d-lg-block" lg="2">
            <span class="bold"><i class="fa fa-shield-alt"></i>&nbsp;&nbsp;RPZ hits</span>
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
                :options="period_options" 
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
            v-model="hits_ltype" 
            @update:model-value="switchStats"
          >
            <BFormRadio value="logs">Logs</BFormRadio>
            <BFormRadio value="stats">Stats</BFormRadio>
          </BFormRadioGroup>
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
                  <BTd class="mw200 d-none d-sm-table-cell">
                    <span v-b-tooltip.hover :title="`Mac: ${item.mac || ''}\nIP: ${item.client_ip || ''}\nVendor: ${item.vendor || ''}`">
                      {{ item.cname }}
                    </span>
                  </BTd>
                  <BTd class="mw200">{{ item.fqdn }}</BTd>
                  <BTd class="d-none d-lg-table-cell">{{ item.action }}</BTd>
                  <BTd class="mw300 d-none d-lg-table-cell">{{ item.rule }}</BTd>
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
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import ResearchLinks from './ResearchLinks.vue'

export default {
  name: 'RpzHits',
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

    const period_options = [
      { text: '30m', value: '30m' },
      { text: '1h', value: '1h' },
      { text: '1d', value: '1d' },
      { text: '1w', value: '1w' },
      { text: '30d', value: '30d' },
      { text: 'custom', value: 'custom', disabled: true }
    ]

    const apiUrl = computed(() => {
      return '/rpi_admin/rpidata.php?req=hits_raw' +
        '&period=' + localPeriod.value +
        '&cp=' + hits_cp.value +
        '&filter=' + localFilter.value +
        '&pp=' + hits_pp.value +
        '&ltype=' + hits_ltype.value +
        '&fields=' + hits_select_fields.value.join(',') +
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
        hits_nrows.value = parseInt(response.data.records) || 0
      } catch (error) {
        tableItems.value = []
        hits_nrows.value = 0
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => {
      if ((Date.now() - hits_updatetime.value) > 60 * 1000) {
        fetchData()
        hits_updatetime.value = Date.now()
      }
    }

    const onPeriodChange = () => { hits_cp.value = 1; fetchData() }
    const switchStats = () => { tableItems.value = []; fetchData() }
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
    watch(() => props.period, (newVal) => { localPeriod.value = newVal })
    watch(localFilter, () => { hits_cp.value = 1; fetchData() })
    watch(hits_cp, () => { fetchData() })

    onMounted(() => { fetchData() })

    return {
      localFilter, localPeriod, hits_ltype, hits_cp, hits_nrows, hits_pp,
      hits_select_fields, tableItems, isLoading, period_options,
      refreshTable, onPeriodChange, switchStats, filterBy, extractRuleDomain,
      allowDomain, allowRule, formatDate, sortBy: sortByField
    }
  }
}
</script>

<style scoped>
.mw200 { max-width: 200px; }
.mw300 { max-width: 300px; }
</style>
