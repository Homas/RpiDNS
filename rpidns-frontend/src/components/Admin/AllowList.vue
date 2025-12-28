<template>
  <div>
    <!-- Toolbar Row -->
    <b-row class="d-none d-sm-flex">
      <b-col cols="3" lg="3">
        <b-button 
          v-b-tooltip.hover 
          title="Add" 
          variant="outline-secondary" 
          size="sm" 
          @click.stop="openAddModal"
        >
          <i class="fa fa-plus"></i>
        </b-button>
        <b-button 
          v-b-tooltip.hover 
          title="Edit" 
          variant="outline-secondary" 
          size="sm" 
          :disabled="!wl_selected"
          @click.stop="openEditModal"
        >
          <i class="fa fa-edit"></i>
        </b-button>
        <b-button 
          v-b-tooltip.hover 
          title="Delete" 
          variant="outline-secondary" 
          size="sm" 
          :disabled="!wl_selected"
          @click.stop="confirmDelete"
        >
          <i class="fa fa-trash-alt"></i>
        </b-button>
        <b-button 
          v-b-tooltip.hover 
          title="Refresh" 
          variant="outline-secondary" 
          size="sm" 
          @click.stop="refreshTable"
        >
          <i class="fa fa-sync"></i>
        </b-button>
      </b-col>

      <b-col cols="3" lg="3"></b-col>

      <b-col cols="6" lg="6">
        <b-form-group label-cols-md="4" label-size="sm">
          <b-input-group>
            <b-input-group-text slot="prepend" size="sm">
              <i class="fas fa-filter fa-fw" size="sm"></i>
            </b-input-group-text>
            <b-form-input 
              v-model="wl_Filter" 
              placeholder="Type to search" 
              size="sm"
              debounce="300"
            ></b-form-input>
            <b-button 
              size="sm" 
              slot="append" 
              :disabled="!wl_Filter" 
              @click="wl_Filter = ''"
            >
              Clear
            </b-button>
          </b-input-group>
        </b-form-group>
      </b-col>
    </b-row>

    <!-- Allow List Table -->
    <b-row>
      <b-col cols="12" lg="12">
        <b-table
          id="whitelist"
          ref="whitelist"
          :sticky-header="`${logs_height}px`"
          :sort-icon-left="true"
          no-border-collapse
          striped
          hover
          small
          responsive
          :no-provider-paging="true"
          :no-provider-sorting="true"
          :no-provider-filtering="true"
          :items="getAllowList"
          :api-url="apiUrl"
          :fields="lists_fields"
          :filter="wl_Filter"
        >
          <template v-slot:table-busy>
            <div class="text-center text-second m-0 p-0">
              <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;
              <strong>Loading...</strong>
            </div>
          </template>

          <!-- Row Selection Checkbox -->
          <template v-slot:cell(rowid)="row">
            <b-form-checkbox 
              :value="row.item" 
              :name="'wl' + row.item.rowid" 
              v-model="wl_selected" 
            />
          </template>

          <!-- Subdomains Toggle -->
          <template v-slot:cell(subdomains)="row">
            <span @click="toggleIOC(row.item.rowid, 'subdomains')">
              <div v-if="row.item.subdomains == '1'">
                <i class="fas fa-toggle-on fa-lg"></i>
              </div>
              <div v-else>
                <i class="fas fa-toggle-off fa-lg"></i>
              </div>
            </span>
          </template>

          <!-- Active Toggle -->
          <template v-slot:cell(active)="row">
            <span @click="toggleIOC(row.item.rowid, 'active')">
              <div v-if="row.item.active == '1'">
                <i class="fas fa-toggle-on fa-lg"></i>
              </div>
              <div v-else>
                <i class="fas fa-toggle-off fa-lg"></i>
              </div>
            </span>
          </template>

          <!-- IOC Column with Popover -->
          <template v-slot:cell(ioc)="row">
            <b-popover 
              title="Actions" 
              :target="'tip-whitelist' + row.item.ioc" 
              triggers="hover"
            >
              <a href="javascript:{}" @click.stop="navigateToQueries(row.item)">Show queries</a><br>
              <a href="javascript:{}" @click.stop="navigateToHits(row.item)">Show hits</a>
            </b-popover>
            <span :id="'tip-whitelist' + row.item.ioc">{{ row.item.ioc }}</span>
          </template>
        </b-table>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'AllowList',
  props: {
    logs_height: {
      type: Number,
      default: 150
    }
  },
  data() {
    return {
      wl_Filter: '',
      wl_selected: null,
      apiUrl: '/rpi_admin/rpidata.php?req=whitelist',
      lists_fields: [
        { 
          key: 'rowid', 
          label: '', 
          tdClass: 'width050 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell' 
        },
        { 
          key: 'ioc', 
          label: 'Domain/IP', 
          sortable: true, 
          tdClass: 'mw150'
        },
        { 
          key: 'dtz', 
          label: 'Added', 
          sortable: true, 
          tdClass: 'width250 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell',
          formatter: (value) => { 
            const date = new Date(value)
            return date.toLocaleString()
          }
        },
        { 
          key: 'active', 
          label: 'Active', 
          sortable: true, 
          tdClass: 'width050 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        },
        { 
          key: 'subdomains', 
          label: '*.', 
          sortable: true, 
          tdClass: 'width050 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        },
        { 
          key: 'comment', 
          label: 'Comment', 
          sortable: true, 
          tdClass: 'mw150 d-none d-lg-table-cell', 
          thClass: 'd-none d-lg-table-cell'
        }
      ],
      localItems: []
    }
  },
  setup() {
    const api = useApi()
    return { api }
  },
  methods: {
    async getAllowList(ctx) {
      try {
        const response = await this.api.get({
          req: 'whitelist',
          sortBy: ctx.sortBy,
          sortDesc: ctx.sortDesc
        })
        this.localItems = response.data || []
        return this.localItems
      } catch (error) {
        console.error('Error fetching allow list:', error)
        return []
      }
    },
    
    refreshTable() {
      this.$root.$emit('bv::refresh::table', 'whitelist')
    },
    
    openAddModal() {
      this.$emit('add-ioc', {
        mode: 'add',
        ioc: '',
        type: 'wl',
        comment: '',
        active: true,
        subdomains: true,
        rowid: 0
      })
    },
    
    openEditModal() {
      if (this.wl_selected) {
        this.$emit('add-ioc', {
          mode: 'edit',
          ioc: this.wl_selected.ioc,
          type: 'wl',
          comment: this.wl_selected.comment,
          active: this.wl_selected.active === 1,
          subdomains: this.wl_selected.subdomains === 1,
          rowid: this.wl_selected.rowid
        })
      }
    },
    
    confirmDelete() {
      if (this.wl_selected) {
        this.$emit('delete-ioc', {
          ioc: this.wl_selected,
          table: 'whitelist'
        })
      }
    },
    
    async toggleIOC(id, field) {
      // Find the IOC in local items
      const ioc = this.localItems.find(item => item.rowid === id)
      if (!ioc) return
      
      const data = {
        id: ioc.rowid,
        ioc: ioc.ioc,
        ltype: 'whitelist',
        active: field === 'active' ? !ioc.active : (ioc.active ? true : false),
        subdomains: field === 'subdomains' ? !ioc.subdomains : (ioc.subdomains ? true : false),
        comment: ioc.comment
      }
      
      try {
        const response = await this.api.put({ req: 'whitelist' }, data)
        if (response.status === 'success') {
          // Update local state
          ioc[field] = ioc[field] ? 0 : 1
        } else {
          this.$emit('show-info', response.reason)
        }
      } catch (error) {
        this.$emit('show-info', 'Unknown error!!!')
      }
    },
    
    navigateToQueries(item) {
      this.$emit('navigate', {
        type: 'qlogs',
        filter: item.ioc,
        tab: 1
      })
    },
    
    navigateToHits(item) {
      this.$emit('navigate', {
        type: 'hits',
        filter: item.ioc,
        tab: 2
      })
    }
  }
}
</script>

<style scoped>
.width050 {
  width: 50px;
}

.width250 {
  width: 250px;
}

.mw150 {
  max-width: 150px;
}
</style>
