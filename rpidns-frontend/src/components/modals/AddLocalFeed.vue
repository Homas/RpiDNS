<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Add Local Feed" 
    id="mAddLocalFeed" 
    body-class="pt-0 pb-0" 
    ok-title="Add Feed" 
    :ok-disabled="!isFormValid || loading"
    @ok="addFeed"
    @show="onShow"
    @hidden="onHidden"
  >
    <BContainer fluid>
      <!-- Feed Name -->
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <label class="form-label mb-1">Feed Name <span class="text-danger">*</span></label>
          <BFormInput 
            v-model.trim="feedName" 
            placeholder="e.g., my-blocklist.local"
            :state="feedNameState"
            @input="validateFeedName"
          />
          <BFormInvalidFeedback :state="feedNameState">
            {{ feedNameError }}
          </BFormInvalidFeedback>
          <small class="text-muted d-block text-start mt-1">
            Must follow DNS naming conventions (alphanumeric, hyphens, dots)
          </small>
        </BCol>
      </BRow>

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
          />
          <BFormInvalidFeedback :state="cnameTargetState">
            CNAME target is required when using CNAME action
          </BFormInvalidFeedback>
        </BCol>
      </BRow>

      <!-- Description -->
      <BRow class="pb-2">
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
          <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Adding feed...
        </BCol>
      </BRow>
    </BContainer>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'

// DNS name validation regex
// Allows alphanumeric, hyphens, dots; no leading/trailing hyphens per label
const DNS_NAME_REGEX = /^(?!-)(?!.*--)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.(?!-)(?!.*--)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/

function validateDnsName(name) {
  if (!name) {
    return { valid: false, message: 'Feed name is required' }
  }
  
  if (name.length > 253) {
    return { valid: false, message: 'Feed name must be 253 characters or less' }
  }
  
  const labels = name.split('.')
  for (const label of labels) {
    if (label.length > 63) {
      return { valid: false, message: 'Each label must be 63 characters or less' }
    }
    if (label.startsWith('-') || label.endsWith('-')) {
      return { valid: false, message: 'Labels cannot start or end with hyphens' }
    }
  }
  
  if (!DNS_NAME_REGEX.test(name)) {
    return { valid: false, message: 'Invalid DNS name format. Use alphanumeric characters, hyphens, and dots.' }
  }
  
  return { valid: true, message: '' }
}

export default {
  name: 'AddLocalFeed',
  emits: ['show-info', 'refresh-feeds'],
  setup(_props, { emit, expose }) {
    const api = useApi()
    const isVisible = ref(false)
    const feedName = ref('')
    const policyAction = ref('nxdomain')
    const cnameTarget = ref('')
    const description = ref('')
    const loading = ref(false)
    const error = ref('')
    const feedNameError = ref('')

    const policyOptions = [
      { value: 'nxdomain', text: 'nxdomain (domain does not exist)' },
      { value: 'nodata', text: 'nodata (no records for query type)' },
      { value: 'passthru', text: 'passthru (allow query)' },
      { value: 'drop', text: 'drop (silently drop query)' },
      { value: 'cname', text: 'cname (redirect to another domain)' },
      { value: 'given', text: 'given (use feed-defined action)' }
    ]

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      feedName.value = ''
      policyAction.value = 'nxdomain'
      cnameTarget.value = ''
      description.value = ''
      error.value = ''
      feedNameError.value = ''
    }

    const onHidden = () => {
      feedName.value = ''
      policyAction.value = 'nxdomain'
      cnameTarget.value = ''
      description.value = ''
      error.value = ''
      feedNameError.value = ''
    }

    const validateFeedName = () => {
      if (feedName.value.length === 0) {
        feedNameError.value = ''
        return
      }
      const result = validateDnsName(feedName.value)
      feedNameError.value = result.message
    }

    const feedNameState = computed(() => {
      if (feedName.value.length === 0) return null
      return validateDnsName(feedName.value).valid
    })

    const cnameTargetState = computed(() => {
      if (policyAction.value !== 'cname') return null
      if (cnameTarget.value.length === 0) return null
      return cnameTarget.value.length > 0
    })

    const isFormValid = computed(() => {
      const nameValid = validateDnsName(feedName.value).valid
      const cnameValid = policyAction.value !== 'cname' || cnameTarget.value.length > 0
      return nameValid && cnameValid
    })

    const addFeed = async (event) => {
      event.preventDefault()
      
      if (!isFormValid.value) return

      loading.value = true
      error.value = ''

      try {
        const feedData = {
          feed: feedName.value,
          source: 'local',
          action: policyAction.value,
          description: description.value
        }

        if (policyAction.value === 'cname') {
          feedData.cnameTarget = cnameTarget.value
        }

        const response = await api.post({ req: 'rpz_feed' }, { feeds: [feedData] })

        if (response.status === 'success' || response.status === 'warning') {
          hide()
          emit('refresh-feeds')
          const msg = response.status === 'warning' 
            ? response.reason 
            : 'Local feed added successfully'
          emit('show-info', { msg, time: 3 })
        } else {
          error.value = response.reason || 'Failed to add feed'
        }
      } catch (err) {
        error.value = 'Failed to add feed'
      } finally {
        loading.value = false
      }
    }

    expose({ show, hide })

    return {
      isVisible,
      feedName,
      policyAction,
      policyOptions,
      cnameTarget,
      description,
      loading,
      error,
      feedNameError,
      feedNameState,
      cnameTargetState,
      isFormValid,
      show,
      hide,
      onShow,
      onHidden,
      validateFeedName,
      addFeed
    }
  }
}
</script>
