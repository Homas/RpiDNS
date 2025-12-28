# Design Document: Vite + Vue 3 Migration

## Overview

This design describes the migration of the RpiDNS web UI from a legacy script-based Vue 2 setup to a modern Vite-based build system. The migration follows a two-phase approach:

1. **Phase 1**: Migrate to Vite while keeping Vue 2 and Bootstrap-Vue
2. **Phase 2**: Upgrade to Vue 3 and bootstrap-vue-next

This approach minimizes risk by allowing full testing after each phase.

## Architecture

### Current Architecture

```
www/rpi_admin/
├── index.php          # PHP template with embedded Vue templates
├── rpidata.php        # PHP API endpoints
├── css/
│   ├── bootstrap.min.css
│   ├── bootstrap-vue.min.css
│   ├── all.css (FontAwesome)
│   └── rpi_admin.css
├── js/
│   ├── vue.min.js
│   ├── bootstrap-vue.min.js
│   ├── axios.min.js
│   ├── apexcharts
│   ├── vue-apexcharts
│   ├── polyfill.min.js
│   └── rpi_admin.js
└── webfonts/          # FontAwesome fonts
```

### Target Architecture (Phase 1 - Vite + Vue 2)

```
rpidns-frontend/                    # New frontend project root
├── package.json
├── vite.config.js
├── index.html                      # Development entry point
├── src/
│   ├── main.js                     # Vue app initialization
│   ├── App.vue                     # Root component
│   ├── components/
│   │   ├── Dashboard.vue
│   │   ├── QueryLog.vue
│   │   ├── RpzHits.vue
│   │   ├── Admin/
│   │   │   ├── AdminTabs.vue
│   │   │   ├── Assets.vue
│   │   │   ├── RpzFeeds.vue
│   │   │   ├── BlockList.vue
│   │   │   ├── AllowList.vue
│   │   │   ├── Settings.vue
│   │   │   └── Tools.vue
│   │   └── modals/
│   │       ├── AddAsset.vue
│   │       ├── AddIOC.vue
│   │       └── ImportDB.vue
│   ├── composables/                # Shared logic
│   │   ├── useApi.js
│   │   ├── useTableData.js
│   │   └── useWindowSize.js
│   └── assets/
│       └── css/
│           └── rpi_admin.css
├── public/
│   └── webfonts/                   # FontAwesome fonts
└── dist/                           # Build output

www/rpi_admin/
├── index.php                       # Updated to load built assets
├── rpidata.php                     # Unchanged
└── dist/                           # Copied from rpidns-frontend/dist
    ├── assets/
    │   ├── index-[hash].js
    │   ├── index-[hash].css
    │   └── vendor-[hash].js
    └── webfonts/
```

### Target Architecture (Phase 2 - Vue 3)

Same structure as Phase 1, but with:
- Vue 3 instead of Vue 2
- bootstrap-vue-next instead of bootstrap-vue
- Composition API where beneficial
- vue3-apexcharts instead of vue-apexcharts

## Components and Interfaces

### Build Configuration (vite.config.js)

```javascript
// Phase 1: Vue 2 configuration
import { defineConfig } from 'vite'
import vue2 from '@vitejs/plugin-vue2'
import path from 'path'

export default defineConfig({
  plugins: [vue2()],
  base: '/rpi_admin/dist/',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'index.html')
      },
      output: {
        manualChunks: {
          'vendor-vue': ['vue'],
          'vendor-bootstrap': ['bootstrap-vue', 'bootstrap/dist/css/bootstrap.css'],
          'vendor-charts': ['apexcharts', 'vue-apexcharts'],
          'vendor-utils': ['axios']
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  server: {
    proxy: {
      '/rpi_admin/rpidata.php': {
        target: 'http://localhost:8080',
        changeOrigin: true
      }
    }
  }
})
```

### Main Entry Point (src/main.js)

```javascript
// Phase 1: Vue 2
import Vue from 'vue'
import { BootstrapVue } from 'bootstrap-vue'
import VueApexCharts from 'vue-apexcharts'
import App from './App.vue'

// Import styles
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'
import '@fortawesome/fontawesome-free/css/all.css'
import './assets/css/rpi_admin.css'

Vue.use(BootstrapVue)
Vue.component('apexchart', VueApexCharts)

// Inject PHP variables (will be set by index.php)
Vue.prototype.$rpiver = window.RPIDNS_CONFIG?.rpiver || ''
Vue.prototype.$assetsBy = window.RPIDNS_CONFIG?.assets_by || 'mac'

new Vue({
  render: h => h(App)
}).$mount('#app')
```

### PHP Integration (www/rpi_admin/index.php)

```php
<?php
require_once "/opt/rpidns/www/rpidns_vars.php";
require_once "/opt/rpidns/www/rpisettings.php";
$AddressType = $assets_by == "mac" ? "MAC" : "IP";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RpiDNS</title>
    <link rel="stylesheet" href="/rpi_admin/dist/assets/index-[hash].css">
</head>
<body>
    <div id="app"></div>
    <script>
        window.RPIDNS_CONFIG = {
            rpiver: "<?= $rpiver ?>",
            assets_by: "<?= $assets_by ?>",
            addressType: "<?= $AddressType ?>"
        };
    </script>
    <script type="module" src="/rpi_admin/dist/assets/index-[hash].js"></script>
</body>
</html>
```

### API Service (src/composables/useApi.js)

```javascript
import axios from 'axios'

const API_BASE = '/rpi_admin/rpidata.php'

export function useApi() {
  const get = async (params) => {
    const response = await axios.get(API_BASE, { params })
    return response.data
  }

  const post = async (params, data) => {
    const response = await axios.post(`${API_BASE}?${new URLSearchParams(params)}`, data)
    return response.data
  }

  const put = async (params, data) => {
    const response = await axios.put(`${API_BASE}?${new URLSearchParams(params)}`, data)
    return response.data
  }

  const del = async (params) => {
    const response = await axios.delete(API_BASE, { params })
    return response.data
  }

  return { get, post, put, del }
}
```

## Data Models

### Application State

```javascript
// Shared state structure (preserved from original)
const appState = {
  // UI State
  toggleMenu: 0,
  cfgTab: 0,
  windowInnerWidth: 800,
  logs_height: 150,
  
  // Query Logs
  qlogs_cp: 1,
  qlogs_Filter: '',
  qlogs_nrows: 0,
  qlogs_pp: 100,
  qlogs_period: '30m',
  qlogs_fields: [],
  qlogs_select_fields: ['cname', 'server', 'fqdn', 'type', 'class', 'options', 'action'],
  query_ltype: 'logs',
  
  // RPZ Hits
  hits_cp: 1,
  hits_Filter: '',
  hits_nrows: 0,
  hits_pp: 100,
  hits_period: '30m',
  hits_fields: [],
  hits_select_fields: ['cname', 'fqdn', 'action', 'rule', 'rule_type'],
  hits_ltype: 'logs',
  
  // Dashboard
  dash_period: '30m',
  qps_series: [],
  
  // Settings
  retention: [],
  assets_by: 'mac',
  assets_autocreate: true,
  dashboard_topx: 100,
  
  // Assets
  assets_Filter: '',
  asset_selected: 0,
  
  // Block/Allow Lists
  bl_Filter: '',
  bl_selected: 0,
  wl_Filter: '',
  wl_selected: 0,
  
  // Modal State
  addAssetAddr: '',
  addAssetName: '',
  addAssetVendor: '',
  addAssetComment: '',
  addAssetRowID: 0,
  addIOC: '',
  addIOCtype: '',
  addIOCcomment: '',
  addIOCactive: true,
  addIOCsubd: true,
  addBLRowID: 0,
  
  // Upload State
  upload_file: null,
  db_import_type: [],
  upload_progress: 0
}
```

### API Response Types

```typescript
// TypeScript interfaces for documentation (implementation in JS)
interface TableResponse {
  data: any[]
  records: number
}

interface SettingsResponse {
  retention: [string, number, number, string, string, number][]
  assets_autocreate: string
  assets_by: string
  dashboard_topx: string
}

interface ApiResult {
  status: 'success' | 'error'
  reason?: string
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the prework analysis, most requirements are configuration/structure verifications (examples) or UI functional tests (manual). However, two properties can be formally tested:

### Property 1: Build Output Contains Content Hashes

*For any* production build output, all JavaScript and CSS asset filenames SHALL contain a content hash pattern (e.g., `index-[a-f0-9]+\.(js|css)`).

**Validates: Requirements 3.3**

### Property 2: No External Network Dependencies

*For any* production build output, the bundled JavaScript and CSS files SHALL NOT contain references to external CDN URLs (unpkg.com, cdn.jsdelivr.net, use.fontawesome.com, etc.).

**Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7**

## Error Handling

### Build Errors

| Error Type | Handling Strategy |
|------------|-------------------|
| Missing dependencies | npm install fails with clear error; user must run npm install |
| Invalid Vue syntax | Vite build fails with line-specific error message |
| Missing imports | Build fails with module not found error |
| CSS processing errors | Build fails with PostCSS error details |

### Runtime Errors

| Error Type | Handling Strategy |
|------------|-------------------|
| API request failure | Display error toast using existing showInfo() method |
| Invalid API response | Log error, display user-friendly message |
| Component mount failure | Vue error boundary catches and logs |
| Missing PHP config | Use default values from window.RPIDNS_CONFIG |

### Deployment Errors

| Error Type | Handling Strategy |
|------------|-------------------|
| Missing dist directory | Install script fails with clear message |
| Permission errors | Script checks and reports permission issues |
| Node.js not installed | Script detects and prompts for installation |

## Testing Strategy

### Unit Tests

Unit tests verify specific examples and edge cases:

1. **Build Configuration Tests**
   - Verify vite.config.js contains correct base path
   - Verify manifest.json is generated
   - Verify dist directory structure

2. **PHP Integration Tests**
   - Verify index.php contains RPIDNS_CONFIG injection
   - Verify asset references point to dist directory

3. **Script Tests**
   - Verify rpidns_install.sh contains npm commands
   - Verify Dockerfile contains build stage

### Property-Based Tests

Property-based tests verify universal properties across all inputs:

1. **Content Hash Property Test**
   - Generate production build
   - Scan all .js and .css files in dist/assets
   - Verify each filename matches hash pattern
   - Run minimum 100 iterations (with different build configurations if applicable)
   - Tag: **Feature: vite-vue3-migration, Property 1: Build Output Contains Content Hashes**

2. **No External URLs Property Test**
   - Generate production build
   - Read all .js and .css files in dist
   - Scan for CDN URL patterns (unpkg.com, cdn.jsdelivr.net, etc.)
   - Verify no matches found
   - Run minimum 100 iterations
   - Tag: **Feature: vite-vue3-migration, Property 2: No External Network Dependencies**

### Manual Functional Tests

These tests require browser interaction and cannot be automated with property-based testing:

1. **Dashboard Tab**
   - Load dashboard, verify all cards render
   - Click refresh, verify data updates
   - Change period, verify data filters

2. **Query Log Tab**
   - Navigate to tab, verify table loads
   - Enter filter, verify results filter
   - Toggle Logs/Stats, verify view changes

3. **RPZ Hits Tab**
   - Navigate to tab, verify table loads
   - Click row, verify popover appears
   - Use Allow action, verify modal opens

4. **Admin Tab**
   - Test each sub-tab (Assets, RPZ Feeds, Block, Allow, Settings, Tools)
   - Test CRUD operations on Assets
   - Test CRUD operations on Block/Allow lists
   - Test Settings save functionality
   - Test file download links

### Testing Framework

- **Build Tests**: Node.js scripts using fs module to verify file structure
- **Property Tests**: Jest with custom matchers for file content scanning
- **Manual Tests**: Checklist-based verification after each migration phase

### Test Execution Order

1. After Vite setup: Run build tests, verify dist output
2. After component migration: Run property tests, manual dashboard test
3. After each tab migration: Manual functional test for that tab
4. After Vue 3 migration: Full regression of all tests

