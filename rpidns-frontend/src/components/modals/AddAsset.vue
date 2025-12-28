<template>
  <b-modal 
    centered 
    title="Asset" 
    id="mAddAsset" 
    ref="refAddAsset" 
    body-class="text-center pt-0 pb-0" 
    ok-title="Add" 
    @ok="addAsset"
    @show="onShow"
    v-cloak
  >
    <span class="text-center">
      <b-container fluid>
        <b-row class="pb-1">
          <b-col md="12" class="p-0">
            <b-input 
              v-model.trim="localAddress" 
              :placeholder="`Enter ${addressType} address`"
              v-b-tooltip.hover 
              :title="`${addressType} Address`"
            />
          </b-col>
        </b-row>
        <b-row class="pb-1">
          <b-col md="12" class="p-0">
            <b-input 
              v-model.trim="localName" 
              placeholder="Enter Name"
              v-b-tooltip.hover 
              title="Name"
            />
          </b-col>
        </b-row>
        <b-row class="pb-1">
          <b-col md="12" class="p-0">
            <b-input 
              v-model.trim="localVendor" 
              placeholder="Enter Vendor"
              v-b-tooltip.hover 
              title="Vendor"
            />
          </b-col>
        </b-row>
        <b-row>
          <b-col md="12" class="p-0">
            <b-textarea 
              rows="3" 
              max-rows="6" 
              maxlength="250" 
              v-model.trim="localComment" 
              placeholder="Commentary"
              v-b-tooltip.hover 
              title="Commentary"
            />
          </b-col>
        </b-row>
      </b-container>
    </span>
  </b-modal>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'AddAsset',
  props: {
    address: {
      type: String,
      default: ''
    },
    name: {
      type: String,
      default: ''
    },
    vendor: {
      type: String,
      default: ''
    },
    comment: {
      type: String,
      default: ''
    },
    rowid: {
      type: Number,
      default: 0
    },
    assetsBy: {
      type: String,
      default: 'mac'
    }
  },
  data() {
    return {
      localAddress: '',
      localName: '',
      localVendor: '',
      localComment: ''
    }
  },
  computed: {
    addressType() {
      return this.assetsBy === 'mac' ? 'MAC' : 'IP'
    }
  },
  setup() {
    const api = useApi()
    return { api }
  },
  methods: {
    onShow() {
      // Sync props to local data when modal opens
      this.localAddress = this.address
      this.localName = this.name
      this.localVendor = this.vendor
      this.localComment = this.comment
    },
    async addAsset(event) {
      event.preventDefault()
      
      const data = {
        id: this.rowid,
        name: this.localName,
        address: this.localAddress,
        vendor: this.localVendor,
        comment: this.localComment
      }
      
      try {
        let response
        if (this.rowid === 0) {
          // Create new asset
          response = await this.api.post({ req: 'assets' }, data)
        } else {
          // Update existing asset
          response = await this.api.put({ req: 'assets' }, data)
        }
        
        if (response.status === 'success') {
          this.$root.$emit('bv::refresh::table', 'assets')
          this.$refs.refAddAsset.hide()
        } else {
          this.$emit('show-info', response.reason, 3)
        }
      } catch (error) {
        this.$emit('show-info', 'Unknown error!!!', 3)
      }
    }
  }
}
</script>
