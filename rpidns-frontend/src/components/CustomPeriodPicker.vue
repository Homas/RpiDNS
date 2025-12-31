<template>
  <BModal 
    v-model="isVisible"
    centered 
    title="Select Custom Period" 
    id="mCustomPeriod" 
    body-class="pt-2 pb-2"
    @show="onShow"
    @hidden="onCancel"
  >
    <BContainer fluid>
      <!-- Start Date/Time -->
      <BRow class="pb-2">
        <BCol cols="12">
          <label class="form-label mb-1"><small>Start Date & Time</small></label>
        </BCol>
        <BCol cols="7" class="pe-1">
          <BFormInput 
            v-model="startDate" 
            type="date"
            :max="maxStartDate"
          />
        </BCol>
        <BCol cols="5" class="ps-1">
          <BFormInput 
            v-model="startTime" 
            type="time"
          />
        </BCol>
      </BRow>

      <!-- End Date/Time -->
      <BRow class="pb-2">
        <BCol cols="12">
          <label class="form-label mb-1"><small>End Date & Time</small></label>
        </BCol>
        <BCol cols="7" class="pe-1">
          <BFormInput 
            v-model="endDate" 
            type="date"
            :max="todayDate"
          />
        </BCol>
        <BCol cols="5" class="ps-1">
          <BFormInput 
            v-model="endTime" 
            type="time"
          />
        </BCol>
      </BRow>

      <!-- Validation Error -->
      <BRow v-if="validationError" class="pb-2">
        <BCol cols="12">
          <BAlert variant="danger" :model-value="true" class="mb-0 py-2">
            <small>{{ validationError }}</small>
          </BAlert>
        </BCol>
      </BRow>
    </BContainer>

    <template #footer>
      <BButton variant="secondary" size="sm" @click="onCancel">
        Cancel
      </BButton>
      <BButton 
        variant="primary" 
        size="sm" 
        @click="onApply"
        :disabled="!!validationError || !isValid"
      >
        Apply
      </BButton>
    </template>
  </BModal>
</template>

<script>
import { ref, computed, watch } from 'vue'

export default {
  name: 'CustomPeriodPicker',
  props: {
    show: { type: Boolean, default: false },
    initialStart: { type: Date, default: null },
    initialEnd: { type: Date, default: null }
  },
  emits: ['update:show', 'apply', 'cancel'],
  setup(props, { emit, expose }) {
    const isVisible = ref(false)
    const startDate = ref('')
    const startTime = ref('')
    const endDate = ref('')
    const endTime = ref('')

    // Today's date for max constraint
    const todayDate = computed(() => {
      const now = new Date()
      return formatDateForInput(now)
    })

    // Max start date is the end date (if set) or today
    const maxStartDate = computed(() => {
      return endDate.value || todayDate.value
    })

    // Format date for input[type="date"]
    const formatDateForInput = (date) => {
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }

    // Format time for input[type="time"]
    const formatTimeForInput = (date) => {
      const hours = String(date.getHours()).padStart(2, '0')
      const minutes = String(date.getMinutes()).padStart(2, '0')
      return `${hours}:${minutes}`
    }

    // Parse date and time strings to Date object
    const parseDateTime = (dateStr, timeStr) => {
      if (!dateStr || !timeStr) return null
      const [year, month, day] = dateStr.split('-').map(Number)
      const [hours, minutes] = timeStr.split(':').map(Number)
      return new Date(year, month - 1, day, hours, minutes, 0)
    }

    // Convert Date to Unix timestamp (seconds)
    const toUnixTimestamp = (date) => {
      return Math.floor(date.getTime() / 1000)
    }

    // Computed start and end Date objects
    const startDateTime = computed(() => parseDateTime(startDate.value, startTime.value))
    const endDateTime = computed(() => parseDateTime(endDate.value, endTime.value))

    // Validation
    const validationError = computed(() => {
      if (!startDate.value || !startTime.value) {
        return 'Please select start date and time'
      }
      if (!endDate.value || !endTime.value) {
        return 'Please select end date and time'
      }
      
      const start = startDateTime.value
      const end = endDateTime.value
      
      if (start && end && start >= end) {
        return 'Start date/time must be before end date/time'
      }
      
      return ''
    })

    // Check if form is valid
    const isValid = computed(() => {
      return startDate.value && startTime.value && 
             endDate.value && endTime.value &&
             startDateTime.value && endDateTime.value &&
             startDateTime.value < endDateTime.value
    })

    // Initialize with default values
    const initializeDefaults = () => {
      const now = new Date()
      const oneHourAgo = new Date(now.getTime() - 60 * 60 * 1000)
      
      if (props.initialStart) {
        startDate.value = formatDateForInput(props.initialStart)
        startTime.value = formatTimeForInput(props.initialStart)
      } else {
        startDate.value = formatDateForInput(oneHourAgo)
        startTime.value = formatTimeForInput(oneHourAgo)
      }
      
      if (props.initialEnd) {
        endDate.value = formatDateForInput(props.initialEnd)
        endTime.value = formatTimeForInput(props.initialEnd)
      } else {
        endDate.value = formatDateForInput(now)
        endTime.value = formatTimeForInput(now)
      }
    }

    // Watch for show prop changes
    watch(() => props.show, (newVal) => {
      isVisible.value = newVal
    })

    // Watch for isVisible changes to emit update
    watch(isVisible, (newVal) => {
      emit('update:show', newVal)
    })

    const onShow = () => {
      initializeDefaults()
    }

    const onApply = () => {
      if (!isValid.value) return
      
      emit('apply', {
        start_dt: toUnixTimestamp(startDateTime.value),
        end_dt: toUnixTimestamp(endDateTime.value)
      })
      isVisible.value = false
    }

    const onCancel = () => {
      emit('cancel')
      isVisible.value = false
    }

    const show = () => { isVisible.value = true }
    const hide = () => { isVisible.value = false }

    expose({ show, hide })

    return {
      isVisible,
      startDate,
      startTime,
      endDate,
      endTime,
      todayDate,
      maxStartDate,
      validationError,
      isValid,
      onShow,
      onApply,
      onCancel,
      show,
      hide
    }
  }
}
</script>
