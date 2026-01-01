<template>
  <BModal 
    v-model="isVisible"
    centered 
    :title="modalTitle" 
    id="mEditFeed" 
    body-class="pt-0 pb-0" 
    size="lg"
    ok-title="Save Changes" 
    :ok-disabled="!isFormValid || loading"
    @ok="saveFeed"
    @show="onShow"
    @hidden="onHidden"
  >
    <BContainer fluid>
      <!-- Feed Name (read-only) -->
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Feed Name</label>
          <BFormInput 
            v-model="feedName" 
            disabled
            readonly
          />
          <small class="text-muted d-block text-start mt-1">
            Feed name cannot be changed
          </small>
        </BCol>
      </BRow>

      <!-- Source Type Badge -->
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Source Type</label>
          <div>
            <BBadge :variant="sourceVariant">{{ sourceLabel }}</BBadge>
            <small v-if="isIoc2rpz" class="text-muted ms-2">
              Only policy action can be modified for ioc2rpz.net feeds
            </small>
          </div>
        </BCol>
      </BRow>

      <!-- Primary Server (third-party only) -->
      <BRow v-if="isThirdParty" class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Primary Server <span class="text-danger">*</span></label>
          <BFormInput 
            v-model.trim="primaryServer" 
            placeholder="e.g., 192.168.1.100 or ns1.example.com"
            :state="primaryServerState"
          />
          <BFormInvalidFeedback :state="primaryServerState">
            Primary server IP or hostname is required
          </BFormInvalidFeedback>
        </BCol>
      </BRow>

      <!-- TSIG Key Section (third-party only) -->
      <template v-if="isThirdParty">
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <BFormCheckbox v-model="useTsig" switch>
              Use TSIG authentication for zone transfers
            </BFormCheckbox>
          </BCol>
        </BRow>

        <template v-if="useTsig">
          <!-- TSIG Key Name -->
          <BRow class="pb-2">
            <BCol md="12" class="p-0">
              <label class="form-label mb-1">TSIG Key Name</label>
              <BFormInput 
                v-model.trim="tsigKeyName" 
                placeholder="e.g., transfer-key"
                :state="tsigKeyNameState"
              />
              <BFormInvalidFeedback :state="tsigKeyNameState">
                TSIG key name is required when using TSIG authentication
              </BFormInvalidFeedback>
            </BCol>
          </BRow>

          <!-- TSIG Key Secret -->
          <BRow class="pb-2">
            <BCol md="12" class="p-0">
              <label class="form-label mb-1">TSIG Key Secret</label>
              <BFormInput 
                v-model.trim="tsigKeySecret" 
                type="password"
                placeholder="Leave empty to keep existing secret"
              />
              <small class="text-muted d-block text-start mt-1">
                Leave empty to keep the existing secret unchanged
              </small>
            </BCol>
          </BRow>
        </template>
      </template>

      <!-- Policy Action -->
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Policy Action</label>
          <BFormSelect v-model="policyAction" :options="policyOptions" />
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
            :disabled="isIoc2rpz"
          />
          <BFormInvalidFeedback :state="cnameTargetState">
            CNAME target is required when using CNAME action
          </BFormInvalidFeedback>
        </BCol>
      </BRow>

      <!-- Description (not for ioc2rpz) -->
      <BRow v-if="!isIoc2rpz" class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Description</label>
          <BFormTextarea 
            v-model.trim="description" 
            placeholder="Optional description for this feed"
            rows="2"
            max-rows="4"
            maxlength="500"
          />
        </BCol>
      </BRow>

      <!-- Error Alert -->
      <BRow v-if="error" class="pb-1">
        <BCol md="12" class="p-0">
          <BAlert variant="danger" show class="mb-0 py-2">{{ error }}</BAlert>
        </BCol>
      </BRow>

      <!-- Loading State -->
      <BRow v-if="loading" class="pb-1">
        <BCol md="12" class="p-0 text-center">
          <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Saving changes...
        </BCol>
      </BRow>
    </BContainer>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'EditFeed',
  props: {
    feed: {
      type: Object,
      default: null
    }
  },
  emits: ['show-info', 'refresh-feeds'],
  setup(props, { emit, expose }) {
    const api = useApi()
    const isVisible = ref(false)
    const feedName = ref('')
    const source = ref('')
    const primaryServer = ref('')
    const useTsig = ref(false)
    const tsigKeyName = ref('')
    const tsigKeySecret = ref('')
    const policyAction = ref('nxdomain')
    const cnameTarget = ref('')
    const description = ref('')
    const loading = ref(false)
    const error = ref('')

    // Policy options vary by source type
    const basePolicyOptions = [
      { value: 'nxdomain', text: 'nxdomain (domain does not exist)' },
      { value: 'nodata', text: 'nodata (no records for query type)' },
      { value: 'passthru', text: 'passthru (allow query)' },
      { value: 'drop', text: 'drop (silently drop query)' },
      { value: 'cname', text: 'cname (redirect to another domain)' }
    ]

    const ioc2rpzPolicyOptions = [
      { value: 'given', text: 'given (use feed-defined action)' },
      ...basePolicyOptions
    ]

    const policyOptions = computed(() => {
      return source.value === 'ioc2rpz' ? ioc2rpzPolicyOptions : basePolicyOptions
    })

    const isIoc2rpz = computed(() => source.value === 'ioc2rpz')
    const isLocal = computed(() => source.value === 'local')
    const isThirdParty = computed(() => source.value === 'third-party')

    const sourceLabel = computed(() => {
      switch (source.value) {
        case 'ioc2rpz': return 'ioc2rpz.net'
        case 'local': return 'Local'
        case 'third-party': return 'Third-Party'
        default: return source.value
      }
    })

    const sourceVariant = computed(() => {
      switch (source.value) {
        case 'ioc2rpz': return 'primary'
        case 'local': return 'success'
        case 'third-party': return 'info'
        default: return 'secondary'
      }
    })

    const modalTitle = computed(() => {
      return `Edit Feed: ${feedName.value}`
    })

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      if (props.feed) {
        feedName.value = props.feed.feed || ''
        source.value = props.feed.source || ''
        policyAction.value = props.feed.action || 'nxdomain'
        cnameTarget.value = props.feed.cnameTarget || ''
        description.value = props.feed.desc || ''
        primaryServer.value = props.feed.primaryServer || ''
        tsigKeyName.value = props.feed.tsigKeyName || ''
        useTsig.value = !!props.feed.tsigKeyName
        tsigKeySecret.value = '' // Never pre-fill secrets
      }
      error.value = ''
    }

    const onHidden = () => {
      feedName.value = ''
      source.value = ''
      primaryServer.value = ''
      useTsig.value = false
      tsigKeyName.value = ''
      tsigKeySecret.value = ''
      policyAction.value = 'nxdomain'
      cnameTarget.value = ''
      description.value = ''
      error.value = ''
    }

    const primaryServerState = computed(() => {
      if (!isThirdParty.value) return null
      if (primaryServer.value.length === 0) return null
      return primaryServer.value.length > 0
    })

    const tsigKeyNameState = computed(() => {
      if (!isThirdParty.value || !useTsig.value) return null
      if (tsigKeyName.value.length === 0) return null
      return tsigKeyName.value.length > 0
    })

    const cnameTargetState = computed(() => {
      if (policyAction.value !== 'cname') return null
      if (cnameTarget.value.length === 0) return null
      return cnameTarget.value.length > 0
    })

    const isFormValid = computed(() => {
      // For ioc2rpz, only policy action matters (and CNAME target if applicable)
      if (isIoc2rpz.value) {
        return policyAction.value !== 'cname' || cnameTarget.value.length > 0
      }
      
      // For third-party, need primary server and TSIG validation
      if (isThirdParty.value) {
        const serverValid = primaryServer.value.length > 0
        const tsigValid = !useTsig.value || tsigKeyName.value.length > 0
        const cnameValid = policyAction.value !== 'cname' || cnameTarget.value.length > 0
        return serverValid && tsigValid && cnameValid
      }
      
      // For local, just CNAME validation
      return policyAction.value !== 'cname' || cnameTarget.value.length > 0
    })

    const saveFeed = async (event) => {
      event.preventDefault()
      
      if (!isFormValid.value) return

      loading.value = true
      error.value = ''

      try {
        const feedData = {
          feed: feedName.value,
          action: policyAction.value
        }

        // Add fields based on source type
        if (!isIoc2rpz.value) {
          feedData.description = description.value
        }

        if (isThirdParty.value) {
          feedData.primaryServer = primaryServer.value
          if (useTsig.value) {
            feedData.tsigKeyName = tsigKeyName.value
            if (tsigKeySecret.value) {
              feedData.tsigKeySecret = tsigKeySecret.value
            }
          } else {
            feedData.tsigKeyName = ''
          }
        }

        if (policyAction.value === 'cname') {
          feedData.cnameTarget = cnameTarget.value
        }

        const response = await api.put({ req: 'rpz_feed' }, feedData)

        if (response.status === 'success' || response.status === 'warning') {
          hide()
          emit('refresh-feeds')
          const msg = response.status === 'warning' 
            ? response.reason 
            : 'Feed updated successfully'
          emit('show-info', { msg, time: 3 })
        } else {
          error.value = response.reason || 'Failed to update feed'
        }
      } catch (err) {
        error.value = 'Failed to update feed'
      } finally {
        loading.value = false
      }
    }

    expose({ show, hide })

    return {
      isVisible,
      feedName,
      source,
      primaryServer,
      useTsig,
      tsigKeyName,
      tsigKeySecret,
      policyAction,
      policyOptions,
      cnameTarget,
      description,
      loading,
      error,
      isIoc2rpz,
      isLocal,
      isThirdParty,
      sourceLabel,
      sourceVariant,
      modalTitle,
      primaryServerState,
      tsigKeyNameState,
      cnameTargetState,
      isFormValid,
      show,
      hide,
      onShow,
      onHidden,
      saveFeed
    }
  }
}
</script>
