/*
 * (c) Vadim Pavlov 2020 - 2026
 * API composable for RpiDNS
 * Provides methods for interacting with the PHP backend API
 */

import axios from 'axios'

const API_BASE = '/rpi_admin/rpidata.php'

/**
 * Handle 401 Unauthorized responses by redirecting to login
 * @param {Error} error - Axios error object
 */
const handleUnauthorized = (error) => {
  if (error.response && error.response.status === 401) {
    // Dispatch a custom event that App.vue can listen to
    window.dispatchEvent(new CustomEvent('session-expired'))
    // Return a rejected promise with a clear message
    return Promise.reject(new Error('Session expired. Please log in again.'))
  }
  return Promise.reject(error)
}

// Configure axios interceptor for 401 handling
axios.interceptors.response.use(
  (response) => response,
  (error) => handleUnauthorized(error)
)

/**
 * Composable for API calls to the RpiDNS backend
 * @returns {Object} API methods: get, post, put, del
 */
export function useApi() {
  /**
   * Perform a GET request
   * @param {Object} params - Query parameters
   * @returns {Promise<any>} Response data
   */
  const get = async (params) => {
    const response = await axios.get(API_BASE, { params })
    return response.data
  }

  /**
   * Perform a POST request
   * @param {Object} params - Query parameters
   * @param {Object} data - Request body data
   * @returns {Promise<any>} Response data
   */
  const post = async (params, data) => {
    const queryString = new URLSearchParams(params).toString()
    const url = queryString ? `${API_BASE}?${queryString}` : API_BASE
    const response = await axios.post(url, data)
    return response.data
  }

  /**
   * Perform a PUT request
   * @param {Object} params - Query parameters
   * @param {Object} data - Request body data
   * @returns {Promise<any>} Response data
   */
  const put = async (params, data) => {
    const queryString = new URLSearchParams(params).toString()
    const url = queryString ? `${API_BASE}?${queryString}` : API_BASE
    const response = await axios.put(url, data)
    return response.data
  }

  /**
   * Perform a DELETE request
   * @param {Object} params - Query parameters
   * @returns {Promise<any>} Response data
   */
  const del = async (params) => {
    const response = await axios.delete(API_BASE, { params })
    return response.data
  }

  /**
   * Perform a GET request for table data (used by b-table :items provider)
   * @param {Object} ctx - Bootstrap-Vue table context with apiUrl, sortBy, sortDesc
   * @returns {Promise<Array>} Table items array
   */
  const getTableData = async (ctx) => {
    const url = `${ctx.apiUrl}&sortBy=${ctx.sortBy}&sortDesc=${ctx.sortDesc}`
    try {
      const response = await axios.get(url)
      
      // Check for 401 response
      if (response.status === 401) {
        window.dispatchEvent(new CustomEvent('session-expired'))
        return { items: [], records: 0 }
      }
      
      const items = response.data.data
      // Check for HTML response (session expired - legacy check)
      if (/DOCTYPE html/.test(items)) {
        window.dispatchEvent(new CustomEvent('session-expired'))
        return { items: [], records: 0 }
      }
      return {
        items: items,
        records: parseInt(response.data.records) || 0
      }
    } catch (error) {
      // 401 errors are handled by the interceptor
      if (error.message === 'Session expired. Please log in again.') {
        return { items: [], records: 0 }
      }
      console.error('API Error:', error)
      return {
        items: [],
        records: 0
      }
    }
  }

  /**
   * Upload a file with progress tracking
   * @param {Object} params - Query parameters
   * @param {FormData} formData - Form data with file
   * @param {Function} onProgress - Progress callback (0-100)
   * @param {Object} cancelToken - Axios cancel token source
   * @returns {Promise<any>} Response data
   */
  const uploadFile = async (params, formData, onProgress, cancelToken) => {
    const queryString = new URLSearchParams(params).toString()
    const url = queryString ? `${API_BASE}?${queryString}` : API_BASE
    
    const config = {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        const progress = parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100))
        if (onProgress) {
          onProgress(progress)
        }
      }
    }
    
    if (cancelToken) {
      config.cancelToken = cancelToken.token
    }
    
    const response = await axios.post(url, formData, config)
    return response.data
  }

  /**
   * Create a cancel token for cancellable requests
   * @returns {Object} Axios cancel token source
   */
  const createCancelToken = () => {
    return axios.CancelToken.source()
  }

  /**
   * Check if an error is a cancellation
   * @param {Error} error - Error to check
   * @returns {boolean} True if error is a cancellation
   */
  const isCancel = (error) => {
    return axios.isCancel(error)
  }

  return {
    get,
    post,
    put,
    del,
    getTableData,
    uploadFile,
    createCancelToken,
    isCancel
  }
}

export default useApi
