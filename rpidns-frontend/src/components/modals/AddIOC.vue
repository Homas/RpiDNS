<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Add Indicator" 
    id="mAddIOC" 
    body-class="pt-0 pb-0" 
    ok-title="Add" 
    @ok="addIOC"
    @show="onShow"
  >
    <BContainer fluid>
        <BRow class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput 
              v-model.trim="localIOC" 
              placeholder="Enter IOC"
              v-b-tooltip.hover 
              title="IOC"
            />
          </BCol>
        </BRow>
        <BRow class="pb-1">
          <BCol md="12" class="p-0 text-left">
            <BFormCheckbox v-model="localSubdomains" switch size="lg">
              &nbsp;Include subdomains
            </BFormCheckbox>
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
        <BRow class="pb-1">
          <BCol md="12" class="p-0 text-left">
            <BFormCheckbox v-model="localActive" switch size="lg">
              &nbsp;Active
            </BFormCheckbox>
          </BCol>
        </BRow>
      </BContainer>
  </BModal>
</template>

<script>
import { ref, computed } from 'vue'
import { useApi } from '@/composables/useApi'

export default {
  name: 'AddIOC',
  props: {
    ioc: { type: String, default: '' },
    iocType: { type: String, default: 'bl' },
    comment: { type: String, default: '' },
    active: { type: Boolean, default: true },
    subdomains: { type: Boolean, default: true },
    rowid: { type: Number, default: 0 }
  },
  emits: ['show-info', 'refresh-table'],
  setup(props, { emit, expose }) {
    const api = useApi()
    const isVisible = ref(false)
    const localIOC = ref('')
    const localComment = ref('')
    const localActive = ref(true)
    const localSubdomains = ref(true)

    const tableName = computed(() => props.iocType === 'bl' ? 'blacklist' : 'whitelist')

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      localIOC.value = props.ioc
      localComment.value = props.comment
      localActive.value = props.active
      localSubdomains.value = props.subdomains
    }

    const addIOC = async (event) => {
      event.preventDefault()
      const data = {
        id: props.rowid,
        ioc: localIOC.value,
        ltype: props.iocType,
        active: localActive.value,
        subdomains: localSubdomains.value,
        comment: localComment.value
      }

      try {
        let response
        if (props.rowid === 0) {
          response = await api.post({ req: tableName.value }, data)
        } else {
          response = await api.put({ req: tableName.value }, data)
        }

        if (response.status === 'success') {
          emit('refresh-table', tableName.value)
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
      isVisible, localIOC, localComment, localActive, localSubdomains, tableName,
      show, hide, onShow, addIOC
    }
  }
}
</script>
