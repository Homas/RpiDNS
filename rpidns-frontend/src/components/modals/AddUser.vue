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
            placeholder="Password (min 8 characters)"
            :state="passwordState"
            autocomplete="new-password"
          />
          <BFormInvalidFeedback :state="passwordState">
            Password must be at least 8 characters
          </BFormInvalidFeedback>
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

    const passwordState = computed(() => {
      if (password.value.length === 0) return null
      return password.value.length >= MIN_PASSWORD_LENGTH
    })

    const confirmPasswordState = computed(() => {
      if (confirmPassword.value.length === 0) return null
      return confirmPassword.value === password.value
    })

    const isFormValid = computed(() => {
      return username.value.length > 0 &&
             password.value.length >= MIN_PASSWORD_LENGTH &&
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
