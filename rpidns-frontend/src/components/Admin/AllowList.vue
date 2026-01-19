<template>
  <div>
    <!-- Toolbar Row -->
    <BRow class="d-none d-sm-flex">
      <BCol cols="3" lg="3">
        <BButton v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="openAddModal">
          <i class="fa fa-plus"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!wl_selected" @click.stop="openEditModal">
          <i class="fa fa-edit"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" :disabled="!wl_selected" @click.stop="confirmDelete">
          <i class="fa fa-trash-alt"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTable">
          <i class="fa fa-sync"></i>
        </BButton>
      </BCol>
      <BCol cols="3" lg="3"></BCol>
      <BCol cols="6" lg="6">
        <BFormGroup label-cols-md="4" label-size="sm">
          <BInputGroup>
            <template #prepend>
              <BInputGroupText size="sm"><i class="fas fa-filter fa-fw"></i></BInputGroupText>
            </template>
            <BFormInput v-model="wl_Filter" placeholder="Type to search" size="sm" debounce="300"></BFormInput>
            <template #append>
              <BButton size="sm" :disabled="!wl_Filter" @click="wl_Filter = ''">Clear</BButton>
            </template>
          </BInputGroup>
        </BFormGroup>
      </BCol>
    </BRow>

    <!-- Allow List Table -->
    <BRow>
      <BCol cols="12" lg="12">
        <BTableSimple id="whitelist" :sticky-header="`${logs_height}px`" striped hover small responsive>
          <BThead>
            <BTr>
              <BTh class="width050 d-none d-md-table-cell"></BTh>
              <BTh>Domain/IP</BTh>
              <BTh class="d-none d-md-table-cell">Added</BTh>
              <BTh class="d-none d-md-table-cell">Active</BTh>
              <BTh class="d-none d-md-table-cell">*.</BTh>
              <BTh class="d-none d-lg-table-cell">Comment</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="item in filteredItems" :key="item.rowid">
              <BTd class="width050 d-none d-md-table-cell">
                <BFormCheckbox :value="item" v-model="wl_selected" />
              </BTd>
              <BTd class="mw150">{{ item.ioc }}</BTd>
              <BTd class="width250 d-none d-md-table-cell">{{ formatDate(item.dtz) }}</BTd>
              <BTd class="width050 d-none d-md-table-cell" @click="toggleIOC(item.rowid, 'active')">
                <i v-if="item.active == '1'" class="fas fa-toggle-on fa-lg"></i>
                <i v-else class="fas fa-toggle-off fa-lg"></i>
              </BTd>
              <BTd class="width050 d-none d-md-table-cell" @click="toggleIOC(item.rowid, 'subdomains')">
                <i v-if="item.subdomains == '1'" class="fas fa-toggle-on fa-lg"></i>
                <i v-else class="fas fa-toggle-off fa-lg"></i>
              </BTd>
              <BTd class="mw150 d-none d-lg-table-cell">{{ item.comment }}</BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
        <div v-if="isLoading" class="text-center m-0 p-0">
          <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
        </div>
      </BCol>
    </BRow>
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'AllowList',
  props: { logs_height: { type: Number, default: 150 } },
  emits: ['navigate', 'add-ioc', 'delete-ioc', 'show-info'],
  setup(props, { emit, expose }) {
    const api = useApi()
    const wl_Filter = ref('')
    const wl_selected = ref(null)
    const tableItems = ref([])
    const isLoading = ref(false)

    const filteredItems = computed(() => {
      if (!wl_Filter.value) return tableItems.value
      const filter = wl_Filter.value.toLowerCase()
      return tableItems.value.filter(item => 
        (item.ioc && item.ioc.toLowerCase().includes(filter)) ||
        (item.comment && item.comment.toLowerCase().includes(filter))
      )
    })

    const fetchData = async () => {
      isLoading.value = true
      try {
        const response = await api.get({ req: 'whitelist', sortBy: 'ioc', sortDesc: false })
        tableItems.value = response.data || []
      } catch (error) {
        console.error('Error fetching allow list:', error)
        tableItems.value = []
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => { fetchData() }
    const formatDate = (value) => { const date = new Date(value); return date.toLocaleString() }

    const openAddModal = () => {
      emit('add-ioc', { mode: 'add', ioc: '', type: 'wl', comment: '', active: true, subdomains: true, rowid: 0 })
    }

    const openEditModal = () => {
      if (wl_selected.value) {
        emit('add-ioc', {
          mode: 'edit', ioc: wl_selected.value.ioc, type: 'wl',
          comment: wl_selected.value.comment, active: wl_selected.value.active === 1,
          subdomains: wl_selected.value.subdomains === 1, rowid: wl_selected.value.rowid
        })
      }
    }

    const confirmDelete = () => {
      if (wl_selected.value) { emit('delete-ioc', { ioc: wl_selected.value, table: 'whitelist' }) }
    }

    const toggleIOC = async (id, field) => {
      const ioc = tableItems.value.find(item => item.rowid === id)
      if (!ioc) return
      const data = {
        id: ioc.rowid, ioc: ioc.ioc, ltype: 'whitelist',
        active: field === 'active' ? !ioc.active : (ioc.active ? true : false),
        subdomains: field === 'subdomains' ? !ioc.subdomains : (ioc.subdomains ? true : false),
        comment: ioc.comment
      }
      try {
        const response = await api.put({ req: 'whitelist' }, data)
        if (response.status === 'success') { ioc[field] = ioc[field] ? 0 : 1 }
        else { emit('show-info', { msg: response.reason, time: 3 }) }
      } catch (error) { emit('show-info', { msg: 'Unknown error!!!', time: 3 }) }
    }

    const handleRefreshEvent = async (event) => {
      if (event.detail && event.detail.table === 'whitelist') { 
        const selectedRowId = wl_selected.value?.rowid
        await fetchData()
        if (selectedRowId) {
          wl_selected.value = tableItems.value.find(item => item.rowid === selectedRowId) || null
        }
      }
    }

    onMounted(() => {
      fetchData()
      window.addEventListener('refresh-table', handleRefreshEvent)
    })

    onBeforeUnmount(() => {
      window.removeEventListener('refresh-table', handleRefreshEvent)
    })

    expose({ refreshTable })

    return {
      wl_Filter, wl_selected, filteredItems, isLoading,
      refreshTable, formatDate, openAddModal, openEditModal, confirmDelete, toggleIOC
    }
  }
}
</script>

<style scoped>
.width050 { width: 50px; }
.width250 { width: 250px; }
.mw150 { max-width: 150px; }
</style>
