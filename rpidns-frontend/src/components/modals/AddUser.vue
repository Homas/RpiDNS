<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Add User" 
    id="mAddUser" 
    body-class="pt-0 pb-0" 
    ok-title="Create User" 
    :ok-disabled="!isFormValid || loading"
    @ok="handleSubmit"
    @show="onShow"
    @hidden="onHidden"
  >
    <BContainer fluid>
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <BFormInput 
            v-model.trim="username" 
            placeholder="Username"
            :state="usernameState"
            autocomplete="off"
          />
          <BFormInvalidFeedback :state="usernameState">
            Username is required
          </BFormInvalidFeedback>
        </BCol>
      </BRow>
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <BFormInput 
            v-model="password" 
            type="password"
            placeholder="Password"
            :state="passwordState"
            autocomplete="new-password"
          />
          <BFormInvalidFeedback :state="passwordState">
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
            placeholder="Confirm Password"
            :state="confirmPasswordState"
            autocomplete="new-password"
          />
          <BFormInvalidFeedback :state="confirmPasswordState">
            Passwords do not match
          </BFormInvalidFeedback>
        </BCol>
      </BRow>
      <BRow class="pb-2">
        <BCol md="12" class="p-0">
          <BFormCheckbox v-model="isAdmin">
            Administrator privileges
          </BFormCheckbox>
        </BCol>
      </BRow>
      <BRow v-if="error" class="pb-1">
        <BCol md="12" class="p-0">
          <BAlert variant="danger" show class="mb-0 py-2">{{ error }}</BAlert>
        </BCol>
      </BRow>
      <BRow v-if="loading" class="pb-1">
        <BCol md="12" class="p-0 text-center">
          <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Creating user...
        </BCol>
      </BRow>
    </BContainer>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
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
  name: 'AddUser',
  emits: ['user-created', 'show-info'],
  setup(props, { emit, expose }) {
    const isVisible = ref(false)
    const username = ref('')
    const password = ref('')
    const confirmPassword = ref('')
    const isAdmin = ref(false)
    const error = ref('')
    const loading = ref(false)

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      username.value = ''
      password.value = ''
      confirmPassword.value = ''
      isAdmin.value = false
      error.value = ''
      loading.value = false
    }

    const onHidden = () => {
      username.value = ''
      password.value = ''
      confirmPassword.value = ''
      isAdmin.value = false
      error.value = ''
    }

    const usernameState = computed(() => {
      if (username.value.length === 0) return null
      return username.value.length > 0
    })

    const passwordValidation = computed(() => validatePassword(password.value))
    
    const passwordValidationMessage = computed(() => passwordValidation.value.message)

    const passwordState = computed(() => {
      if (password.value.length === 0) return null
      return passwordValidation.value.valid
    })

    const confirmPasswordState = computed(() => {
      if (confirmPassword.value.length === 0) return null
      return confirmPassword.value === password.value
    })

    const isFormValid = computed(() => {
      return username.value.length > 0 &&
             passwordValidation.value.valid &&
             confirmPassword.value === password.value
    })

    const handleSubmit = async (event) => {
      event.preventDefault()
      
      if (!isFormValid.value) {
        return
      }

      error.value = ''
      loading.value = true

      try {
        const response = await axios.post('/rpi_admin/auth.php?action=create_user', {
          username: username.value,
          password: password.value,
          is_admin: isAdmin.value
        })

        if (response.data.status === 'success') {
          hide()
          emit('user-created')
          emit('show-info', 'User created successfully', 3)
        } else {
          error.value = response.data.message || 'Failed to create user'
        }
      } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
          error.value = err.response.data.message
        } else {
          error.value = 'An error occurred while creating user'
        }
      } finally {
        loading.value = false
      }
    }

    expose({ show, hide })

    return {
      isVisible,
      username,
      password,
      confirmPassword,
      isAdmin,
      error,
      loading,
      usernameState,
      passwordValidationMessage,
      passwordState,
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
