/*
(c) Vadim Pavlov 2020
RpiDNS powered by https://ioc2rpz.net
Migrated to Vite + Vue 2
*/

import Vue from 'vue'
import { BootstrapVue, BVConfigPlugin } from 'bootstrap-vue'
import VueApexCharts from 'vue-apexcharts'

// Import CSS dependencies
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'
import '@fortawesome/fontawesome-free/css/all.css'
import './assets/css/rpi_admin.css'

// Use BootstrapVue
Vue.use(BootstrapVue)

// Register ApexCharts component globally
Vue.component('apexchart', VueApexCharts)

// Inject PHP variables (will be set by index.php via window.RPIDNS_CONFIG)
Vue.prototype.$rpiver = window.RPIDNS_CONFIG?.rpiver || ''
Vue.prototype.$assetsBy = window.RPIDNS_CONFIG?.assets_by || 'mac'
Vue.prototype.$addressType = window.RPIDNS_CONFIG?.addressType || 'MAC'

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

// Make colors available globally
Vue.prototype.$gColors = gColors

// Import root App component
import App from './App.vue'

// Create Vue app instance
new Vue({
  render: h => h(App)
}).$mount('#app')
