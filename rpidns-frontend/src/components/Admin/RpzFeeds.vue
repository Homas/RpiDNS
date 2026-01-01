<template>
  <div>
    <!-- Toolbar Row -->
    <BRow class="d-none d-sm-flex mb-2">
      <BCol cols="6" lg="6">
        <!-- Add Dropdown -->
        <BDropdown variant="outline-secondary" size="sm" class="d-inline-block me-1">
          <template #button-content>
            <i class="fa fa-plus"></i> Add
          </template>
          <BDropdownItem @click="openAddIoc2rpzModal">
            <i class="fas fa-cloud-download-alt fa-fw me-1"></i> ioc2rpz.net Feed
          </BDropdownItem>
          <BDropdownItem @click="openAddLocalModal">
            <i class="fas fa-file-alt fa-fw me-1"></i> Local Feed
          </BDropdownItem>
          <BDropdownItem @click="openAddThirdPartyModal">
            <i class="fas fa-server fa-fw me-1"></i> Third-Party Feed
          </BDropdownItem>
        </BDropdown>
        
        <BButton v-b-tooltip.hover title="Edit" variant="outline-secondary" size="sm" :disabled="!selectedFeed" @click.stop="openEditModal" class="me-1">
          <i class="fa fa-edit"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Delete" variant="outline-secondary" size="sm" :disabled="!selectedFeed" @click.stop="confirmDelete" class="me-1">
          <i class="fa fa-trash-alt"></i>
        </BButton>
        <BButton v-b-tooltip.hover :title="selectedFeed && selectedFeed.enabled ? 'Disable' : 'Enable'" variant="outline-secondary" size="sm" :disabled="!selectedFeed" @click.stop="toggleFeedStatus" class="me-1">
          <i :class="selectedFeed && selectedFeed.enabled ? 'fas fa-toggle-on' : 'fas fa-toggle-off'"></i>
        </BButton>
        <BButton v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="fetchData">
          <i class="fa fa-sync"></i>
        </BButton>
      </BCol>
      <BCol cols="6" lg="6" class="text-end">
        <small class="text-muted">
          <i class="fas fa-grip-vertical me-1"></i> Drag rows to reorder feeds
        </small>
      </BCol>
    </BRow>

    <!-- Feeds Table -->
    <BRow>
      <BCol cols="12" lg="12">
        <BTableSimple id="rpz_feeds" :sticky-header="`${logs_height}px`" striped hover small>
          <BThead>
            <BTr>
              <BTh class="width030"></BTh>
              <BTh class="width040"></BTh>
              <BTh>Feed</BTh>
              <BTh class="d-none d-sm-table-cell">Action</BTh>
              <BTh class="d-none d-md-table-cell">Source</BTh>
              <BTh class="d-none d-md-table-cell">Status</BTh>
              <BTh class="d-none d-lg-table-cell">Description</BTh>
              <BTh class="width050">Actions</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr 
              v-for="(item, index) in tableItems" 
              :key="item.feed"
              :class="getRowClass(item)"
              :draggable="true"
              @dragstart="handleDragStart($event, index)"
              @dragover="handleDragOver($event, index)"
              @dragend="handleDragEnd"
              @drop="handleDrop($event, index)"
              @click="selectFeed(item)"
            >
              <BTd class="width030 drag-handle" style="cursor: grab;">
                <i class="fas fa-grip-vertical text-muted"></i>
              </BTd>
              <BTd class="width040">
                <BFormCheckbox 
                  :checked="isSelected(item)" 
                  @change="selectFeed(item)"
                  @click.stop
                />
              </BTd>
              <BTd class="mw200">{{ item.feed }}</BTd>
              <BTd class="d-none d-sm-table-cell mw100">
                <BBadge :variant="getActionVariant(item.action)">{{ item.action }}</BBadge>
              </BTd>
              <BTd class="d-none d-md-table-cell mw100">
                <BBadge :variant="getSourceVariant(item.source)">{{ getSourceLabel(item.source) }}</BBadge>
              </BTd>
              <BTd class="d-none d-md-table-cell width080">
                <span v-if="item.enabled" class="text-success">
                  <i class="fas fa-check-circle"></i> Enabled
                </span>
                <span v-else class="text-muted">
                  <i class="fas fa-times-circle"></i> Disabled
                </span>
              </BTd>
              <BTd class="d-none d-lg-table-cell mw300">{{ item.desc }}</BTd>
              <BTd class="width050">
                <BButton v-b-tooltip.hover title="Retransfer" variant="outline-secondary" size="sm" @click.stop="retransferRPZ(item)">
                  <i class="fas fa-redo"></i>
                </BButton>
              </BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
        
        <!-- Loading State -->
        <div v-if="isLoading" class="text-center m-0 p-0">
          <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
        </div>
        
        <!-- Saving Order State -->
        <div v-if="isSavingOrder" class="text-center m-0 p-0">
          <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Saving order...</strong>
        </div>
        
        <!-- Empty State -->
        <div v-if="!isLoading && tableItems.length === 0" class="text-center text-muted py-4">
          <i class="fas fa-shield-alt fa-2x mb-2"></i>
          <div>No RPZ feeds configured</div>
          <small>Click "Add" to add your first feed</small>
        </div>
      </BCol>
    </BRow>

    <!-- Delete Confirmation Modal -->
    <BModal 
      v-model="showDeleteConfirm" 
      centered 
      title="Confirm Delete" 
      ok-variant="danger"
      ok-title="Delete"
      @ok="deleteFeed"
    >
      <p>Are you sure you want to delete the feed <strong>{{ selectedFeed?.feed }}</strong>?</p>
      <p class="text-muted mb-0">This will remove the feed from your BIND configuration.</p>
    </BModal>

    <!-- Modal Components -->
    <AddIoc2rpzFeed ref="addIoc2rpzModal" @show-info="handleShowInfo" @refresh-feeds="fetchData" />
    <AddLocalFeed ref="addLocalModal" @show-info="handleShowInfo" @refresh-feeds="fetchData" />
    <AddThirdPartyFeed ref="addThirdPartyModal" @show-info="handleShowInfo" @refresh-feeds="fetchData" />
    <EditFeed ref="editModal" :feed="selectedFeed" @show-info="handleShowInfo" @refresh-feeds="fetchData" />
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import AddIoc2rpzFeed from '@/components/modals/AddIoc2rpzFeed.vue'
import AddLocalFeed from '@/components/modals/AddLocalFeed.vue'
import AddThirdPartyFeed from '@/components/modals/AddThirdPartyFeed.vue'
import EditFeed from '@/components/modals/EditFeed.vue'

export default {
  name: 'RpzFeeds',
  components: {
    AddIoc2rpzFeed,
    AddLocalFeed,
    AddThirdPartyFeed,
    EditFeed
  },
  props: { logs_height: { type: Number, default: 150 } },
  emits: ['show-info'],
  setup(props, { emit }) {
    const api = useApi()
    const tableItems = ref([])
    const selectedFeed = ref(null)
    const isLoading = ref(false)
    const isSavingOrder = ref(false)
    const showDeleteConfirm = ref(false)
    
    // Drag and drop state
    const draggedIndex = ref(null)
    const dragOverIndex = ref(null)

    // Modal refs
    const addIoc2rpzModal = ref(null)
    const addLocalModal = ref(null)
    const addThirdPartyModal = ref(null)
    const editModal = ref(null)

    // Fetch feeds from backend
    const fetchData = async () => {
      isLoading.value = true
      selectedFeed.value = null
      try {
        const response = await api.get({ req: 'rpz_feeds', sortBy: 'order', sortDesc: false })
        tableItems.value = response.data || []
      } catch (error) {
        console.error('Error fetching RPZ feeds:', error)
        tableItems.value = []
      } finally {
        isLoading.value = false
      }
    }

    // Selection handling
    const selectFeed = (item) => {
      if (selectedFeed.value && selectedFeed.value.feed === item.feed) {
        selectedFeed.value = null
      } else {
        selectedFeed.value = item
      }
    }

    const isSelected = (item) => {
      return selectedFeed.value && selectedFeed.value.feed === item.feed
    }

    // Row styling
    const getRowClass = (item) => {
      const classes = []
      if (isSelected(item)) {
        classes.push('table-primary')
      }
      if (!item.enabled) {
        classes.push('text-muted')
      }
      if (dragOverIndex.value !== null && tableItems.value[dragOverIndex.value]?.feed === item.feed) {
        classes.push('drag-over')
      }
      return classes.join(' ')
    }

    // Badge variants
    const getActionVariant = (action) => {
      switch (action) {
        case 'nxdomain': return 'danger'
        case 'nodata': return 'warning'
        case 'passthru': return 'success'
        case 'drop': return 'dark'
        case 'cname': return 'info'
        case 'given': return 'primary'
        default: return 'secondary'
      }
    }

    const getSourceVariant = (source) => {
      switch (source) {
        case 'ioc2rpz': return 'primary'
        case 'local': return 'success'
        case 'third-party': return 'info'
        default: return 'secondary'
      }
    }

    const getSourceLabel = (source) => {
      switch (source) {
        case 'ioc2rpz': return 'ioc2rpz.net'
        case 'local': return 'Local'
        case 'third-party': return 'Third-Party'
        default: return source
      }
    }

    // Modal handlers
    const openAddIoc2rpzModal = () => {
      addIoc2rpzModal.value?.show()
    }

    const openAddLocalModal = () => {
      addLocalModal.value?.show()
    }

    const openAddThirdPartyModal = () => {
      addThirdPartyModal.value?.show()
    }

    const openEditModal = () => {
      if (selectedFeed.value) {
        editModal.value?.show()
      }
    }

    // Delete handling
    const confirmDelete = () => {
      if (selectedFeed.value) {
        showDeleteConfirm.value = true
      }
    }

    const deleteFeed = async () => {
      if (!selectedFeed.value) return

      try {
        const response = await api.del({ req: 'rpz_feed', feed: selectedFeed.value.feed })
        if (response.status === 'success') {
          emit('show-info', { msg: 'Feed deleted successfully', time: 3 })
          selectedFeed.value = null
          fetchData()
        } else {
          emit('show-info', { msg: response.reason || 'Failed to delete feed', time: 3 })
        }
      } catch (error) {
        console.error('Error deleting feed:', error)
        emit('show-info', { msg: 'Failed to delete feed', time: 3 })
      }
    }

    // Enable/Disable toggle
    const toggleFeedStatus = async () => {
      if (!selectedFeed.value) return

      try {
        const response = await api.put(
          { req: 'rpz_feed_status' }, 
          { feed: selectedFeed.value.feed, enabled: !selectedFeed.value.enabled }
        )
        if (response.status === 'success') {
          const action = selectedFeed.value.enabled ? 'disabled' : 'enabled'
          emit('show-info', { msg: `Feed ${action} successfully`, time: 3 })
          fetchData()
        } else {
          emit('show-info', { msg: response.reason || 'Failed to update feed status', time: 3 })
        }
      } catch (error) {
        console.error('Error toggling feed status:', error)
        emit('show-info', { msg: 'Failed to update feed status', time: 3 })
      }
    }

    // Retransfer
    const retransferRPZ = async (item) => {
      try {
        const response = await api.put({ req: 'retransfer_feed' }, { feed: item.feed })
        if (response.status !== 'success') {
          emit('show-info', { msg: response.reason, time: 3 })
        } else {
          emit('show-info', { msg: 'Retransfer requested', time: 3 })
        }
      } catch (error) {
        console.error('Error requesting retransfer:', error)
        emit('show-info', { msg: 'Unknown error!!!', time: 3 })
      }
    }

    // Drag and drop handlers
    const handleDragStart = (event, index) => {
      draggedIndex.value = index
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', index)
      // Add a slight delay to allow the drag image to be captured
      setTimeout(() => {
        event.target.classList.add('dragging')
      }, 0)
    }

    const handleDragOver = (event, index) => {
      event.preventDefault()
      event.dataTransfer.dropEffect = 'move'
      dragOverIndex.value = index
    }

    const handleDragEnd = (event) => {
      event.target.classList.remove('dragging')
      draggedIndex.value = null
      dragOverIndex.value = null
    }

    const handleDrop = async (event, dropIndex) => {
      event.preventDefault()
      
      const fromIndex = draggedIndex.value
      if (fromIndex === null || fromIndex === dropIndex) {
        draggedIndex.value = null
        dragOverIndex.value = null
        return
      }

      // Reorder the array locally
      const items = [...tableItems.value]
      const [movedItem] = items.splice(fromIndex, 1)
      items.splice(dropIndex, 0, movedItem)
      tableItems.value = items

      // Reset drag state
      draggedIndex.value = null
      dragOverIndex.value = null

      // Save the new order to backend
      await saveFeedOrder()
    }

    const saveFeedOrder = async () => {
      isSavingOrder.value = true
      try {
        const order = tableItems.value.map(item => item.feed)
        const response = await api.put({ req: 'rpz_feeds_order' }, { order })
        if (response.status === 'success') {
          emit('show-info', { msg: 'Feed order updated', time: 2 })
        } else {
          emit('show-info', { msg: response.reason || 'Failed to update feed order', time: 3 })
          // Refresh to get the actual order from server
          fetchData()
        }
      } catch (error) {
        console.error('Error saving feed order:', error)
        emit('show-info', { msg: 'Failed to update feed order', time: 3 })
        fetchData()
      } finally {
        isSavingOrder.value = false
      }
    }

    // Event handler for modal events
    const handleShowInfo = (info) => {
      emit('show-info', info)
    }

    onMounted(() => { fetchData() })

    return {
      tableItems,
      selectedFeed,
      isLoading,
      isSavingOrder,
      showDeleteConfirm,
      addIoc2rpzModal,
      addLocalModal,
      addThirdPartyModal,
      editModal,
      fetchData,
      selectFeed,
      isSelected,
      getRowClass,
      getActionVariant,
      getSourceVariant,
      getSourceLabel,
      openAddIoc2rpzModal,
      openAddLocalModal,
      openAddThirdPartyModal,
      openEditModal,
      confirmDelete,
      deleteFeed,
      toggleFeedStatus,
      retransferRPZ,
      handleDragStart,
      handleDragOver,
      handleDragEnd,
      handleDrop,
      handleShowInfo
    }
  }
}
</script>

<style scoped>
.width030 { width: 30px; }
.width040 { width: 40px; }
.width050 { width: 50px; }
.width080 { width: 80px; }
.mw100 { max-width: 100px; }
.mw150 { max-width: 150px; }
.mw200 { max-width: 200px; }
.mw300 { max-width: 300px; }
.mw400 { max-width: 400px; }

.drag-handle {
  cursor: grab;
}

.drag-handle:active {
  cursor: grabbing;
}

.dragging {
  opacity: 0.5;
}

.drag-over {
  border-top: 2px solid #0d6efd;
}

tr {
  transition: background-color 0.15s ease;
}

tr:hover {
  cursor: pointer;
}
</style>
