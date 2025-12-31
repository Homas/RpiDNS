<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">User Management</h4>
      <BButton size="sm" variant="primary" @click="showAddUser">
        <i class="fa fa-plus"></i>&nbsp;Add User
      </BButton>
    </div>

    <BTableSimple striped hover small responsive>
      <BThead>
        <BTr>
          <BTh>Username</BTh>
          <BTh class="text-center">Admin</BTh>
          <BTh class="d-none d-md-table-cell">Created</BTh>
          <BTh class="text-center">Actions</BTh>
        </BTr>
      </BThead>
      <BTbody>
        <BTr v-for="user in users" :key="user.id">
          <BTd>{{ user.username }}</BTd>
          <BTd class="text-center">
            <i v-if="user.is_admin" class="fa fa-check text-success"></i>
            <i v-else class="fa fa-times text-muted"></i>
          </BTd>
          <BTd class="d-none d-md-table-cell">{{ formatDate(user.created_at) }}</BTd>
          <BTd class="text-center">
            <BButton 
              size="sm" 
              variant="outline-warning" 
              class="me-1"
              @click="handleResetPassword(user)"
              v-b-tooltip.hover
              title="Reset Password"
            >
              <i class="fa fa-key"></i>
            </BButton>
            <BButton 
              size="sm" 
              variant="outline-danger"
              @click="handleDeleteUser(user)"
              v-b-tooltip.hover
              title="Delete User"
              :disabled="isLastAdmin(user)"
            >
              <i class="fa fa-trash"></i>
            </BButton>
          </BTd>
        </BTr>
      </BTbody>
    </BTableSimple>

    <div v-if="loading" class="text-center m-3">
      <BSpinner class="align-middle" small></BSpinner>&nbsp;&nbsp;<strong>Loading...</strong>
    </div>

    <div v-if="!loading && users.length === 0" class="text-center text-muted m-3">
      No users found
    </div>

    <!-- Add User Modal -->
    <AddUser 
      ref="addUserModal"
      @user-created="onUserCreated"
      @show-info="handleShowInfo"
    />

    <!-- Reset Password Confirmation Modal -->
    <BModal 
      v-model="resetConfirmModalVisible"
      centered
      title="Confirm Password Reset"
      @ok="confirmResetPassword"
    >
      <p class="text-center">
        Are you sure you want to reset the password for <strong>{{ resetConfirmUsername }}</strong>?
        <br><br>
        <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> This action cannot be undone.</span>
        <br>
        <span class="text-muted small">A new random password will be generated.</span>
      </p>
    </BModal>

    <!-- Reset Password Result Modal -->
    <BModal 
      v-model="resetPasswordModalVisible"
      centered
      title="Password Reset"
      ok-only
      ok-variant="primary"
    >
      <div class="text-center">
        <p>New password for <strong>{{ resetPasswordUsername }}</strong>:</p>
        <div class="bg-light p-3 rounded mb-3">
          <code class="fs-5">{{ resetPasswordValue }}</code>
        </div>
        <p class="text-muted small">
          <i class="fa fa-exclamation-triangle text-warning"></i>
          Please save this password. It will not be shown again.
        </p>
      </div>
    </BModal>

    <!-- Delete Confirmation Modal -->
    <BModal 
      v-model="deleteModalVisible"
      centered
      title="Confirm Delete"
      @ok="confirmDeleteUser"
    >
      <p class="text-center">
        Are you sure you want to delete user <strong>{{ deleteUsername }}</strong>?
        <br><br>
        <span class="text-danger">This action cannot be undone.</span>
      </p>
    </BModal>
  </div>
</template>

<script>
import { ref, onMounted, inject } from 'vue'
import axios from 'axios'
import AddUser from '@/components/modals/AddUser.vue'

export default {
  name: 'UserManager',
  components: {
    AddUser
  },
  emits: ['show-info'],
  setup(props, { emit }) {
    const users = ref([])
    const loading = ref(false)
    const addUserModal = ref(null)
    
    // Reset password modal state
    const resetPasswordModalVisible = ref(false)
    const resetPasswordUsername = ref('')
    const resetPasswordValue = ref('')
    
    // Reset password confirmation modal state
    const resetConfirmModalVisible = ref(false)
    const resetConfirmUserId = ref(null)
    const resetConfirmUsername = ref('')
    
    // Delete confirmation modal state
    const deleteModalVisible = ref(false)
    const deleteUserId = ref(null)
    const deleteUsername = ref('')
    
    // Get admin status from parent
    const isAdmin = inject('isAdmin', ref(false))

    const formatDate = (timestamp) => {
      if (!timestamp) return ''
      const date = new Date(timestamp * 1000)
      return date.toLocaleDateString()
    }

    const isLastAdmin = (user) => {
      if (!user.is_admin) return false
      const adminCount = users.value.filter(u => u.is_admin).length
      return adminCount <= 1
    }

    const loadUsers = async () => {
      loading.value = true
      try {
        const response = await axios.get('/rpi_admin/auth.php?action=users')
        if (response.data.status === 'success') {
          users.value = response.data.users || []
        } else {
          emit('show-info', response.data.message || 'Failed to load users', 3)
        }
      } catch (error) {
        if (error.response?.status === 403) {
          emit('show-info', 'Administrator access required', 3)
        } else {
          emit('show-info', 'Failed to load users', 3)
        }
      } finally {
        loading.value = false
      }
    }

    const showAddUser = () => {
      if (addUserModal.value) {
        addUserModal.value.show()
      }
    }

    const onUserCreated = () => {
      loadUsers()
    }

    const handleResetPassword = (user) => {
      resetConfirmUserId.value = user.id
      resetConfirmUsername.value = user.username
      resetConfirmModalVisible.value = true
    }

    const confirmResetPassword = async () => {
      if (!resetConfirmUserId.value) return
      
      try {
        const response = await axios.post('/rpi_admin/auth.php?action=reset_password', {
          user_id: resetConfirmUserId.value
        })
        
        if (response.data.status === 'success') {
          resetPasswordUsername.value = resetConfirmUsername.value
          resetPasswordValue.value = response.data.new_password
          resetPasswordModalVisible.value = true
        } else {
          emit('show-info', response.data.message || 'Failed to reset password', 3)
        }
      } catch (error) {
        emit('show-info', error.response?.data?.message || 'Failed to reset password', 3)
      } finally {
        resetConfirmUserId.value = null
        resetConfirmUsername.value = ''
      }
    }

    const handleDeleteUser = (user) => {
      if (isLastAdmin(user)) {
        emit('show-info', 'Cannot delete the last administrator', 3)
        return
      }
      deleteUserId.value = user.id
      deleteUsername.value = user.username
      deleteModalVisible.value = true
    }

    const confirmDeleteUser = async () => {
      if (!deleteUserId.value) return
      
      try {
        const response = await axios.post('/rpi_admin/auth.php?action=delete_user', {
          user_id: deleteUserId.value
        })
        
        if (response.data.status === 'success') {
          emit('show-info', 'User deleted successfully', 3)
          loadUsers()
        } else {
          emit('show-info', response.data.message || 'Failed to delete user', 3)
        }
      } catch (error) {
        emit('show-info', error.response?.data?.message || 'Failed to delete user', 3)
      } finally {
        deleteUserId.value = null
        deleteUsername.value = ''
      }
    }

    const handleShowInfo = (msg, time) => {
      emit('show-info', msg, time)
    }

    onMounted(() => {
      loadUsers()
    })

    return {
      users,
      loading,
      addUserModal,
      resetPasswordModalVisible,
      resetPasswordUsername,
      resetPasswordValue,
      resetConfirmModalVisible,
      resetConfirmUserId,
      resetConfirmUsername,
      deleteModalVisible,
      deleteUserId,
      deleteUsername,
      isAdmin,
      formatDate,
      isLastAdmin,
      loadUsers,
      showAddUser,
      onUserCreated,
      handleResetPassword,
      confirmResetPassword,
      handleDeleteUser,
      confirmDeleteUser,
      handleShowInfo
    }
  }
}
</script>

<style scoped>
code {
  user-select: all;
}
</style>
