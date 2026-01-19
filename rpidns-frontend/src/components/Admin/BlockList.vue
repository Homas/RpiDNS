<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div>
    <!-- Toolbar Row -->
    <BRow class="d-none d-sm-flex">
      <BCol cols="3" lg="3">
        <BButton v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="openAddModal">
          <i class="fa fa-plus"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!bl_selected" @click.stop="openEditModal">
          <i class="fa fa-edit"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" :disabled="!bl_selected" @click.stop="confirmDelete">
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
            <BFormInput v-model="bl_Filter" placeholder="Type to search" size="sm"></BFormInput>
            <template #append>
              <BButton size="sm" :disabled="!bl_Filter" @click="bl_Filter = ''">Clear</BButton>
            </template>
          </BInputGroup>
        </BFormGroup>
      </BCol>
    </BRow>

    <!-- Block List Table -->
    <BRow>
      <BCol cols="12" lg="12">
        <BTableSimple id="blacklist" :sticky-header="`${logs_height}px`" striped hover small responsive>
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
                <BFormCheckbox :value="item" v-model="bl_selected" />
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
  name: 'BlockList',
  props: { logs_height: { type: Number, default: 150 } },
  emits: ['navigate', 'add-ioc', 'delete-ioc', 'show-info'],
  setup(props, { emit, expose }) {
    const api = useApi()
    const bl_Filter = ref('')
    const bl_selected = ref(null)
    const tableItems = ref([])
    const isLoading = ref(false)

    const filteredItems = computed(() => {
      if (!bl_Filter.value) return tableItems.value
      const filter = bl_Filter.value.toLowerCase()
      return tableItems.value.filter(item => 
        (item.ioc && item.ioc.toLowerCase().includes(filter)) ||
        (item.comment && item.comment.toLowerCase().includes(filter))
      )
    })

    const fetchData = async () => {
      isLoading.value = true
      try {
        const response = await api.get({ req: 'blacklist', sortBy: 'ioc', sortDesc: false })
        tableItems.value = response.data || []
      } catch (error) {
        console.error('Error fetching block list:', error)
        tableItems.value = []
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => { fetchData() }
    const formatDate = (value) => { const date = new Date(value); return date.toLocaleString() }

    const openAddModal = () => {
      emit('add-ioc', { mode: 'add', ioc: '', type: 'bl', comment: '', active: true, subdomains: true, rowid: 0 })
    }

    const openEditModal = () => {
      if (bl_selected.value) {
        emit('add-ioc', {
          mode: 'edit', ioc: bl_selected.value.ioc, type: 'bl',
          comment: bl_selected.value.comment, active: bl_selected.value.active === 1,
          subdomains: bl_selected.value.subdomains === 1, rowid: bl_selected.value.rowid
        })
      }
    }

    const confirmDelete = () => {
      if (bl_selected.value) { emit('delete-ioc', { ioc: bl_selected.value, table: 'blacklist' }) }
    }

    const toggleIOC = async (id, field) => {
      const ioc = tableItems.value.find(item => item.rowid === id)
      if (!ioc) return
      const data = {
        id: ioc.rowid, ioc: ioc.ioc, ltype: 'blacklist',
        active: field === 'active' ? !ioc.active : (ioc.active ? true : false),
        subdomains: field === 'subdomains' ? !ioc.subdomains : (ioc.subdomains ? true : false),
        comment: ioc.comment
      }
      try {
        const response = await api.put({ req: 'blacklist' }, data)
        if (response.status === 'success') { ioc[field] = ioc[field] ? 0 : 1 }
        else { emit('show-info', { msg: response.reason, time: 3 }) }
      } catch (error) { emit('show-info', { msg: 'Unknown error!!!', time: 3 }) }
    }

    const handleRefreshEvent = async (event) => {
      if (event.detail && event.detail.table === 'blacklist') { 
        const selectedRowId = bl_selected.value?.rowid
        await fetchData()
        if (selectedRowId) {
          bl_selected.value = tableItems.value.find(item => item.rowid === selectedRowId) || null
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
      bl_Filter, bl_selected, filteredItems, isLoading,
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
