<template>
  <div>
    <b-row>
      <b-col cols="12" lg="12">
        <b-table
          id="rpz_feeds"
          :sticky-header="`${logs_height}px`"
          :sort-icon-left="true"
          no-border-collapse
          striped
          hover
          small
          :no-provider-paging="true"
          :no-provider-sorting="true"
          :no-provider-filtering="true"
          :items="getRpzFeeds"
          :api-url="apiUrl"
          :fields="rpz_feeds_fields"
          :filter="rpz_feeds_Filter"
        >
          <template v-slot:table-busy>
            <div class="text-center text-second m-0 p-0">
              <b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;
              <strong>Loading...</strong>
            </div>
          </template>

          <!-- Actions Column with Retransfer Button -->
          <template v-slot:cell(act)="row">
            <b-button 
              v-b-tooltip.hover 
              title="Retransfer" 
              variant="outline-secondary" 
              size="sm" 
              @click.stop="retransferRPZ(row)"
            >
              <i class="fas fa-redo"></i>
            </b-button>
          </template>
        </b-table>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'RpzFeeds',
  props: {
    logs_height: {
      type: Number,
      default: 150
    }
  },
  data() {
    return {
      rpz_feeds_Filter: '',
      apiUrl: '/rpi_admin/rpidata.php?req=rpz_feeds',
      rpz_feeds_fields: [
        { 
          key: 'feed', 
          label: 'Feed', 
          sortable: true, 
          tdClass: 'mw150 d-none d-sm-table-cell', 
          thClass: 'd-none d-sm-table-cell'
        },
        { 
          key: 'action', 
          label: 'Feed action', 
          sortable: true, 
          tdClass: 'mw150'
        },
        { 
          key: 'desc', 
          label: 'Description', 
          sortable: true, 
          tdClass: 'mw400 d-none d-md-table-cell', 
          thClass: 'd-none d-md-table-cell'
        },
        { 
          key: 'act', 
          label: 'Actions', 
          sortable: false, 
          tdClass: 'mw050'
        }
      ]
    }
  },
  setup() {
    const api = useApi()
    return { api }
  },
  methods: {
    async getRpzFeeds(ctx) {
      try {
        const response = await this.api.get({
          req: 'rpz_feeds',
          sortBy: ctx.sortBy,
          sortDesc: ctx.sortDesc
        })
        return response.data || []
      } catch (error) {
        console.error('Error fetching RPZ feeds:', error)
        return []
      }
    },
    
    async retransferRPZ(row) {
      try {
        const response = await this.api.put(
          { req: 'retransfer_feed' },
          { feed: row.item.feed }
        )
        
        if (response.status !== 'success') {
          this.showInfo(response.reason, 3)
        } else {
          this.showInfo('Retransfer requested', 3)
        }
      } catch (error) {
        console.error('Error requesting retransfer:', error)
        this.showInfo('Unknown error!!!', 3)
      }
    },
    
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
    }
  }
}
</script>

<style scoped>
.mw050 {
  max-width: 50px;
}

.mw150 {
  max-width: 150px;
}

.mw400 {
  max-width: 400px;
}
</style>
