/*
(c) Vadim Pavlov 2020
RpiDNS powered by https://ioc2rpz.net
Migrated to Vite + Vue 3
*/

import { createApp } from 'vue'
import { createBootstrap } from 'bootstrap-vue-next'
import VueApexCharts from 'vue3-apexcharts'

// Import CSS dependencies
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue-next/dist/bootstrap-vue-next.css'
import '@fortawesome/fontawesome-free/css/all.css'
import './assets/css/rpi_admin.css'

// Import root App component
import App from './App.vue'

// Global color palette for charts
const gColors = [
  '#008FFB', '#00E396', '#FEB019', '#FF4560', '#775DD0',
  '#3F51B5', '#03A9F4', '#4CAF50', '#F9CE1D', '#FF9800',
  '#33B2DF', '#546E7A', '#D4526E', '#13D8AA', '#A5978B',
  '#4ECDC4', '#C7F464', '#81D4FA', '#546E7A', '#FD6A6A',
  '#2B908F', '#F9A3A4', '#90EE7E', '#FA4443', '#69D2E7',
  '#449DD1', '#F86624', '#EA3546', '#662E9B', '#C5D86D',
  '#D7263D', '#1B998B', '#2E294E', '#F46036', '#E2C044',
  '#662E9B', '#F86624', '#F9C80E', '#EA3546', '#43BCCD',
  '#5C4742', '#A5978B', '#8D5B4C', '#5A2A27', '#C4BBAF',
  '#A300D6', '#7D02EB', '#5653FE', '#2983FF', '#00B1F2'
]

// Create Vue 3 app instance
const app = createApp(App)

// Use bootstrap-vue-next
app.use(createBootstrap())

// Use vue3-apexcharts
app.use(VueApexCharts)

// Provide global properties (Vue 3 way to replace Vue.prototype)
app.config.globalProperties.$rpiver = window.RPIDNS_CONFIG?.rpiver || ''
app.config.globalProperties.$assetsBy = window.RPIDNS_CONFIG?.assets_by || 'mac'
app.config.globalProperties.$addressType = window.RPIDNS_CONFIG?.addressType || 'MAC'
app.config.globalProperties.$gColors = gColors

// Mount the app
app.mount('#app')
