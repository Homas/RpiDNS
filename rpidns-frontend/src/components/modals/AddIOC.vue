<template>
  <b-modal 
    centered 
    title="Add Indicator" 
    id="mAddIOC" 
    ref="refAddIOC" 
    body-class="text-center pt-0 pb-0" 
    ok-title="Add" 
    @ok="addIOC"
    @show="onShow"
    v-cloak
  >
    <span class="text-center">
      <b-container fluid>
        <b-row class="pb-1">
          <b-col md="12" class="p-0">
            <b-input 
              v-model.trim="localIOC" 
              placeholder="Enter IOC"
              v-b-tooltip.hover 
              title="IOC"
            />
          </b-col>
        </b-row>
        <b-row class="pb-1">
          <b-col md="12" class="p-0 text-left">
            <b-form-checkbox 
              v-model="localSubdomains" 
              switch 
              size="lg"
            >
              &nbsp;Include subdomains
            </b-form-checkbox>
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
        <b-row class="pb-1">
          <b-col md="12" class="p-0 text-left">
            <b-form-checkbox 
              v-model="localActive" 
              switch 
              size="lg"
            >
              &nbsp;Active
            </b-form-checkbox>
          </b-col>
        </b-row>
      </b-container>
    </span>
  </b-modal>
</template>

<script>
import { useApi } from '@/composables/useApi'

export default {
  name: 'AddIOC',
  props: {
    ioc: {
      type: String,
      default: ''
    },
    iocType: {
      type: String,
      default: 'bl' // 'bl' for blacklist, 'wl' for whitelist
    },
    comment: {
      type: String,
      default: ''
    },
    active: {
      type: Boolean,
      default: true
    },
    subdomains: {
      type: Boolean,
      default: true
    },
    rowid: {
      type: Number,
      default: 0
    }
  },
  data() {
    return {
      localIOC: '',
      localComment: '',
      localActive: true,
      localSubdomains: true
    }
  },
  computed: {
    tableName() {
      return this.iocType === 'bl' ? 'blacklist' : 'whitelist'
    }
  },
  setup() {
    const api = useApi()
    return { api }
  },
  methods: {
    onShow() {
      // Sync props to local data when modal opens
      this.localIOC = this.ioc
      this.localComment = this.comment
      this.localActive = this.active
      this.localSubdomains = this.subdomains
    },
    async addIOC(event) {
      event.preventDefault()
      
      const data = {
        id: this.rowid,
        ioc: this.localIOC,
        ltype: this.iocType,
        active: this.localActive,
        subdomains: this.localSubdomains,
        comment: this.localComment
      }
      
      try {
        let response
        if (this.rowid === 0) {
          // Create new IOC
          response = await this.api.post({ req: this.tableName }, data)
        } else {
          // Update existing IOC
          response = await this.api.put({ req: this.tableName }, data)
        }
        
        if (response.status === 'success') {
          this.$root.$emit('bv::refresh::table', this.tableName)
          this.$refs.refAddIOC.hide()
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
