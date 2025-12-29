<template>
  <div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
    <!-- Header -->
    <div class="menu-bkgr white ps-4 pt-2">
      <span style="font-size: 32px">RpiDNS</span> powered by 
      <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a>
    </div>

    <!-- Main Container with Tabs -->
    <BContainer fluid class="flex-grow-1 p-0 d-flex overflow-hidden">
      <!-- Nav wrapper with toggle button -->
      <div v-if="windowInnerWidth > 500" class="menu-bkgr d-flex flex-column nav-sidebar">
        <!-- Tab navigation -->
        <ul class="nav nav-pills flex-column p-1" :class="{ hidden: isMenuCollapsed }">
          <li class="nav-item" v-for="(tab, index) in tabs" :key="index">
            <a 
              class="nav-link" 
              :class="{ active: cfgTab === index }" 
              href="#" 
              @click.prevent="selectTab(index)"
            >
              <i :class="tab.icon"></i>
              <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;{{ tab.title }}</span>
            </a>
          </li>
        </ul>
        <!-- Toggle button at bottom -->
        <div class="mt-auto p-2">
          <i 
            v-cloak 
            class="fa fa-angle-double-left border rounded border-secondary text-light menu-toggle-btn" 
            :class="{ hidden: isMenuCollapsed }" 
            @click="collapseMenu"
          ></i>
          <i 
            v-cloak 
            class="fa fa-angle-double-right border rounded border-secondary text-light menu-toggle-btn" 
            :class="{ hidden: !isMenuCollapsed }" 
            @click="expandMenu"
          ></i>
        </div>
      </div>

      <!-- Tab content -->
      <div class="flex-grow-1 corners curl_angels overflow-auto">
        <!-- Dashboard Tab -->
        <div v-if="cfgTab === 0" class="scroll_tab h-100">
          <Dashboard 
            @navigate="handleNavigate"
            @add-ioc="handleAddIOC"
          />
        </div>

        <!-- Query Log Tab -->
        <div v-else-if="cfgTab === 1" class="scroll_tab h-100">
          <QueryLog 
            ref="queryLog"
            :filter="qlogs_Filter"
            :period="qlogs_period"
            :logs_height="logs_height"
            @add-ioc="handleAddIOC"
          />
        </div>

        <!-- RPZ Hits Tab -->
        <div v-else-if="cfgTab === 2" class="scroll_tab h-100">
          <RpzHits 
            ref="rpzHits"
            :filter="hits_Filter"
            :period="hits_period"
            :logs_height="logs_height"
            @add-ioc="handleAddIOC"
          />
        </div>

        <!-- Admin Tab -->
        <div v-else-if="cfgTab === 3" class="scroll_tab h-100">
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
        </div>

        <!-- Help Tab -->
        <div v-else-if="cfgTab === 4" class="scroll_tab h-100">
          <div class="p-3 h-100">
            <BCard class="h-100">
              <template #header>
                <span class="bold"><i class="fas fa-hands-helping"></i>&nbsp;&nbsp;Help</span>
              </template>
              <p>Help content</p>
            </BCard>
          </div>
        </div>
      </div>

      <!-- Horizontal tabs for mobile -->
      <BTabs 
        v-if="windowInnerWidth <= 500"
        ref="i2r" 
        pills 
        lazy 
        class="flex-grow-1" 
        content-class="curl_angels flex-grow-1 overflow-auto" 
        v-model="cfgTab" 
        @update:model-value="changeTab"
      >
        <BTab v-for="(tab, index) in tabs" :key="index" class="scroll_tab h-100">
          <template #title>
            <i :class="tab.icon"></i>
            <span>&nbsp;&nbsp;{{ tab.title }}</span>
          </template>
        </BTab>
      </BTabs>
    </BContainer>

    <!-- Copyright Footer -->
    <div class="copyright">
      <p>Copyright © 2020-2026 Vadim Pavlov</p>
    </div>

    <!-- Modal Dialogs -->
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
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Dashboard from './components/Dashboard.vue'
import QueryLog from './components/QueryLog.vue'
import RpzHits from './components/RpzHits.vue'
import AdminTabs from './components/Admin/AdminTabs.vue'
import AddAsset from './components/modals/AddAsset.vue'
import AddIOC from './components/modals/AddIOC.vue'
import ImportDB from './components/modals/ImportDB.vue'

export default {
  name: 'App',
  components: {
    Dashboard,
    QueryLog,
    RpzHits,
    AdminTabs,
    AddAsset,
    AddIOC,
    ImportDB
  },
  setup() {
    // Refs for child components
    const queryLog = ref(null)
    const rpzHits = ref(null)
    const addAssetModal = ref(null)
    const addIOCModal = ref(null)
    const importDB = ref(null)
    const i2r = ref(null)

    // UI State
    const toggleMenu = ref(0)
    const cfgTab = ref(0)
    const windowInnerWidth = ref(800)
    const logs_height = ref(150)

    // Tab definitions
    const tabs = [
      { title: 'Dashboard', icon: 'fa fa-tachometer-alt' },
      { title: 'Query log', icon: 'fas fa-shoe-prints' },
      { title: 'RPZ hits', icon: 'fa fa-shield-alt' },
      { title: 'Admin', icon: 'fas fa-screwdriver' },
      { title: 'Help', icon: 'fas fa-hands-helping' }
    ]

    // Computed classes for nav
    const navWrapperClass = computed(() => ({
      'menu-bkgr': true, 
      'h-100': windowInnerWidth.value > 500, 
      'p-1': windowInnerWidth.value > 500
    }))

    const navClass = computed(() => ({ 
      hidden: (toggleMenu.value == 2 && windowInnerWidth.value >= 992) || 
              (toggleMenu.value == 1 && windowInnerWidth.value < 992) 
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

    const isMenuCollapsed = computed(() => 
      (toggleMenu.value == 2 && windowInnerWidth.value >= 992) || 
      (toggleMenu.value == 1 && windowInnerWidth.value < 992)
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

    const selectTab = (index) => {
      cfgTab.value = index
      changeTab(index)
      // Trigger refresh for specific tabs
      if (index === 1) {
        refreshQueryLog()
      } else if (index === 2) {
        refreshRpzHits()
      }
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
      if (data.type === 'qlogs') {
        qlogs_Filter.value = data.filter
        qlogs_period.value = data.period
      } else if (data.type === 'hits') {
        hits_Filter.value = data.filter
        hits_period.value = data.period
      }
      cfgTab.value = data.tab
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
      infoModalSize.value = msg.length > 30 ? 'md' : 'sm'
      infoModalMessage.value = msg
      infoModalVisible.value = true

      if (infoModalTimeout) {
        clearTimeout(infoModalTimeout)
      }
      infoModalTimeout = setTimeout(() => {
        infoModalVisible.value = false
      }, time * 1000)
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

    // Lifecycle hooks
    onMounted(() => {
      updateWindowSize()
      nextTick(() => {
        window.addEventListener('resize', updateWindowSize)

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
      i2r,
      // State
      toggleMenu,
      cfgTab,
      windowInnerWidth,
      logs_height,
      tabs,
      navWrapperClass,
      navClass,
      shouldHideCollapseIcon,
      shouldHideExpandIcon,
      isMenuCollapsed,
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
      selectTab,
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
      refreshIOCTable
    }
  }
}
</script>

<style scoped>
.placeholder-content {
  padding: 1rem;
}

.menu-toggle-btn {
  cursor: pointer;
  padding: 3px 6px;
}

.nav-sidebar {
  min-width: 50px;
}

.nav-sidebar .nav-link {
  color: #0d6efd;
  border-radius: 0.375rem;
  padding: 0.5rem 1rem;
  margin-bottom: 0.25rem;
}

.nav-sidebar .nav-link:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-sidebar .nav-link.active {
  background-color: #0d6efd;
  color: white;
}
</style>
