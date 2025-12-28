<template>
  <div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
    <!-- Header -->
    <div class="menu-bkgr white pl-4 pt-2">
      <span style="font-size: 32px">RpiDNS</span> powered by 
      <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a>
    </div>

    <!-- Main Container with Tabs -->
    <b-container 
      fluid 
      :class="{'h-100': true, 'd-flex': true, 'flex-column': true, 'p-0': windowInnerWidth <= 500}"
    >
      <b-tabs 
        ref="i2r" 
        tabs 
        pills 
        :vertical="windowInnerWidth > 500" 
        lazy 
        :nav-wrapper-class="{'menu-bkgr': true, 'h-100': windowInnerWidth > 500, 'p-1': windowInnerWidth > 500}" 
        class="h-100 corners" 
        content-class="curl_angels" 
        v-model="cfgTab" 
        @input="changeTab"
        :nav-class="{ hidden: (toggleMenu == 2 && windowInnerWidth >= 992) || (toggleMenu == 1 && windowInnerWidth < 992) }"
      >
        <!-- Menu Toggle Icons -->
        <i 
          v-cloak 
          class="fa fa-angle-double-left border rounded-right border-dark" 
          style="position: absolute; left: -2px; top: 10px; z-index: 1; cursor: pointer;" 
          :class="{ hidden: (toggleMenu == 2 && windowInnerWidth >= 992) || (toggleMenu == 1 && windowInnerWidth < 992) }" 
          @click="collapseMenu"
        ></i>
        <i 
          v-cloak 
          class="fa fa-angle-double-right border rounded-right border-dark" 
          style="position: absolute; left: -2px; top: 10px; z-index: 1; cursor: pointer;" 
          :class="{ hidden: (toggleMenu != 2 && windowInnerWidth >= 992) || (toggleMenu != 1 && windowInnerWidth < 992) }" 
          @click="expandMenu"
        ></i>

        <!-- Dashboard Tab -->
        <b-tab class="scroll_tab">
          <template slot="title">
            <i class="fa fa-tachometer-alt"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Dashboard</span>
          </template>
          <Dashboard 
            @navigate="handleNavigate"
            @add-ioc="handleAddIOC"
          />
        </b-tab>

        <!-- Query Log Tab -->
        <b-tab @click="refreshQueryLog" lazy>
          <template slot="title">
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
        </b-tab>

        <!-- RPZ Hits Tab -->
        <b-tab @click="refreshRpzHits" lazy>
          <template slot="title">
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
        </b-tab>

        <!-- Admin Tab -->
        <b-tab lazy>
          <template slot="title">
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
        </b-tab>

        <!-- Help Tab -->
        <b-tab lazy>
          <template slot="title">
            <i class="fas fa-hands-helping"></i>
            <span class="d-none d-lg-inline" :class="{ hidden: toggleMenu > 0 }">&nbsp;&nbsp;Help</span>
          </template>
          <div class="placeholder-content">
            <b-card>
              <template slot="header">
                <span class="bold"><i class="fas fa-hands-helping"></i>&nbsp;&nbsp;Help</span>
              </template>
              <p>Help content</p>
            </b-card>
          </div>
        </b-tab>
      </b-tabs>
    </b-container>

    <!-- Copyright Footer -->
    <div class="copyright">
      <p>Copyright © 2020-2023 Vadim Pavlov</p>
    </div>

    <!-- Modal Dialogs -->
    <AddAsset
      :address="addAssetAddr"
      :name="addAssetName"
      :vendor="addAssetVendor"
      :comment="addAssetComment"
      :rowid="addAssetRowID"
      :assets-by="assets_by"
      @show-info="showInfo"
    />

    <AddIOC
      :ioc="addIOC"
      :ioc-type="addIOCtype"
      :comment="addIOCcomment"
      :active="addIOCactive"
      :subdomains="addIOCsubd"
      :rowid="addBLRowID"
      @show-info="showInfo"
    />

    <ImportDB
      ref="importDB"
      :import-types="db_import_type"
      @show-info="showInfo"
    />
  </div>
</template>

<script>
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
  data() {
    return {
      // UI State
      toggleMenu: 0,
      cfgTab: 0,
      windowInnerWidth: 800,
      logs_height: 150,
      
      // Query Logs state (for navigation from Dashboard)
      qlogs_Filter: '',
      qlogs_period: '30m',
      
      // RPZ Hits state (for navigation from Dashboard)
      hits_Filter: '',
      hits_period: '30m',
      
      // IOC Modal state
      addIOC: '',
      addIOCtype: '',
      addIOCcomment: '',
      addIOCactive: true,
      addIOCsubd: true,
      addBLRowID: 0,
      
      // Asset Modal state
      addAssetAddr: '',
      addAssetName: '',
      addAssetVendor: '',
      addAssetComment: '',
      addAssetRowID: 0,
      
      // Import DB Modal state
      db_import_type: [],
      
      // Settings state
      assets_by: 'mac'
    }
  },
  mounted() {
    this.updateWindowSize()
    this.$nextTick(() => {
      window.addEventListener('resize', this.updateWindowSize)
      
      // Restore menu state from localStorage
      if (window.localStorage.getItem('toggleMenu')) {
        this.toggleMenu = parseInt(window.localStorage.getItem('toggleMenu'))
      }
      
      // Handle URL hash for tab navigation
      if (window.location.hash) {
        const parts = window.location.hash.split(/#|\//).filter(String)
        if (parts[0] === 'i2r') {
          this.cfgTab = parseInt(parts[1])
        }
        if (parts[2] === 'hidemenu') {
          this.toggleMenu = 2
        }
      }
      
      // Fetch settings including assets_by
      this.getSettings()
    })
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.updateWindowSize)
  },
  methods: {
    updateWindowSize() {
      this.logs_height = window.innerHeight > 400 ? (window.innerHeight - 240) : 150
      this.windowInnerWidth = window.innerWidth
    },
    changeTab(tab) {
      history.pushState(null, null, '#i2r/' + tab)
    },
    collapseMenu() {
      this.toggleMenu += 1
      this.updateWindowSize()
      window.localStorage.setItem('toggleMenu', this.toggleMenu)
    },
    expandMenu() {
      this.toggleMenu = 0
      this.updateWindowSize()
      window.localStorage.setItem('toggleMenu', this.toggleMenu)
    },
    
    // Handle navigation from Dashboard
    handleNavigate(data) {
      if (data.type === 'qlogs') {
        this.qlogs_Filter = data.filter
        this.qlogs_period = data.period
      } else if (data.type === 'hits') {
        this.hits_Filter = data.filter
        this.hits_period = data.period
      }
      this.cfgTab = data.tab
    },
    
    // Handle IOC add request from Dashboard or Admin Block/Allow lists
    handleAddIOC(data) {
      this.addIOC = data.ioc
      this.addIOCtype = data.type
      this.addIOCcomment = data.comment !== undefined ? data.comment : ''
      this.addBLRowID = data.rowid !== undefined ? data.rowid : 0
      this.addIOCactive = data.active !== undefined ? data.active : true
      this.addIOCsubd = data.subdomains !== undefined ? data.subdomains : true
      this.$emit('bv::show::modal', 'mAddIOC')
    },
    
    // Handle IOC delete request from Admin Block/Allow lists
    handleDeleteIOC(data) {
      this.$bvModal.msgBoxConfirm('You are about to delete the selected entry. This action is irreversible!', {
        title: 'Please confirm the action',
        size: 'md',
        buttonSize: 'md',
        okVariant: 'danger',
        okTitle: 'YES',
        cancelTitle: 'NO',
        footerClass: 'p-2',
        bodyClass: 'text-center',
        hideHeaderClose: false,
        centered: true
      }).then(value => {
        if (value) {
          this.deleteIOC(data.ioc, data.table)
        }
      })
    },
    
    // Delete IOC API call
    async deleteIOC(ioc, table) {
      try {
        const response = await fetch(`/rpi_admin/rpidata.php?req=${table}&id=${ioc.rowid}`, {
          method: 'DELETE'
        })
        const result = await response.json()
        
        if (result.status === 'success') {
          this.$root.$emit('bv::refresh::table', table)
        } else {
          this.showInfo(result.reason, 3)
        }
      } catch (error) {
        this.showInfo('Unknown error!!!', 3)
      }
    },
    
    // Refresh Query Log table when tab is clicked
    refreshQueryLog() {
      if (this.$refs.queryLog) {
        this.$refs.queryLog.refreshTable()
      }
    },
    
    // Refresh RPZ Hits table when tab is clicked
    refreshRpzHits() {
      if (this.$refs.rpzHits) {
        this.$refs.rpzHits.refreshTable()
      }
    },
    
    // Handle asset add/edit request from Admin
    handleAddAsset(data) {
      this.addAssetAddr = data.address
      this.addAssetName = data.name
      this.addAssetVendor = data.vendor
      this.addAssetComment = data.comment
      this.addAssetRowID = data.rowid
      this.$emit('bv::show::modal', 'mAddAsset')
    },
    
    // Handle asset delete request from Admin
    handleDeleteAsset(data) {
      this.$bvModal.msgBoxConfirm('You are about to delete the selected asset. This action is irreversible!', {
        title: 'Please confirm the action',
        size: 'md',
        buttonSize: 'md',
        okVariant: 'danger',
        okTitle: 'YES',
        cancelTitle: 'NO',
        footerClass: 'p-2',
        bodyClass: 'text-center',
        hideHeaderClose: false,
        centered: true
      }).then(value => {
        if (value) {
          this.deleteAsset(data.asset, data.table)
        }
      })
    },
    
    // Delete asset API call
    async deleteAsset(asset, table) {
      try {
        const response = await fetch(`/rpi_admin/rpidata.php?req=${table}&id=${asset.rowid}`, {
          method: 'DELETE'
        })
        const result = await response.json()
        
        if (result.status === 'success') {
          this.$root.$emit('bv::refresh::table', table)
        } else {
          this.showInfo(result.reason, 3)
        }
      } catch (error) {
        this.showInfo('Unknown error!!!', 3)
      }
    },
    
    // Show info message
    showInfo(msg, time) {
      const size = msg.length > 30 ? 'md' : 'sm'
      const id = Math.random().toString(36).substring(7)
      
      this.$bvModal.msgBoxOk(msg, {
        id: 'infoMsgBox' + id,
        size: size,
        buttonSize: 'sm',
        okVariant: 'success',
        headerClass: 'p-2 border-bottom-0',
        footerClass: 'p-2 border-top-0',
        bodyClass: 'font-weight-bold text-center',
        centered: true
      })
      
      setTimeout(() => {
        this.$bvModal.hide('infoMsgBox' + id)
      }, time * 1000)
    },
    
    // Handle open import modal request from Tools
    handleOpenImportModal(data) {
      this.db_import_type = data.db_import_type || []
      this.$emit('bv::show::modal', 'mImportDB')
    },
    
    // Fetch settings from API
    async getSettings() {
      try {
        const response = await fetch('/rpi_admin/rpidata.php?req=RPIsettings')
        const data = await response.json()
        this.assets_by = data.assets_by || 'mac'
      } catch (error) {
        console.error('Error fetching settings:', error)
      }
    }
  }
}
</script>

<style scoped>
.placeholder-content {
  padding: 1rem;
}
</style>
