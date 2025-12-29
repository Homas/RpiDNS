<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Asset" 
    id="mAddAsset" 
    body-class="text-center pt-0 pb-0" 
    ok-title="Add" 
    @ok="addAsset"
    @show="onShow"
  >
    <span class="text-center">
      <BContainer fluid>
        <BRow class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model.trim="localAddress" 
              :placeholder="`Enter ${addressType} address`"
              v-b-tooltip.hover 
              :title="`${addressType} Address`"
            />
          </BCol>
        </BRow>
        <BRow class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model.trim="localName" 
              placeholder="Enter Name"
              v-b-tooltip.hover 
              title="Name"
            />
          </BCol>
        </BRow>
        <BRow class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model.trim="localVendor" 
              placeholder="Enter Vendor"
              v-b-tooltip.hover 
              title="Vendor"
            />
          </BCol>
        </BRow>
        <BRow>
          <BCol md="12" class="p-0">
            <BFormTextarea 
              rows="3" 
              max-rows="6" 
              maxlength="250" 
              v-model.trim="localComment" 
              placeholder="Commentary"
              v-b-tooltip.hover 
              title="Commentary"
            />
          </BCol>
        </BRow>
      </BContainer>
    </span>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'AddAsset',
  props: {
    address: { type: String, default: '' },
    name: { type: String, default: '' },
    vendor: { type: String, default: '' },
    comment: { type: String, default: '' },
    rowid: { type: Number, default: 0 },
    assetsBy: { type: String, default: 'mac' }
  },
  emits: ['show-info', 'refresh-table'],
  setup(props, { emit, expose }) {
    const api = useApi()
    const isVisible = ref(false)
    const localAddress = ref('')
    const localName = ref('')
    const localVendor = ref('')
    const localComment = ref('')

    const addressType = computed(() => props.assetsBy === 'mac' ? 'MAC' : 'IP')

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      localAddress.value = props.address
      localName.value = props.name
      localVendor.value = props.vendor
      localComment.value = props.comment
    }

    const addAsset = async (event) => {
      event.preventDefault()
      const data = {
        id: props.rowid,
        name: localName.value,
        address: localAddress.value,
        vendor: localVendor.value,
        comment: localComment.value
      }

      try {
        let response
        if (props.rowid === 0) {
          response = await api.post({ req: 'assets' }, data)
        } else {
          response = await api.put({ req: 'assets' }, data)
        }

        if (response.status === 'success') {
          emit('refresh-table')
          hide()
        } else {
          emit('show-info', response.reason, 3)
        }
      } catch (error) {
        emit('show-info', 'Unknown error!!!', 3)
      }
    }

    expose({ show, hide })

    return {
      isVisible, localAddress, localName, localVendor, localComment, addressType,
      show, hide, onShow, addAsset
    }
  }
}
</script>
