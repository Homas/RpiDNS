/*
 * Window Size composable for RpiDNS
 * Provides reactive window dimensions and calculated values for responsive behavior
 */

import Vue from 'vue'

/**
 * Composable for tracking window size and calculating responsive values
 * Ports the update_window_size function from the original rpi_admin.js
 * 
 * @returns {Object} Reactive state with windowInnerWidth, logs_height, logs_pp
 */
export function useWindowSize() {
  // Create reactive state
  const state = Vue.observable({
    windowInnerWidth: window.innerWidth,
    logs_height: 150,
    logs_pp: 5 // logs per page
  })

  /**
   * Update all window-size-dependent values
   * Ported from original update_window_size function
   */
  const updateWindowSize = () => {
    // Calculate logs per page based on window dimensions
    // Original: logs_pp = window.innerHeight>500 && window.innerWidth>1000?Math.floor((window.innerHeight -350)/28):5
    state.logs_pp = (window.innerHeight > 500 && window.innerWidth > 1000) 
      ? Math.floor((window.innerHeight - 350) / 28) 
      : 5

    // Calculate logs container height
    // Original: logs_height = window.innerHeight>400?(window.innerHeight - 240):150
    state.logs_height = window.innerHeight > 400 
      ? (window.innerHeight - 240) 
      : 150

    // Update window width
    state.windowInnerWidth = window.innerWidth
  }

  /**
   * Initialize window size tracking
   * Call this in component's mounted hook
   */
  const initWindowSize = () => {
    updateWindowSize()
    window.addEventListener('resize', updateWindowSize)
  }

  /**
   * Clean up window size tracking
   * Call this in component's beforeDestroy hook
   */
  const destroyWindowSize = () => {
    window.removeEventListener('resize', updateWindowSize)
  }

  return {
    state,
    updateWindowSize,
    initWindowSize,
    destroyWindowSize
  }
}

// Create a singleton instance for shared state across components
let sharedInstance = null

/**
 * Get or create a shared window size instance
 * Use this when multiple components need to share the same window size state
 * 
 * @returns {Object} Shared window size composable instance
 */
export function useSharedWindowSize() {
  if (!sharedInstance) {
    sharedInstance = useWindowSize()
  }
  return sharedInstance
}

export default useWindowSize
