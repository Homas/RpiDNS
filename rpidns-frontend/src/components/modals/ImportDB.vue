<template>
  <div>
    <!-- Import DB Modal -->
    <b-modal 
      centered 
      title="Import DB" 
      id="mImportDB" 
      ref="refImportDB" 
      body-class="text-center pt-0 pb-0" 
      ok-title="Import" 
      @ok="importDB"
      @show="onShow"
      v-cloak
    >
      <span class="text-center">
        <b-container fluid>
          <b-row class="pb-2">
            <b-col md="12" class="p-0">
              <b-form-file 
                v-model="upload_file" 
                accept=".sqlite, .gzip, .zip" 
                :state="upload_file !== null" 
                placeholder="Choose a file or drop it here..." 
                drop-placeholder="Drop file here..."
              ></b-form-file>
            </b-col>
          </b-row>
          <b-row class="pb-1">
            <b-col md="12" class="p-0">
              <b-form-group class="text-left">
                <b-form-checkbox-group 
                  id="dbImportType" 
                  v-model="db_import_type" 
                  name="dbImportType"
                >
                  <b-row class="pb-1">
                    <b-col md="4">
                      <b-form-checkbox value="assets">Assets</b-form-checkbox>
                    </b-col>
                    <b-col md="4" class="p-0">
                      <b-form-checkbox value="bl">Block</b-form-checkbox>
                    </b-col>
                    <b-col md="4" class="p-0">
                      <b-form-checkbox value="wl">Allow</b-form-checkbox>
                    </b-col>
                  </b-row>
                  <b-row class="pb-1">
                    <b-col md="6">
                      <b-form-checkbox value="q_raw">Query logs - Raw</b-form-checkbox>
                    </b-col>
                    <b-col md="6" class="p-0">
                      <b-form-checkbox value="h_raw">RPZ hits logs - Raw</b-form-checkbox>
                    </b-col>
                  </b-row>
                  <b-row class="pb-1">
                    <b-col md="6">
                      <b-form-checkbox value="q_5m">Query logs - 5m</b-form-checkbox>
                    </b-col>
                    <b-col md="6" class="p-0">
                      <b-form-checkbox value="h_5m">RPZ hits logs - 5m</b-form-checkbox>
                    </b-col>
                  </b-row>
                  <b-row class="pb-1">
                    <b-col md="6">
                      <b-form-checkbox value="q_1h">Query logs - 1h</b-form-checkbox>
                    </b-col>
                    <b-col md="6" class="p-0">
                      <b-form-checkbox value="h_1h">RPZ hits logs - 1h</b-form-checkbox>
                    </b-col>
                  </b-row>
                  <b-row class="pb-0">
                    <b-col md="6">
                      <b-form-checkbox value="q_1d">Query logs - 1d</b-form-checkbox>
                    </b-col>
                    <b-col md="6" class="p-0">
                      <b-form-checkbox value="h_1d">RPZ hits logs - 1d</b-form-checkbox>
                    </b-col>
                  </b-row>
                </b-form-checkbox-group>
              </b-form-group>
            </b-col>
          </b-row>
        </b-container>
      </span>
    </b-modal>

    <!-- Upload Progress Modal -->
    <b-modal 
      centered 
      title="Upload progress" 
      id="mUploadPr" 
      ref="refUploadPr" 
      body-class="text-center pt-0 pb-0" 
      no-close-on-esc 
      no-close-on-backdrop 
      ok-only 
      ok-title="Cancel" 
      ok-variant="secondary"
      :ok-disabled="!fUpInd"
      @ok="cancelUpload"
      v-cloak
    >
      <b-progress 
        v-if="fUpInd" 
        :value="upload_progress" 
        :max="100" 
        height="20px" 
        show-progress 
        animated
      ></b-progress>
      <span v-if="fImpInd">
        <b-spinner small type="grow"></b-spinner>&nbsp;&nbsp;Validating...
      </span>
    </b-modal>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ImportDB',
  props: {
    importTypes: {
      type: Array,
      default: () => ['assets', 'bl', 'wl', 'q_raw', 'h_raw', 'q_5m', 'h_5m', 'q_1h', 'h_1h', 'q_1d', 'h_1d']
    }
  },
  data() {
    return {
      upload_file: null,
      db_import_type: [],
      upload_progress: 0,
      upload_cancel_token: null,
      fUpInd: true,
      fImpInd: false
    }
  },
  methods: {
    onShow() {
      // Reset state and sync import types from props
      this.upload_file = null
      this.db_import_type = [...this.importTypes]
      this.upload_progress = 0
      this.fUpInd = true
      this.fImpInd = false
    },
    async importDB(event) {
      event.preventDefault()
      
      if (!this.upload_file) {
        this.$emit('show-info', 'Please select a file to import', 3)
        return
      }
      
      const formData = new FormData()
      formData.append('type', 'DB')
      formData.append('req', 'import')
      formData.append('objects', this.db_import_type)
      formData.append('file', this.upload_file)
      
      // Show progress modal
      this.$refs.refImportDB.hide()
      this.$refs.refUploadPr.show()
      
      this.upload_progress = 0
      this.upload_cancel_token = axios.CancelToken.source()
      this.fUpInd = true
      this.fImpInd = false
      
      try {
        const response = await axios.post('/rpi_admin/rpidata.php?req=import', formData, {
          cancelToken: this.upload_cancel_token.token,
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (progressEvent) => {
            this.upload_progress = parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100))
            if (this.upload_progress >= 100) {
              this.fUpInd = false
              this.fImpInd = true
            }
          }
        })
        
        if (response.data.status === 'success') {
          this.$refs.refUploadPr.hide()
          this.$emit('show-info', 'The DB will be imported soon', 3)
        } else {
          this.$refs.refUploadPr.hide()
          this.$emit('show-info', response.data.reason, 3)
        }
      } catch (error) {
        this.$refs.refUploadPr.hide()
        if (axios.isCancel(error)) {
          this.$emit('show-info', 'Upload canceled', 3)
        } else {
          this.$emit('show-info', 'Unknown error!!!', 3)
        }
      }
    },
    cancelUpload(event) {
      event.preventDefault()
      if (this.upload_cancel_token) {
        this.upload_cancel_token.cancel()
      }
    }
  }
}
</script>
