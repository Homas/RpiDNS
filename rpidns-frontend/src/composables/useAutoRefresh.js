// (c) Vadim Pavlov 2020 - 2026
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

const AUTOREFRESH_INTERVAL = 60000 // 60 seconds

/**
 * Composable for autorefresh functionality
 * @param {string} storageKey - Key for localStorage to persist state
 * @param {Function} refreshFn - Function to call on refresh
 * @param {Function} isActiveFn - Function that returns true if this tab is active
 */
export function useAutoRefresh(storageKey, refreshFn, isActiveFn = () => true) {
  const autoRefreshEnabled = ref(false)
  let intervalId = null

  // Load state from localStorage
  const loadState = () => {
    try {
      const stored = localStorage.getItem(storageKey)
      if (stored !== null) {
        autoRefreshEnabled.value = stored === 'true'
      }
    } catch (e) {
      console.warn('Failed to load autorefresh state:', e)
    }
  }

  // Save state to localStorage
  const saveState = () => {
    try {
      localStorage.setItem(storageKey, autoRefreshEnabled.value.toString())
    } catch (e) {
      console.warn('Failed to save autorefresh state:', e)
    }
  }

  // Start the interval
  const startInterval = () => {
    if (intervalId) return
    intervalId = setInterval(() => {
      if (autoRefreshEnabled.value && isActiveFn()) {
        refreshFn()
      }
    }, AUTOREFRESH_INTERVAL)
  }

  // Stop the interval
  const stopInterval = () => {
    if (intervalId) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  // Watch for changes in autoRefreshEnabled - save state, manage interval, and trigger immediate refresh
  watch(autoRefreshEnabled, (newVal, oldVal) => {
    saveState()
    if (newVal) {
      startInterval()
      // Trigger immediate refresh when enabled (but not on initial load from localStorage)
      if (oldVal !== undefined && isActiveFn()) {
        refreshFn()
      }
    }
  })

  onMounted(() => {
    loadState()
    // Always start interval - it will only refresh if enabled AND active
    startInterval()
  })

  onBeforeUnmount(() => {
    stopInterval()
  })

  return {
    autoRefreshEnabled
  }
}
