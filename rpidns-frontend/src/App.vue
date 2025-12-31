<template>
  <!-- Loading State -->
  <div v-if="authLoading" class="auth-loading d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center">
      <BSpinner variant="light" style="width: 3rem; height: 3rem;" />
      <p class="text-white mt-3">Loading...</p>
    </div>
  </div>

  <!-- Login Page -->
  <LoginPage 
    v-else-if="!isAuthenticated" 
    @login-success="handleLoginSuccess"
  />

  <!-- Main Application -->
  <div v-else id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
    <!-- Header -->
    <div class="menu-bkgr white ps-4 pt-2 d-flex justify-content-between align-items-center">
      <div>
        <span style="font-size: 32px">RpiDNS</span> powered by 
        <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a>
      </div>
      <div class="pe-3 d-flex align-items-center">
        <BDropdown 
          v-if="currentUser"
          variant="link" 
          toggle-class="text-white text-decoration-none p-0 me-3"
          no-caret
          end
        >
          <template #button-content>
            <i class="fas fa-user me-1"></i>{{ currentUser.username }}
            <i class="fas fa-caret-down ms-1"></i>
          </template>
          <BDropdownItem @click="showPasswordChangeModal">
            <i class="fas fa-key me-2"></i>Change Password
          </BDropdownItem>
          <BDropdownDivider />
          <BDropdownItem @click="handleLogout" :disabled="loggingOut">
            <BSpinner v-if="loggingOut" small class="me-2" />
            <i v-else class="fas fa-sign-out-alt me-2"></i>Logout
          </BDropdownItem>
        </BDropdown>
      </div>
    </div>

    <!-- Main Container with Tabs -->
    <BContainer fluid class="flex-grow-1 p-0 d-flex h-100">
 
      <BTabs 
        ref="i2r" 
        pills
        justified
        :vertical="windowInnerWidth > 500" 
        lazy 
        :nav-wrapper-class="navWrapperClass" 
        class="flex-grow-1 corners position-relative" 
        content-class="curl_angels flex-grow-1 overflow-auto h-100 position-relative" 
        :index="cfgTab"
        @update:index="onTabChange"
        :nav-class="navClass"
        nav-item-class="text-start"
      >

      <!-- Menu Toggle Icons -->
         <i 
            v-cloak 
            class="fa fa-angle-double-left border rounded-end border-dark bg-light" 
            style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" 
            :class="{ hidden: (toggleMenu == 2 && windowInnerWidth >= 992) || (toggleMenu == 1 && windowInnerWidth < 992) }" 
            @click="collapseMenu"
          ></i>
          <i 
            v-cloak 
            class="fa fa-angle-double-right border rounded-end border-dark bg-light" 
            style="position: absolute;left: -2px;top: 10px;z-index: 1; cursor: pointer;" 
            :class="{ hidden: (toggleMenu != 2 && windowInnerWidth >= 992) || (toggleMenu != 1 && windowInnerWidth < 992) }" 
            @click="expandMenu"
          ></i>

      <!-- Dashboard Tab -->
        <BTab class="scroll_tab">
          <template #title>
            <i class="fa fa-tachometer-alt"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Dashboard</span>
          </template>
          <Dashboard 
            @navigate="handleNavigate"
            @add-ioc="handleAddIOC"
          />
        </BTab>

        <!-- Query Log Tab -->
        <BTab @click="refreshQueryLog" lazy>
          <template #title>
            <i class="fas fa-shoe-prints"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Query log</span>
          </template>
          <QueryLog 
            ref="queryLog"
            :filter="qlogs_Filter"
            :period="qlogs_period"
            :logs_height="logs_height"
            @add-ioc="handleAddIOC"
          />
        </BTab>

        <!-- RPZ Hits Tab -->
        <BTab @click="refreshRpzHits" lazy>
          <template #title>
            <i class="fa fa-shield-alt"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;RPZ hits</span>
          </template>
          <RpzHits 
            ref="rpzHits"
            :filter="hits_Filter"
            :period="hits_period"
            :logs_height="logs_height"
            @add-ioc="handleAddIOC"
          />
        </BTab>

        <!-- Admin Tab -->
        <BTab lazy>
          <template #title>
            <i class="fas fa-screwdriver"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Admin</span>
          </template>
          <AdminTabs 
            :logs_height="logs_height"
            @navigate="handleNavigate"
            @add-asset="handleAddAsset"
            @delete-asset="handleDeleteAsset"
            @add-ioc="handleAddIOC"
            @delete-ioc="handleDeleteIOC"
            @show-info="showInfo"
            @open-import-modal="handleOpenImportModal"
          />
        </BTab>

        <!-- Help Tab -->

<!--
         <BTab lazy>
          <template #title>
            <i class="fas fa-hands-helping"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Help</span>
          </template>
          <div class="p-3">
            <BCard>
              <template #header>
                <span class="bold"><i class="fas fa-hands-helping"></i>&nbsp;&nbsp;Help</span>
              </template>
              <p>Help content</p>
            </BCard>
          </div>
        </BTab>
 -->

      </BTabs>
    </BContainer>

    <!-- Copyright Footer -->
    <div class="copyright">
      <p>Copyright © 2020-2026 Vadim Pavlov</p>
    </div>
  </div>

  <!-- Modal Dialogs (outside main app div for proper rendering) -->
  <template v-if="isAuthenticated">
    <PasswordChange
      ref="passwordChangeModal"
      @show-info="showInfo"
      @password-changed="onPasswordChanged"
    />

    <AddAsset
      ref="addAssetModal"
      :address="addAssetAddr"
      :name="addAssetName"
      :vendor="addAssetVendor"
      :comment="addAssetComment"
      :rowid="addAssetRowID"
      :assets-by="assets_by"
      @show-info="showInfo"
      @refresh-table="refreshAssetsTable"
    />

    <AddIOC
      ref="addIOCModal"
      :ioc="addIOC"
      :ioc-type="addIOCtype"
      :comment="addIOCcomment"
      :active="addIOCactive"
      :subdomains="addIOCsubd"
      :rowid="addBLRowID"
      @show-info="showInfo"
      @refresh-table="refreshIOCTable"
    />

    <ImportDB
      ref="importDB"
      :import-types="db_import_type"
      @show-info="showInfo"
    />

    <!-- Confirmation Modal -->
    <BModal
      v-model="confirmModalVisible"
      :title="confirmModalTitle"
      centered
      @ok="onConfirmOk"
    >
      <p class="text-center">{{ confirmModalMessage }}</p>
    </BModal>

    <!-- Info Message Modal -->
    <BModal
      v-model="infoModalVisible"
      :size="infoModalSize"
      ok-only
      ok-variant="success"
      centered
      header-class="p-2 border-bottom-0"
      footer-class="p-2 border-top-0"
      body-class="fw-bold text-center"
    >
      {{ infoModalMessage }}
    </BModal>
  </template>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, provide } from 'vue'
import Dashboard from './components/Dashboard.vue'
import QueryLog from './components/QueryLog.vue'
import RpzHits from './components/RpzHits.vue'
import AdminTabs from './components/Admin/AdminTabs.vue'
import AddAsset from './components/modals/AddAsset.vue'
import AddIOC from './components/modals/AddIOC.vue'
import ImportDB from './components/modals/ImportDB.vue'
import LoginPage from './components/LoginPage.vue'
import PasswordChange from './components/modals/PasswordChange.vue'

export default {
  name: 'App',
  components: {
    Dashboard,
    QueryLog,
    RpzHits,
    AdminTabs,
    AddAsset,
    AddIOC,
    ImportDB,
    LoginPage,
    PasswordChange
  },
  setup() {
    // Refs for child components
    const queryLog = ref(null)
    const rpzHits = ref(null)
    const addAssetModal = ref(null)
    const addIOCModal = ref(null)
    const importDB = ref(null)
    const passwordChangeModal = ref(null)
    const i2r = ref(null)

    // Authentication State
    const authLoading = ref(true)
    const isAuthenticated = ref(false)
    const currentUser = ref(null)
    const loggingOut = ref(false)
    
    // Computed property for admin status
    const isAdmin = computed(() => {
      return currentUser.value?.is_admin === true
    })
    
    // Provide auth state to child components
    provide('currentUser', currentUser)
    provide('isAdmin', isAdmin)
    provide('isAuthenticated', isAuthenticated)

    // UI State
    const toggleMenu = ref(0)
    const cfgTab = ref(0)
    const windowInnerWidth = ref(800)
    const logs_height = ref(150)

    // Computed classes for nav
    const navWrapperClass = computed(() => ({
      'menu-bkgr': true, 
      'text-align-start': true,
      'h-100': windowInnerWidth.value > 500, 
      'p-1': windowInnerWidth.value > 500,
      'mnw165': (windowInnerWidth.value > 500 && toggleMenu.value == 0) ,
    }))

    const navClass = computed(() => ({ 
      hidden: (toggleMenu.value == 2 && windowInnerWidth.value >= 992) || 
              (toggleMenu.value == 1 && windowInnerWidth.value < 992),
      'me-0': true 
    }))

    const shouldHideCollapseIcon = computed(() => 
      (toggleMenu.value == 2 && windowInnerWidth.value >= 992) || 
      (toggleMenu.value == 1 && windowInnerWidth.value < 992) ||
      windowInnerWidth.value <= 500
    )

    const shouldHideExpandIcon = computed(() => 
      (toggleMenu.value != 2 && windowInnerWidth.value >= 992) || 
      (toggleMenu.value != 1 && windowInnerWidth.value < 992) ||
      windowInnerWidth.value <= 500
    )

    // Query Logs state
    const qlogs_Filter = ref('')
    const qlogs_period = ref('30m')

    // RPZ Hits state
    const hits_Filter = ref('')
    const hits_period = ref('30m')

    // IOC Modal state
    const addIOC = ref('')
    const addIOCtype = ref('')
    const addIOCcomment = ref('')
    const addIOCactive = ref(true)
    const addIOCsubd = ref(true)
    const addBLRowID = ref(0)

    // Asset Modal state
    const addAssetAddr = ref('')
    const addAssetName = ref('')
    const addAssetVendor = ref('')
    const addAssetComment = ref('')
    const addAssetRowID = ref(0)

    // Import DB Modal state
    const db_import_type = ref([])

    // Settings state
    const assets_by = ref('mac')

    // Confirmation modal state
    const confirmModalVisible = ref(false)
    const confirmModalTitle = ref('')
    const confirmModalMessage = ref('')
    const confirmModalCallback = ref(null)

    // Info modal state
    const infoModalVisible = ref(false)
    const infoModalMessage = ref('')
    const infoModalSize = ref('sm')
    let infoModalTimeout = null

    // Methods
    const updateWindowSize = () => {
      logs_height.value = window.innerHeight > 400 ? (window.innerHeight - 240) : 150
      windowInnerWidth.value = window.innerWidth
    }

    const changeTab = (tab) => {
      history.pushState(null, null, '#i2r/' + tab)
    }

    const onTabChange = (tab) => {
      cfgTab.value = tab
      changeTab(tab)
    }

    const collapseMenu = () => {
      toggleMenu.value += 1
      updateWindowSize()
      window.localStorage.setItem('toggleMenu', toggleMenu.value)
    }

    const expandMenu = () => {
      toggleMenu.value = 0
      updateWindowSize()
      window.localStorage.setItem('toggleMenu', toggleMenu.value)
    }

    const handleNavigate = (data) => {
      // Set filter and period first
      if (data.type === 'qlogs') {
        qlogs_Filter.value = data.filter
        qlogs_period.value = data.period
      } else if (data.type === 'hits') {
        hits_Filter.value = data.filter
        hits_period.value = data.period
      }
      // Change tab - use direct assignment
      cfgTab.value = data.tab
      // Also update URL hash
      history.pushState(null, null, '#i2r/' + data.tab)
    }

    const handleAddIOC = (data) => {
      addIOC.value = data.ioc
      addIOCtype.value = data.type
      addIOCcomment.value = data.comment !== undefined ? data.comment : ''
      addBLRowID.value = data.rowid !== undefined ? data.rowid : 0
      addIOCactive.value = data.active !== undefined ? data.active : true
      addIOCsubd.value = data.subdomains !== undefined ? data.subdomains : true
      nextTick(() => {
        if (addIOCModal.value) {
          addIOCModal.value.show()
        }
      })
    }

    const handleDeleteIOC = (data) => {
      confirmModalTitle.value = 'Please confirm the action'
      confirmModalMessage.value = 'You are about to delete the selected entry. This action is irreversible!'
      confirmModalCallback.value = () => deleteIOC(data.ioc, data.table)
      confirmModalVisible.value = true
    }

    const deleteIOC = async (ioc, table) => {
      try {
        const response = await fetch(`/rpi_admin/rpidata.php?req=${table}&id=${ioc.rowid}`, {
          method: 'DELETE'
        })
        const result = await response.json()

        if (result.status === 'success') {
          window.dispatchEvent(new CustomEvent('refresh-table', { detail: { table } }))
        } else {
          showInfo(result.reason, 3)
        }
      } catch (error) {
        showInfo('Unknown error!!!', 3)
      }
    }

    const refreshQueryLog = () => {
      if (queryLog.value) {
        queryLog.value.refreshTable()
      }
    }

    const refreshRpzHits = () => {
      if (rpzHits.value) {
        rpzHits.value.refreshTable()
      }
    }

    const handleAddAsset = (data) => {
      addAssetAddr.value = data.address
      addAssetName.value = data.name
      addAssetVendor.value = data.vendor
      addAssetComment.value = data.comment
      addAssetRowID.value = data.rowid
      nextTick(() => {
        if (addAssetModal.value) {
          addAssetModal.value.show()
        }
      })
    }

    const handleDeleteAsset = (data) => {
      confirmModalTitle.value = 'Please confirm the action'
      confirmModalMessage.value = 'You are about to delete the selected asset. This action is irreversible!'
      confirmModalCallback.value = () => deleteAsset(data.asset, data.table)
      confirmModalVisible.value = true
    }

    const deleteAsset = async (asset, table) => {
      try {
        const response = await fetch(`/rpi_admin/rpidata.php?req=${table}&id=${asset.rowid}`, {
          method: 'DELETE'
        })
        const result = await response.json()

        if (result.status === 'success') {
          window.dispatchEvent(new CustomEvent('refresh-table', { detail: { table } }))
        } else {
          showInfo(result.reason, 3)
        }
      } catch (error) {
        showInfo('Unknown error!!!', 3)
      }
    }

    const showInfo = (msg, time) => {
      // Handle undefined/null messages
      const message = msg || ''
      const duration = time || 3
      
      infoModalSize.value = message.length > 30 ? 'md' : 'sm'
      infoModalMessage.value = message
      infoModalVisible.value = true

      if (infoModalTimeout) {
        clearTimeout(infoModalTimeout)
      }
      infoModalTimeout = setTimeout(() => {
        infoModalVisible.value = false
      }, duration * 1000)
    }

    const handleOpenImportModal = (data) => {
      db_import_type.value = data.db_import_type || []
      nextTick(() => {
        if (importDB.value) {
          importDB.value.show()
        }
      })
    }

    const getSettings = async () => {
      try {
        const response = await fetch('/rpi_admin/rpidata.php?req=RPIsettings')
        const data = await response.json()
        assets_by.value = data.assets_by || 'mac'
      } catch (error) {
        console.error('Error fetching settings:', error)
      }
    }

    const onConfirmOk = () => {
      if (confirmModalCallback.value) {
        confirmModalCallback.value()
        confirmModalCallback.value = null
      }
    }

    const refreshAssetsTable = () => {
      window.dispatchEvent(new CustomEvent('refresh-table', { detail: { table: 'assets' } }))
    }

    const refreshIOCTable = (tableName) => {
      window.dispatchEvent(new CustomEvent('refresh-table', { detail: { table: tableName } }))
    }

    // Authentication Methods
    const checkSession = async () => {
      try {
        const response = await fetch('/rpi_admin/auth.php?action=verify', {
          method: 'GET',
          credentials: 'same-origin'
        })

        const data = await response.json()

        if (response.ok && data.status === 'success' && data.authenticated) {
          isAuthenticated.value = true
          currentUser.value = data.user
        } else {
          isAuthenticated.value = false
          currentUser.value = null
        }
      } catch (error) {
        console.error('Session check error:', error)
        isAuthenticated.value = false
        currentUser.value = null
      } finally {
        authLoading.value = false
      }
    }

    const handleLoginSuccess = (user) => {
      isAuthenticated.value = true
      currentUser.value = user
    }

    const handleLogout = async () => {
      loggingOut.value = true

      try {
        await fetch('/rpi_admin/auth.php?action=logout', {
          method: 'POST',
          credentials: 'same-origin'
        })
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        isAuthenticated.value = false
        currentUser.value = null
        loggingOut.value = false
        // Reset tab to dashboard
        cfgTab.value = 0
      }
    }

    // Handle session expiration from API calls
    const handleSessionExpired = () => {
      isAuthenticated.value = false
      currentUser.value = null
      cfgTab.value = 0
    }

    // Password change methods
    const showPasswordChangeModal = () => {
      nextTick(() => {
        if (passwordChangeModal.value) {
          passwordChangeModal.value.show()
        }
      })
    }

    const onPasswordChanged = () => {
      // Password was changed successfully - could add additional logic here if needed
    }

    // Lifecycle hooks
    onMounted(() => {
      // Check session first
      checkSession()

      updateWindowSize()
      nextTick(() => {
        window.addEventListener('resize', updateWindowSize)
        
        // Listen for session expiration events from API calls
        window.addEventListener('session-expired', handleSessionExpired)

        // Restore menu state from localStorage
        if (window.localStorage.getItem('toggleMenu')) {
          toggleMenu.value = parseInt(window.localStorage.getItem('toggleMenu'))
        }

        // Handle URL hash for tab navigation
        if (window.location.hash) {
          const parts = window.location.hash.split(/#|\//).filter(String)
          if (parts[0] === 'i2r') {
            cfgTab.value = parseInt(parts[1])
          }
          if (parts[2] === 'hidemenu') {
            toggleMenu.value = 2
          }
        }

        // Fetch settings including assets_by
        getSettings()
      })
    })

    onBeforeUnmount(() => {
      window.removeEventListener('resize', updateWindowSize)
      window.removeEventListener('session-expired', handleSessionExpired)
      if (infoModalTimeout) {
        clearTimeout(infoModalTimeout)
      }
    })

    return {
      // Refs
      queryLog,
      rpzHits,
      addAssetModal,
      addIOCModal,
      importDB,
      passwordChangeModal,
      i2r,
      // Authentication State
      authLoading,
      isAuthenticated,
      currentUser,
      loggingOut,
      isAdmin,
      // State
      toggleMenu,
      cfgTab,
      windowInnerWidth,
      logs_height,
      navWrapperClass,
      navClass,
      shouldHideCollapseIcon,
      shouldHideExpandIcon,
      qlogs_Filter,
      qlogs_period,
      hits_Filter,
      hits_period,
      addIOC,
      addIOCtype,
      addIOCcomment,
      addIOCactive,
      addIOCsubd,
      addBLRowID,
      addAssetAddr,
      addAssetName,
      addAssetVendor,
      addAssetComment,
      addAssetRowID,
      db_import_type,
      assets_by,
      confirmModalVisible,
      confirmModalTitle,
      confirmModalMessage,
      infoModalVisible,
      infoModalMessage,
      infoModalSize,
      // Methods
      updateWindowSize,
      changeTab,
      onTabChange,
      collapseMenu,
      expandMenu,
      handleNavigate,
      handleAddIOC,
      handleDeleteIOC,
      refreshQueryLog,
      refreshRpzHits,
      handleAddAsset,
      handleDeleteAsset,
      showInfo,
      handleOpenImportModal,
      onConfirmOk,
      refreshAssetsTable,
      refreshIOCTable,
      // Authentication Methods
      checkSession,
      handleLoginSuccess,
      handleLogout,
      handleSessionExpired,
      showPasswordChangeModal,
      onPasswordChanged
    }
  }
}
</script>

<style scoped>
.placeholder-content {
  padding: 1rem;
}
.mnw165 { min-width: 165px; }
.auth-loading {
  background-color: #343038;
  min-height: 100vh;
}
</style>


<style>
.mnw165 { min-width: 165px; }
</style>