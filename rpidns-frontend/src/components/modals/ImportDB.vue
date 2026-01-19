<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div>
    <!-- Import DB Modal -->
    <BModal 
      v-model="isVisible"
      centered 
      title="Import DB" 
      id="mImportDB" 
      body-class="text-center pt-0 pb-0" 
      ok-title="Import" 
      @ok="importDB"
      @show="onShow"
      @hidden="onHidden"
    >
      <span class="text-center">
        <BContainer fluid>
          <BRow class="pb-2">
            <BCol md="12" class="p-0">
              <BFormFile 
                :key="fileInputKey"
                v-model="upload_file" 
                accept=".sqlite, .gzip, .zip" 
                :state="upload_file !== null" 
                placeholder="Choose a file or drop it here..." 
                drop-placeholder="Drop file here..."
              ></BFormFile>
            </BCol>
          </BRow>
          <BRow class="pb-1">
            <BCol md="12" class="p-0">
              <BFormGroup class="text-left">
                <BFormCheckboxGroup id="dbImportType" v-model="db_import_type" name="dbImportType">
                  <BRow class="pb-1">
                    <BCol md="4"><BFormCheckbox value="assets">Assets</BFormCheckbox></BCol>
                    <BCol md="4" class="p-0"><BFormCheckbox value="bl">Block</BFormCheckbox></BCol>
                    <BCol md="4" class="p-0"><BFormCheckbox value="wl">Allow</BFormCheckbox></BCol>
                  </BRow>
                  <BRow class="pb-1">
                    <BCol md="6"><BFormCheckbox value="q_raw">Query logs - Raw</BFormCheckbox></BCol>
                    <BCol md="6" class="p-0"><BFormCheckbox value="h_raw">RPZ hits logs - Raw</BFormCheckbox></BCol>
                  </BRow>
                  <BRow class="pb-1">
                    <BCol md="6"><BFormCheckbox value="q_5m">Query logs - 5m</BFormCheckbox></BCol>
                    <BCol md="6" class="p-0"><BFormCheckbox value="h_5m">RPZ hits logs - 5m</BFormCheckbox></BCol>
                  </BRow>
                  <BRow class="pb-1">
                    <BCol md="6"><BFormCheckbox value="q_1h">Query logs - 1h</BFormCheckbox></BCol>
                    <BCol md="6" class="p-0"><BFormCheckbox value="h_1h">RPZ hits logs - 1h</BFormCheckbox></BCol>
                  </BRow>
                  <BRow class="pb-0">
                    <BCol md="6"><BFormCheckbox value="q_1d">Query logs - 1d</BFormCheckbox></BCol>
                    <BCol md="6" class="p-0"><BFormCheckbox value="h_1d">RPZ hits logs - 1d</BFormCheckbox></BCol>
                  </BRow>
                </BFormCheckboxGroup>
              </BFormGroup>
            </BCol>
          </BRow>
        </BContainer>
      </span>
    </BModal>

    <!-- Upload Progress Modal -->
    <BModal 
      v-model="progressVisible"
      centered 
      title="Upload progress" 
      id="mUploadPr" 
      body-class="text-center pt-0 pb-0" 
      no-close-on-esc 
      no-close-on-backdrop 
      ok-only 
      ok-title="Cancel" 
      ok-variant="secondary"
      :ok-disabled="!fUpInd"
      @ok="cancelUpload"
    >
      <BProgress v-if="fUpInd" :value="upload_progress" :max="100" height="20px" show-progress animated></BProgress>
      <span v-if="fImpInd">
        <BSpinner small type="grow"></BSpinner>&nbsp;&nbsp;Validating...
      </span>
    </BModal>
  </div>
</template>

<script>
import { ref } from 'vue'
import axios from 'axios'

export default {
  name: 'ImportDB',
  props: {
    importTypes: {
      type: Array,
      default: () => ['assets', 'bl', 'wl', 'q_raw', 'h_raw', 'q_5m', 'h_5m', 'q_1h', 'h_1h', 'q_1d', 'h_1d']
    }
  },
  emits: ['show-info'],
  setup(props, { emit, expose }) {
    const isVisible = ref(false)
    const progressVisible = ref(false)
    const upload_file = ref(null)
    const db_import_type = ref([])
    const upload_progress = ref(0)
    const upload_cancel_token = ref(null)
    const fUpInd = ref(true)
    const fImpInd = ref(false)
    const fileInputKey = ref(0)

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      upload_file.value = null
      db_import_type.value = [...props.importTypes]
      upload_progress.value = 0
      fUpInd.value = true
      fImpInd.value = false
      // Force re-render of file input to allow re-selecting same file
      fileInputKey.value++
    }

    const onHidden = () => {
      // Reset file input when modal is closed
      upload_file.value = null
      fileInputKey.value++
    }

    const importDB = async (event) => {
      event.preventDefault()

      if (!upload_file.value) {
        emit('show-info', 'Please select a file to import', 3)
        return
      }

      const formData = new FormData()
      formData.append('type', 'DB')
      formData.append('req', 'import')
      formData.append('objects', db_import_type.value)
      formData.append('file', upload_file.value)

      hide()
      progressVisible.value = true

      upload_progress.value = 0
      upload_cancel_token.value = axios.CancelToken.source()
      fUpInd.value = true
      fImpInd.value = false

      try {
        // Debug: log request details
/*         console.error('[ImportDB] POST import request:', {
          file: upload_file.value?.name,
          fileSize: upload_file.value?.size,
          fileType: upload_file.value?.type,
          objects: db_import_type.value
        }) */

        const response = await axios.post('/rpi_admin/rpidata.php?req=import', formData, {
          cancelToken: upload_cancel_token.value.token,
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (progressEvent) => {
            upload_progress.value = parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100))
            if (upload_progress.value >= 100) {
              fUpInd.value = false
              fImpInd.value = true
            }
          }
        })

        // Debug: log response
        //console.error('[ImportDB] Server response:', response.data)

        if (response.data.status === 'success') {
          progressVisible.value = false
          emit('show-info', 'The DB will be imported soon', 3)
        } else {
          progressVisible.value = false
          // Server may return error in 'reason' or 'details' field
          const errorMsg = response.data.reason || response.data.details || 'Import failed'
          console.error('[ImportDB] Import error:', errorMsg)
          emit('show-info', errorMsg, 3)
        }
      } catch (error) {
        progressVisible.value = false
        console.error('[ImportDB] Request error:', error)
        if (axios.isCancel(error)) {
          emit('show-info', 'Upload canceled', 3)
        } else {
          emit('show-info', 'Unknown error!!!', 3)
        }
      }
    }

    const cancelUpload = (event) => {
      event.preventDefault()
      if (upload_cancel_token.value) {
        upload_cancel_token.value.cancel()
      }
    }

    expose({ show, hide })

    return {
      isVisible, progressVisible, upload_file, db_import_type, upload_progress,
      fUpInd, fImpInd, fileInputKey, show, hide, onShow, onHidden, importDB, cancelUpload
    }
  }
}
</script>
