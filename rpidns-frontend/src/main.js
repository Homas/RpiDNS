/*
(c) Vadim Pavlov 2020 - 2026
RpiDNS powered by https://ioc2rpz.net
Migrated to Vite + Vue 3
*/

import { createApp } from 'vue'
import { createBootstrap } from 'bootstrap-vue-next'
import VueApexCharts from 'vue3-apexcharts'

// Import bootstrap-vue-next components explicitly
import {
  BContainer,
  BRow,
  BCol,
  BTabs,
  BTab,
  BCard,
  BCardBody,
  BCardHeader,
  BCardFooter,
  BCardTitle,
  BCardText,
  BCardGroup,
  BTable,
  BTableLite,
  BTableSimple,
  BThead,
  BTbody,
  BTr,
  BTh,
  BTd,
  BButton,
  BButtonGroup,
  BModal,
  BForm,
  BFormGroup,
  BFormInput,
  BFormSelect,
  BFormSelectOption,
  BFormCheckbox,
  BFormCheckboxGroup,
  BFormRadio,
  BFormRadioGroup,
  BFormTextarea,
  BFormFile,
  BInputGroup,
  BInputGroupText,
  BPagination,
  BSpinner,
  BProgress,
  BProgressBar,
  BPopover,
  BTooltip,
  BNav,
  BNavItem,
  BNavbar,
  BNavbarBrand,
  BNavbarNav,
  BNavbarToggle,
  BCollapse,
  BDropdown,
  BDropdownItem,
  BDropdownDivider,
  BBadge,
  BAlert,
  BLink,
  BImg,
  BOverlay,
  vBTooltip,
  vBPopover,
  vBToggle,
  vBModal
} from 'bootstrap-vue-next'

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

// Register bootstrap-vue-next components globally
app.component('BContainer', BContainer)
app.component('BRow', BRow)
app.component('BCol', BCol)
app.component('BTabs', BTabs)
app.component('BTab', BTab)
app.component('BCard', BCard)
app.component('BCardBody', BCardBody)
app.component('BCardHeader', BCardHeader)
app.component('BCardFooter', BCardFooter)
app.component('BCardTitle', BCardTitle)
app.component('BCardText', BCardText)
app.component('BCardGroup', BCardGroup)
app.component('BTable', BTable)
app.component('BTableLite', BTableLite)
app.component('BTableSimple', BTableSimple)
app.component('BThead', BThead)
app.component('BTbody', BTbody)
app.component('BTr', BTr)
app.component('BTh', BTh)
app.component('BTd', BTd)
app.component('BButton', BButton)
app.component('BButtonGroup', BButtonGroup)
app.component('BModal', BModal)
app.component('BForm', BForm)
app.component('BFormGroup', BFormGroup)
app.component('BFormInput', BFormInput)
app.component('BFormSelect', BFormSelect)
app.component('BFormSelectOption', BFormSelectOption)
app.component('BFormCheckbox', BFormCheckbox)
app.component('BFormCheckboxGroup', BFormCheckboxGroup)
app.component('BFormRadio', BFormRadio)
app.component('BFormRadioGroup', BFormRadioGroup)
app.component('BFormTextarea', BFormTextarea)
app.component('BFormFile', BFormFile)
app.component('BInputGroup', BInputGroup)
app.component('BInputGroupText', BInputGroupText)
app.component('BPagination', BPagination)
app.component('BSpinner', BSpinner)
app.component('BProgress', BProgress)
app.component('BProgressBar', BProgressBar)
app.component('BPopover', BPopover)
app.component('BTooltip', BTooltip)
app.component('BNav', BNav)
app.component('BNavItem', BNavItem)
app.component('BNavbar', BNavbar)
app.component('BNavbarBrand', BNavbarBrand)
app.component('BNavbarNav', BNavbarNav)
app.component('BNavbarToggle', BNavbarToggle)
app.component('BCollapse', BCollapse)
app.component('BDropdown', BDropdown)
app.component('BDropdownItem', BDropdownItem)
app.component('BDropdownDivider', BDropdownDivider)
app.component('BBadge', BBadge)
app.component('BAlert', BAlert)
app.component('BLink', BLink)
app.component('BImg', BImg)
app.component('BOverlay', BOverlay)

// Register directives globally
app.directive('b-tooltip', vBTooltip)
app.directive('b-popover', vBPopover)
app.directive('b-toggle', vBToggle)
app.directive('b-modal', vBModal)

// Use vue3-apexcharts
app.use(VueApexCharts)

// Provide global properties (Vue 3 way to replace Vue.prototype)
app.config.globalProperties.$rpiver = window.RPIDNS_CONFIG?.rpiver || ''
app.config.globalProperties.$assetsBy = window.RPIDNS_CONFIG?.assets_by || 'mac'
app.config.globalProperties.$addressType = window.RPIDNS_CONFIG?.addressType || 'MAC'
app.config.globalProperties.$gColors = gColors

// Mount the app
app.mount('#app')
