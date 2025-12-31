<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Change Password" 
    id="mPasswordChange" 
    body-class="text-center pt-0 pb-0" 
    ok-title="Change Password" 
    :ok-disabled="!isFormValid || loading"
    @ok="handleSubmit"
    @show="onShow"
    @hidden="onHidden"
  >
    <form @submit.prevent="handleSubmit">
      <!-- Hidden username field for accessibility and password managers -->
      <input 
        type="text" 
        :value="username"
        autocomplete="username" 
        style="position: absolute; left: -9999px; width: 1px; height: 1px;"
        tabindex="-1"
        aria-hidden="true"
      />
      <BContainer fluid>
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model="currentPassword" 
              type="password"
              placeholder="Current Password"
              :state="currentPassword.length > 0 ? true : null"
              autocomplete="current-password"
            />
          </BCol>
        </BRow>
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model="newPassword" 
              type="password"
              placeholder="New Password"
              :state="newPasswordState"
              autocomplete="new-password"
            />
            <BFormInvalidFeedback :state="newPasswordState">
              {{ passwordValidationMessage }}
            </BFormInvalidFeedback>
            <small class="text-muted d-block text-start mt-1">
              8+ chars with uppercase, lowercase, number &amp; symbol, OR 18+ chars passphrase
            </small>
          </BCol>
        </BRow>
        <BRow class="pb-2">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model="confirmPassword" 
              type="password"
              placeholder="Confirm New Password"
              :state="confirmPasswordState"
              autocomplete="new-password"
            />
            <BFormInvalidFeedback :state="confirmPasswordState">
              Passwords do not match
            </BFormInvalidFeedback>
          </BCol>
        </BRow>
        <BRow v-if="error" class="pb-1">
          <BCol md="12" class="p-0">
            <BAlert variant="danger" show class="mb-0 py-2">{{ error }}</BAlert>
          </BCol>
        </BRow>
        <BRow v-if="loading" class="pb-1">
          <BCol md="12" class="p-0 text-center">
            <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Changing password...
          </BCol>
        </BRow>
      </BContainer>
    </form>
  </BModal>
</template>

<script>
import { ref, computed, inject } from 'vue'
import axios from 'axios'

const MIN_PASSWORD_LENGTH = 8
const PASSPHRASE_LENGTH = 18

// Validate password complexity
function validatePassword(password) {
  if (!password) {
    return { valid: false, message: 'Password is required' }
  }
  
  const length = password.length
  
  // Long passphrase is always valid
  if (length >= PASSPHRASE_LENGTH) {
    return { valid: true, message: '' }
  }
  
  // Short passwords need complexity
  if (length < MIN_PASSWORD_LENGTH) {
    return { 
      valid: false, 
      message: `Password must be at least ${MIN_PASSWORD_LENGTH} characters with complexity, or ${PASSPHRASE_LENGTH}+ characters as a passphrase`
    }
  }
  
  const hasUpper = /[A-Z]/.test(password)
  const hasLower = /[a-z]/.test(password)
  const hasNumber = /[0-9]/.test(password)
  const hasSymbol = /[^A-Za-z0-9]/.test(password)
  
  if (!hasUpper || !hasLower || !hasNumber || !hasSymbol) {
    const missing = []
    if (!hasUpper) missing.push('uppercase')
    if (!hasLower) missing.push('lowercase')
    if (!hasNumber) missing.push('number')
    if (!hasSymbol) missing.push('symbol')
    
    return {
      valid: false,
      message: `Missing: ${missing.join(', ')}. Or use ${PASSPHRASE_LENGTH}+ chars passphrase.`
    }
  }
  
  return { valid: true, message: '' }
}

export default {
  name: 'PasswordChange',
  emits: ['show-info', 'password-changed'],
  setup(props, { emit, expose }) {
    const isVisible = ref(false)
    const currentPassword = ref('')
    const newPassword = ref('')
    const confirmPassword = ref('')
    const error = ref('')
    const loading = ref(false)
    
    // Get current user for username field (accessibility)
    const currentUser = inject('currentUser', ref(null))
    const username = computed(() => currentUser.value?.username || '')

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      currentPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
      error.value = ''
      loading.value = false
    }

    const onHidden = () => {
      currentPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
      error.value = ''
    }

    const passwordValidation = computed(() => validatePassword(newPassword.value))
    
    const passwordValidationMessage = computed(() => passwordValidation.value.message)

    const newPasswordState = computed(() => {
      if (newPassword.value.length === 0) return null
      return passwordValidation.value.valid
    })

    const confirmPasswordState = computed(() => {
      if (confirmPassword.value.length === 0) return null
      return confirmPassword.value === newPassword.value
    })

    const isFormValid = computed(() => {
      return currentPassword.value.length > 0 &&
             passwordValidation.value.valid &&
             confirmPassword.value === newPassword.value
    })

    const handleSubmit = async (event) => {
      event.preventDefault()
      
      if (!isFormValid.value) {
        return
      }

      error.value = ''
      loading.value = true

      try {
        const response = await axios.post('/rpi_admin/auth.php?action=change_password', {
          current_password: currentPassword.value,
          new_password: newPassword.value
        })

        if (response.data.status === 'success') {
          hide()
          emit('password-changed')
          emit('show-info', 'Password changed successfully', 3)
        } else {
          error.value = response.data.message || 'Failed to change password'
        }
      } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
          error.value = err.response.data.message
        } else {
          error.value = 'An error occurred while changing password'
        }
      } finally {
        loading.value = false
      }
    }

    expose({ show, hide })

    return {
      isVisible,
      currentPassword,
      newPassword,
      confirmPassword,
      error,
      loading,
      username,
      passwordValidationMessage,
      newPasswordState,
      confirmPasswordState,
      isFormValid,
      show,
      hide,
      onShow,
      onHidden,
      handleSubmit
    }
  }
}
</script>
