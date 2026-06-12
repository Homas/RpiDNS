<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Add Indicator" 
    id="mAddIOC" 
    body-class="pt-0 pb-0" 
    ok-title="Add" 
    @ok="addIOC"
    @shown="onShow"
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
              v-model="localComment" 
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
        <BRow class="pb-1">
          <BCol md="12" class="p-0 text-left">
            <label class="form-label mb-1">Expiration</label>
            <BFormSelect v-model="localExpiryMode" :options="expiryOptions" size="sm" />
          </BCol>
        </BRow>
        <BRow v-if="localExpiryMode === 'seconds'" class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput
              v-model.number="localExpirySeconds"
              type="number"
              min="1"
              placeholder="Number of seconds until auto-disable"
              v-b-tooltip.hover
              title="Seconds from now until the indicator is auto-disabled"
            />
          </BCol>
        </BRow>
        <BRow v-if="localExpiryMode === 'datetime'" class="pb-1">
          <BCol md="12" class="p-0">
            <BFormInput
              v-model="localExpiryDate"
              type="datetime-local"
              v-b-tooltip.hover
              title="Date/time when the indicator is auto-disabled (local time)"
            />
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
    expiresDt: { type: Number, default: 0 },
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
    const localExpiryMode = ref('never')
    const localExpirySeconds = ref(null)
    const localExpiryDate = ref('')

    const expiryOptions = [
      { value: 'never', text: 'Permanent' },
      { value: 'seconds', text: 'Expire after (seconds)' },
      { value: 'datetime', text: 'Expire at date/time' }
    ]

    const tableName = computed(() => props.iocType === 'bl' ? 'blacklist' : 'whitelist')

    const toLocalInput = (epoch) => {
      const d = new Date(epoch * 1000)
      const pad = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
    }

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    const onShow = () => {
      localIOC.value = props.ioc
      localComment.value = props.comment
      localActive.value = props.active
      localSubdomains.value = props.subdomains
      localExpirySeconds.value = null
      if (props.expiresDt && props.expiresDt > 0) {
        localExpiryMode.value = 'datetime'
        localExpiryDate.value = toLocalInput(props.expiresDt)
      } else {
        localExpiryMode.value = 'never'
        localExpiryDate.value = ''
      }
    }

    const addIOC = async (event) => {
      event.preventDefault()

      let expires = 0
      if (localExpiryMode.value === 'seconds' && localExpirySeconds.value > 0) {
        expires = Math.floor(Date.now() / 1000) + parseInt(localExpirySeconds.value, 10)
      } else if (localExpiryMode.value === 'datetime' && localExpiryDate.value) {
        expires = Math.floor(new Date(localExpiryDate.value).getTime() / 1000)
      }

      const data = {
        id: props.rowid,
        ioc: localIOC.value,
        ltype: props.iocType,
        active: localActive.value,
        subdomains: localSubdomains.value,
        comment: localComment.value,
        expires_dt: expires
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
      localExpiryMode, localExpirySeconds, localExpiryDate, expiryOptions,
      show, hide, onShow, addIOC
    }
  }
}
</script>
