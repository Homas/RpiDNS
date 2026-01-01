<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Add ioc2rpz.net Feeds" 
    id="mAddIoc2rpzFeed" 
    body-class="pt-0 pb-0" 
    size="lg"
    ok-title="Add Selected" 
    :ok-disabled="selectedFeeds.length === 0 || loading"
    @ok="addSelectedFeeds"
    @show="onShow"
    @hidden="onHidden"
  >
    <BContainer fluid>
      <!-- Error Alert -->
      <BRow v-if="error" class="pb-2">
        <BCol md="12" class="p-0">
          <BAlert variant="danger" show class="mb-0 py-2">
            {{ error }}
            <BButton v-if="canRetry" variant="link" size="sm" class="p-0 ms-2" @click="fetchAvailableFeeds">
              <i class="fas fa-redo"></i> Retry
            </BButton>
          </BAlert>
        </BCol>
      </BRow>

      <!-- No TSIG Key Warning -->
      <BRow v-if="!tsigKeyFound && !loading && !error" class="pb-2">
        <BCol md="12" class="p-0">
          <BAlert variant="warning" show class="mb-0 py-2">
            No TSIG key configured for ioc2rpz.net. Please configure a TSIG key in your BIND configuration to use ioc2rpz.net feeds.
          </BAlert>
        </BCol>
      </BRow>

      <!-- Loading State -->
      <BRow v-if="loading" class="pb-2">
        <BCol md="12" class="p-0 text-center py-4">
          <BSpinner type="grow"></BSpinner>
          <div class="mt-2">Loading available feeds...</div>
        </BCol>
      </BRow>

      <!-- Feed Selection -->
      <template v-if="!loading && tsigKeyFound && availableFeeds.length > 0">
        <!-- Policy Action Selector -->
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <label class="form-label mb-1">Policy Action</label>
            <BFormSelect v-model="policyAction" :options="policyOptions" size="sm" />
          </BCol>
        </BRow>

        <!-- CNAME Target (shown when CNAME selected) -->
        <BRow v-if="policyAction === 'cname'" class="pb-2">
          <BCol md="12" class="p-0">
            <label class="form-label mb-1">CNAME Target <span class="text-danger">*</span></label>
            <BFormInput 
              v-model.trim="cnameTarget" 
              placeholder="e.g., blocked.example.com"
              :state="cnameTargetState"
              size="sm"
            />
            <BFormInvalidFeedback :state="cnameTargetState">
              CNAME target is required when using CNAME action
            </BFormInvalidFeedback>
          </BCol>
        </BRow>

        <!-- Selection count -->
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <span class="text-muted">{{ selectedFeeds.length }} of {{ selectableFeeds.length }} feeds selected</span>
          </BCol>
        </BRow>

        <!-- Feeds Table -->
        <BRow>
          <BCol md="12" class="p-0">
            <div class="feeds-list" style="max-height: 350px; overflow-y: auto;">
              <BTableSimple striped hover small>
                <BThead>
                  <BTr>
                    <BTh style="width: 40px;">
                      <BFormCheckbox 
                        :checked="allSelectableSelected"
                        :indeterminate="someSelected && !allSelectableSelected"
                        @change="toggleSelectAll"
                      />
                    </BTh>
                    <BTh>Feed Name</BTh>
                    <BTh class="d-none d-md-table-cell">Type</BTh>
                    <BTh class="d-none d-sm-table-cell">Rules</BTh>
                    <BTh class="d-none d-lg-table-cell">Description</BTh>
                  </BTr>
                </BThead>
                <BTbody>
                  <BTr 
                    v-for="feed in availableFeeds" 
                    :key="feed.rpz"
                    :class="{ 'table-secondary': feed.already_configured }"
                  >
                    <BTd>
                      <BFormCheckbox 
                        :checked="isSelected(feed)"
                        :disabled="feed.already_configured"
                        @change="toggleFeedSelection(feed)"
                      />
                    </BTd>
                    <BTd>
                      {{ feed.rpz }}
                      <BBadge v-if="feed.already_configured" variant="info" class="ms-1">Configured</BBadge>
                    </BTd>
                    <BTd class="d-none d-md-table-cell">
                      <BBadge :variant="feed.feed_type === 'community' ? 'success' : 'primary'">
                        {{ feed.feed_type }}
                      </BBadge>
                    </BTd>
                    <BTd class="d-none d-sm-table-cell">{{ feed.rules_count || '0' }}</BTd>
                    <BTd 
                      class="d-none d-lg-table-cell text-truncate" 
                      style="max-width: 250px;"
                      v-b-tooltip.hover.left
                      :title="feed.description"
                    >
                      {{ feed.description }}
                    </BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
            </div>
          </BCol>
        </BRow>
      </template>

      <!-- No Feeds Available -->
      <BRow v-if="!loading && tsigKeyFound && availableFeeds.length === 0 && !error" class="pb-2">
        <BCol md="12" class="p-0 text-center py-4">
          <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
          <div class="text-muted">No feeds available from ioc2rpz.net</div>
        </BCol>
      </BRow>

      <!-- Submitting State -->
      <BRow v-if="submitting" class="pb-1">
        <BCol md="12" class="p-0 text-center">
          <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Adding feeds...
        </BCol>
      </BRow>
    </BContainer>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'AddIoc2rpzFeed',
  emits: ['show-info', 'refresh-feeds'],
  setup(_props, { emit, expose }) {
    const api = useApi()
    const isVisible = ref(false)
    const availableFeeds = ref([])
    const selectedFeeds = ref([])
    const policyAction = ref('given')
    const cnameTarget = ref('')
    const loading = ref(false)
    const submitting = ref(false)
    const error = ref('')
    const tsigKeyFound = ref(false)
    const tsigKeyName = ref('')
    const canRetry = ref(false)

    const policyOptions = [
      { value: 'given', text: 'given (use feed-defined action)' },
      { value: 'nxdomain', text: 'nxdomain (domain does not exist)' },
      { value: 'nodata', text: 'nodata (no records for query type)' },
      { value: 'passthru', text: 'passthru (allow query)' },
      { value: 'drop', text: 'drop (silently drop query)' },
      { value: 'cname', text: 'cname (redirect to another domain)' }
    ]

    const cnameTargetState = computed(() => {
      if (policyAction.value !== 'cname') return null
      if (cnameTarget.value.length === 0) return null
      return cnameTarget.value.length > 0
    })

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      selectedFeeds.value = []
      policyAction.value = 'given'
      cnameTarget.value = ''
      error.value = ''
      fetchAvailableFeeds()
    }

    const onHidden = () => {
      availableFeeds.value = []
      selectedFeeds.value = []
      cnameTarget.value = ''
      error.value = ''
      tsigKeyFound.value = false
    }

    const fetchAvailableFeeds = async () => {
      loading.value = true
      error.value = ''
      canRetry.value = false

      try {
        const response = await api.get({ req: 'ioc2rpz_available' })
        
        if (response.status === 'error') {
          error.value = response.reason || 'Failed to fetch available feeds'
          canRetry.value = response.code === 'IOC2RPZ_API_ERROR'
          tsigKeyFound.value = response.tsig_key_found || false
          tsigKeyName.value = response.tsig_key_name || ''
          availableFeeds.value = []
        } else {
          tsigKeyFound.value = response.tsig_key_found || true
          tsigKeyName.value = response.tsig_key_name || ''
          availableFeeds.value = response.data || []
        }
      } catch (err) {
        error.value = 'Failed to fetch available feeds'
        canRetry.value = true
        availableFeeds.value = []
      } finally {
        loading.value = false
      }
    }

    const isSelected = (feed) => {
      return selectedFeeds.value.some(f => f.rpz === feed.rpz)
    }

    const toggleFeedSelection = (feed) => {
      if (feed.already_configured) return
      
      const index = selectedFeeds.value.findIndex(f => f.rpz === feed.rpz)
      if (index === -1) {
        selectedFeeds.value.push(feed)
      } else {
        selectedFeeds.value.splice(index, 1)
      }
    }

    const selectAll = () => {
      selectedFeeds.value = availableFeeds.value.filter(f => !f.already_configured)
    }

    const deselectAll = () => {
      selectedFeeds.value = []
    }

    const selectableFeeds = computed(() => {
      return availableFeeds.value.filter(f => !f.already_configured)
    })

    const allSelectableSelected = computed(() => {
      const selectable = selectableFeeds.value
      return selectable.length > 0 && selectedFeeds.value.length === selectable.length
    })

    const someSelected = computed(() => {
      return selectedFeeds.value.length > 0
    })

    const toggleSelectAll = () => {
      if (allSelectableSelected.value) {
        deselectAll()
      } else {
        selectAll()
      }
    }

    const addSelectedFeeds = async (event) => {
      event.preventDefault()
      
      if (selectedFeeds.value.length === 0) return
      
      // Validate CNAME target if action is cname
      if (policyAction.value === 'cname' && cnameTarget.value.length === 0) {
        error.value = 'CNAME target is required when using CNAME action'
        return
      }

      submitting.value = true
      error.value = ''

      try {
        const feedsToAdd = selectedFeeds.value.map(feed => {
          const feedData = {
            feed: feed.rpz,
            source: 'ioc2rpz',
            action: policyAction.value,
            description: feed.description || ''
          }
          if (policyAction.value === 'cname') {
            feedData.cnameTarget = cnameTarget.value
          }
          return feedData
        })

        const response = await api.post({ req: 'rpz_feed' }, { feeds: feedsToAdd })

        if (response.status === 'success' || response.status === 'warning') {
          hide()
          emit('refresh-feeds')
          const msg = response.status === 'warning' 
            ? response.reason 
            : `${response.added} feed(s) added successfully`
          emit('show-info', { msg, time: 3 })
        } else {
          error.value = response.reason || 'Failed to add feeds'
        }
      } catch (err) {
        error.value = 'Failed to add feeds'
      } finally {
        submitting.value = false
      }
    }

    expose({ show, hide })

    return {
      isVisible,
      availableFeeds,
      selectedFeeds,
      policyAction,
      policyOptions,
      loading,
      submitting,
      error,
      tsigKeyFound,
      tsigKeyName,
      canRetry,
      selectableFeeds,
      allSelectableSelected,
      someSelected,
      show,
      hide,
      onShow,
      onHidden,
      fetchAvailableFeeds,
      isSelected,
      toggleFeedSelection,
      selectAll,
      deselectAll,
      toggleSelectAll,
      addSelectedFeeds,
      cnameTarget,
      cnameTargetState
    }
  }
}
</script>

<style scoped>
.feeds-list {
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
}
</style>
