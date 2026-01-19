<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="login-container d-flex align-items-center justify-content-center min-vh-100">
    <div class="login-card">
      <div class="login-header text-center mb-4">
        <span class="login-title">RpiDNS</span>
        <p class="login-subtitle">powered by <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a></p>
      </div>
      
      <BCard class="shadow-sm">
        <BForm @submit.prevent="handleLogin">
          <BFormGroup label="Username" label-for="username" class="mb-3">
            <BFormInput
              id="username"
              v-model.trim="username"
              type="text"
              placeholder="Enter username"
              required
              :disabled="loading"
              :state="usernameState"
              autocomplete="username"
            />
            <BFormInvalidFeedback v-if="usernameState === false">
              Username is required
            </BFormInvalidFeedback>
          </BFormGroup>

          <BFormGroup label="Password" label-for="password" class="mb-3">
            <BFormInput
              id="password"
              v-model="password"
              type="password"
              placeholder="Enter password"
              required
              :disabled="loading"
              :state="passwordState"
              autocomplete="current-password"
            />
            <BFormInvalidFeedback v-if="passwordState === false">
              Password is required
            </BFormInvalidFeedback>
          </BFormGroup>

          <BAlert 
            v-model="showError" 
            variant="danger" 
            dismissible 
            class="mb-3"
          >
            {{ errorMessage }}
          </BAlert>

          <BAlert 
            v-model="showLogoutMessage" 
            variant="success" 
            dismissible 
            class="mb-3"
          >
            You have been logged out successfully.
          </BAlert>

          <BButton
            type="submit"
            variant="primary"
            class="w-100"
            :disabled="loading || !isFormValid"
          >
            <BSpinner v-if="loading" small class="me-2" />
            {{ loading ? 'Signing in...' : 'Sign In' }}
          </BButton>
        </BForm>
      </BCard>

      <div class="login-footer text-center mt-3">
        <small class="text-muted">Copyright © 2020-2026 Vadim Pavlov</small>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue'

export default {
  name: 'LoginPage',
  emits: ['login-success'],
  setup(props, { emit }) {
    const username = ref('')
    const password = ref('')
    const loading = ref(false)
    const errorMessage = ref('')
    const showError = ref(false)
    const showLogoutMessage = ref(false)
    const formSubmitted = ref(false)

    // Form validation states
    const usernameState = computed(() => {
      if (!formSubmitted.value) return null
      return username.value.length > 0 ? null : false
    })

    const passwordState = computed(() => {
      if (!formSubmitted.value) return null
      return password.value.length > 0 ? null : false
    })

    const isFormValid = computed(() => {
      return username.value.length > 0 && password.value.length > 0
    })

    const handleLogin = async () => {
      formSubmitted.value = true
      showError.value = false
      showLogoutMessage.value = false

      if (!isFormValid.value) {
        return
      }

      loading.value = true

      try {
        const response = await fetch('/rpi_admin/auth.php?action=login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            username: username.value,
            password: password.value
          }),
          credentials: 'same-origin'
        })

        const data = await response.json()

        if (response.status === 429) {
          // Rate limited
          errorMessage.value = data.message || 'Too many attempts. Try again later.'
          showError.value = true
        } else if (!response.ok || data.status === 'error') {
          // Authentication failed
          errorMessage.value = data.message || 'Invalid username or password'
          showError.value = true
        } else {
          // Success - emit event with user data
          emit('login-success', data.user)
        }
      } catch (error) {
        console.error('Login error:', error)
        errorMessage.value = 'Unable to connect to server. Please try again.'
        showError.value = true
      } finally {
        loading.value = false
      }
    }

    // Show logout message if redirected from logout
    const checkLogoutMessage = () => {
      const urlParams = new URLSearchParams(window.location.search)
      if (urlParams.get('logout') === 'success') {
        showLogoutMessage.value = true
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname)
      }
    }

    // Check on component mount
    checkLogoutMessage()

    return {
      username,
      password,
      loading,
      errorMessage,
      showError,
      showLogoutMessage,
      formSubmitted,
      usernameState,
      passwordState,
      isFormValid,
      handleLogin
    }
  }
}
</script>

<style scoped>
.login-container {
  background-color: #343038;
  min-height: 100vh;
}

.login-card {
  width: 100%;
  max-width: 400px;
  padding: 1rem;
}

.login-header {
  color: white;
}

.login-title {
  font-size: 2.5rem;
  font-weight: bold;
}

.login-subtitle {
  color: #aaa;
  margin-top: 0.5rem;
}

.login-subtitle a {
  color: #6ea8fe;
  text-decoration: none;
}

.login-subtitle a:hover {
  text-decoration: underline;
}

.login-footer {
  color: #888;
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .login-card {
    padding: 0.5rem;
  }
  
  .login-title {
    font-size: 2rem;
  }
}
</style>
