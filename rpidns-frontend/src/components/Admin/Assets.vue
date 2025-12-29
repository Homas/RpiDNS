<template>
  <div>
    <!-- Toolbar Row -->
    <BRow class="d-none d-sm-flex">
      <BCol cols="3" lg="3">
        <BButton v-b-tooltip.hover title="Add" variant="outline-secondary" size="sm" @click.stop="openAddModal">
          <i class="fa fa-plus"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!asset_selected" @click.stop="openEditModal">
          <i class="fa fa-edit"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" :disabled="!asset_selected" @click.stop="confirmDelete">
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
            <BFormInput v-model="assets_Filter" placeholder="Type to search" size="sm"></BFormInput>
            <template #append>
              <BButton size="sm" :disabled="!assets_Filter" @click="assets_Filter = ''">Clear</BButton>
            </template>
          </BInputGroup>
        </BFormGroup>
      </BCol>
    </BRow>

    <!-- Assets Table -->
    <BRow>
      <BCol cols="12" lg="12">
        <BTableSimple id="assets" :sticky-header="`${logs_height}px`" striped hover small>
          <BThead>
            <BTr>
              <BTh class="width050 d-none d-sm-table-cell"></BTh>
              <BTh class="d-none d-sm-table-cell">Address</BTh>
              <BTh>Name</BTh>
              <BTh class="d-none d-md-table-cell">Vendor</BTh>
              <BTh class="d-none d-md-table-cell">Added</BTh>
              <BTh class="d-none d-md-table-cell">Comment</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-for="item in filteredItems" :key="item.rowid">
              <BTd class="width050 d-none d-sm-table-cell">
                <BFormCheckbox :value="item" v-model="asset_selected" />
              </BTd>
              <BTd class="mw150 d-none d-sm-table-cell">{{ item.address }}</BTd>
              <BTd class="mw200">{{ item.name }}</BTd>
              <BTd class="mw150 d-none d-md-table-cell">{{ item.vendor }}</BTd>
              <BTd class="mw150 d-none d-md-table-cell">{{ formatDate(item.dtz) }}</BTd>
              <BTd class="mw150 d-none d-md-table-cell">{{ item.comment }}</BTd>
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
  name: 'Assets',
  props: { logs_height: { type: Number, default: 150 } },
  emits: ['navigate', 'add-asset', 'delete-asset'],
  setup(props, { emit }) {
    const api = useApi()
    const assets_Filter = ref('')
    const asset_selected = ref(null)
    const tableItems = ref([])
    const isLoading = ref(false)

    const filteredItems = computed(() => {
      if (!assets_Filter.value) return tableItems.value
      const filter = assets_Filter.value.toLowerCase()
      return tableItems.value.filter(item => 
        (item.address && item.address.toLowerCase().includes(filter)) ||
        (item.name && item.name.toLowerCase().includes(filter)) ||
        (item.vendor && item.vendor.toLowerCase().includes(filter)) ||
        (item.comment && item.comment.toLowerCase().includes(filter))
      )
    })

    const fetchData = async () => {
      isLoading.value = true
      try {
        const response = await api.get({ req: 'assets', sortBy: 'name', sortDesc: false })
        tableItems.value = response.data || []
      } catch (error) {
        console.error('Error fetching assets:', error)
        tableItems.value = []
      } finally {
        isLoading.value = false
      }
    }

    const refreshTable = () => { fetchData() }
    const formatDate = (value) => { const date = new Date(value); return date.toLocaleString() }

    const openAddModal = () => {
      emit('add-asset', { mode: 'add', address: '', name: '', vendor: '', comment: '', rowid: 0 })
    }

    const openEditModal = () => {
      if (asset_selected.value) {
        emit('add-asset', {
          mode: 'edit',
          address: asset_selected.value.address,
          name: asset_selected.value.name,
          vendor: asset_selected.value.vendor,
          comment: asset_selected.value.comment,
          rowid: asset_selected.value.rowid
        })
      }
    }

    const confirmDelete = () => {
      if (asset_selected.value) {
        emit('delete-asset', { asset: asset_selected.value, table: 'assets' })
      }
    }

    const navigateToQueries = (item) => { emit('navigate', { type: 'qlogs', filter: item.address, tab: 1 }) }
    const navigateToHits = (item) => { emit('navigate', { type: 'hits', filter: item.address, tab: 2 }) }

    const handleRefreshEvent = (event) => {
      if (event.detail && event.detail.table === 'assets') { fetchData() }
    }

    onMounted(() => {
      fetchData()
      window.addEventListener('refresh-table', handleRefreshEvent)
    })

    onBeforeUnmount(() => {
      window.removeEventListener('refresh-table', handleRefreshEvent)
    })

    return {
      assets_Filter, asset_selected, filteredItems, isLoading,
      refreshTable, formatDate, openAddModal, openEditModal, confirmDelete,
      navigateToQueries, navigateToHits
    }
  }
}
</script>

<style scoped>
.width050 { width: 50px; }
.mw150 { max-width: 150px; }
.mw200 { max-width: 200px; }
</style>
