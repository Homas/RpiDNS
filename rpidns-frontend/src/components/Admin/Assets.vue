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
          :disabled="!asset_selected"
          @click.stop="openEditModal"
        >
          <i class="fa fa-edit"></i>
        </b-button>
        <b-button 
          v-b-tooltip.hover 
          title="Delete" 
          variant="outline-secondary" 
          size="sm" 
          :disabled="!asset_selected"
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
              v-model="assets_Filter" 
              placeholder="Type to search" 
              size="sm"
            ></b-form-input>
            <b-button 
              size="sm" 
              slot="append" 
              :disabled="!assets_Filter" 
              @click="assets_Filter = ''"
            >
              Clear
            </b-button>
          </b-input-group>
        </b-form-group>
      </b-col>
    </b-row>

    <!-- Assets Table -->
    <b-row>
      <b-col cols="12" lg="12">
        <b-table
          id="assets"
          :sticky-header="`${logs_height}px`"
          :sort-icon-left="true"
          no-border-collapse
          striped
          hover
          small
          :no-provider-paging="true"
          :no-provider-sorting="true"
          :no-provider-filtering="true"
          :items="getAssets"
          :api-url="apiUrl"
          :fields="assets_fields"
          :filter="assets_Filter"
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
              :name="'asset' + row.item.rowid" 
              v-model="asset_selected" 
            />
          </template>

          <!-- Address Column with Popover -->
          <template v-slot:cell(address)="row">
            <b-popover 
              title="Actions" 
              :target="'tip-assets' + row.item.address" 
              triggers="hover"
            >
              <a href="javascript:{}" @click.stop="navigateToQueries(row.item)">Show queries</a><br>
              <a href="javascript:{}" @click.stop="navigateToHits(row.item)">Show hits</a>
            </b-popover>
            <span :id="'tip-assets' + row.item.address">{{ row.item.address }}</span>
          </template>

          <!-- Name Column with Popover -->
          <template v-slot:cell(name)="row">
            <b-popover 
              title="Actions" 
              :target="'tip-assets_name' + row.item.rowid" 
              triggers="hover"
            >
              <a href="javascript:{}" @click.stop="navigateToQueriesByName(row.item)">Show queries</a><br>
              <a href="javascript:{}" @click.stop="navigateToHitsByName(row.item)">Show hits</a>
            </b-popover>
            <span :id="'tip-assets_name' + row.item.rowid">{{ row.item.name }}</span>
          </template>

          <!-- Vendor Column with Popover -->
          <template v-slot:cell(vendor)="row">
            <b-popover 
              title="Actions" 
              :target="'tip-assets_vendor' + row.item.rowid" 
              triggers="hover"
            >
              <a href="javascript:{}" @click.stop="navigateToQueriesByVendor(row.item)">Show queries</a><br>
              <a href="javascript:{}" @click.stop="navigateToHitsByVendor(row.item)">Show hits</a>
            </b-popover>
            <span :id="'tip-assets_vendor' + row.item.rowid">{{ row.item.vendor }}</span>
          </template>
        </b-table>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'Assets',
  props: {
    logs_height: {
      type: Number,
      default: 150
    }
  },
  data() {
    return {
      assets_Filter: '',
      asset_selected: null,
      apiUrl: '/rpi_admin/rpidata.php?req=assets',
      assets_fields: [
        { 
          key: 'rowid', 
          label: '', 
          tdClass: 'width050 d-none d-sm-table-cell', 
          thClass: 'd-none d-sm-table-cell' 
        },
        { 
          key: 'address', 
          label: 'Address', 
          sortable: true, 
          tdClass: 'mw150 d-none d-sm-table-cell', 
          thClass: 'd-none d-sm-table-cell'
        },
        { 
          key: 'name', 
          label: 'Name', 
          sortable: true, 
          tdClass: 'mw200'
        },
        { 
          key: 'vendor', 
          label: 'Vendor', 
          sortable: true, 
          tdClass: 'mw150 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        },
        { 
          key: 'dtz', 
          label: 'Added', 
          sortable: true, 
          formatter: (value) => { 
            const date = new Date(value)
            return date.toLocaleString()
          }, 
          tdClass: 'mw150 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        },
        { 
          key: 'comment', 
          label: 'Comment', 
          sortable: true, 
          tdClass: 'mw150 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        }
      ]
    }
  },
  setup() {
    const api = useApi()
    return { api }
  },
  methods: {
    async getAssets(ctx) {
      try {
        const response = await this.api.get({
          req: 'assets',
          sortBy: ctx.sortBy,
          sortDesc: ctx.sortDesc
        })
        return response.data || []
      } catch (error) {
        console.error('Error fetching assets:', error)
        return []
      }
    },
    
    refreshTable() {
      this.$root.$emit('bv::refresh::table', 'assets')
    },
    
    openAddModal() {
      this.$emit('add-asset', {
        mode: 'add',
        address: '',
        name: '',
        vendor: '',
        comment: '',
        rowid: 0
      })
    },
    
    openEditModal() {
      if (this.asset_selected) {
        this.$emit('add-asset', {
          mode: 'edit',
          address: this.asset_selected.address,
          name: this.asset_selected.name,
          vendor: this.asset_selected.vendor,
          comment: this.asset_selected.comment,
          rowid: this.asset_selected.rowid
        })
      }
    },
    
    confirmDelete() {
      if (this.asset_selected) {
        this.$emit('delete-asset', {
          asset: this.asset_selected,
          table: 'assets'
        })
      }
    },
    
    navigateToQueries(item) {
      this.$emit('navigate', {
        type: 'qlogs',
        filter: item.address,
        tab: 1
      })
    },
    
    navigateToHits(item) {
      this.$emit('navigate', {
        type: 'hits',
        filter: item.address,
        tab: 2
      })
    },
    
    navigateToQueriesByName(item) {
      this.$emit('navigate', {
        type: 'qlogs',
        filter: item.name,
        tab: 1
      })
    },
    
    navigateToHitsByName(item) {
      this.$emit('navigate', {
        type: 'hits',
        filter: item.name,
        tab: 2
      })
    },
    
    navigateToQueriesByVendor(item) {
      this.$emit('navigate', {
        type: 'qlogs',
        filter: item.vendor,
        tab: 1
      })
    },
    
    navigateToHitsByVendor(item) {
      this.$emit('navigate', {
        type: 'hits',
        filter: item.vendor,
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

.mw150 {
  max-width: 150px;
}

.mw200 {
  max-width: 200px;
}
</style>
