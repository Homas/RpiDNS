# Implementation Plan: Vite + Vue 3 Migration

## Overview

This implementation plan follows a two-phase approach: first migrating to Vite with Vue 2, then upgrading to Vue 3. Tasks are sequenced to allow testing after each step.

## Tasks

### Phase 1: Vite + Vue 2 Migration

- [x] 1. Initialize Vite project with Vue 2
  - [x] 1.1 Create rpidns-frontend directory and initialize package.json
    - Create directory structure: rpidns-frontend/src, rpidns-frontend/public
    - Initialize npm with package.json containing project metadata
    - _Requirements: 1.1, 2.1_
  - [x] 1.2 Install Vite and Vue 2 dependencies
    - Install vite, @vitejs/plugin-vue2, vue@2, bootstrap-vue, bootstrap
    - Install axios, apexcharts, vue-apexcharts
    - Install @fortawesome/fontawesome-free
    - _Requirements: 1.1, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [x] 1.3 Create vite.config.js with Vue 2 plugin and build settings
    - Configure base path as /rpi_admin/dist/
    - Configure output directory and asset chunking
    - Configure manifest generation
    - Set up dev server proxy for PHP API
    - _Requirements: 1.1, 1.7, 2.5, 2.6, 3.3_
  - [x] 1.4 Create index.html entry point for development
    - Create minimal HTML with #app mount point
    - Reference main.js as module script
    - _Requirements: 2.1_

- [x] 2. Create Vue application entry point
  - [x] 2.1 Create src/main.js with Vue 2 initialization
    - Import Vue, BootstrapVue, VueApexCharts
    - Import all CSS dependencies (Bootstrap, Bootstrap-Vue, FontAwesome, rpi_admin.css)
    - Register global components
    - Mount Vue app to #app
    - _Requirements: 2.2, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [x] 2.2 Copy and adapt rpi_admin.css to src/assets/css/
    - Copy existing CSS file
    - Adjust any path references if needed
    - _Requirements: 2.3_
  - [x] 2.3 Copy FontAwesome webfonts to public/webfonts/
    - Download FontAwesome package
    - Copy webfonts directory
    - _Requirements: 1.5, 4.6_

- [x] 3. Checkpoint - Verify Vite build works
  - Run npm run build and verify dist directory is created
  - Verify manifest.json is generated
  - Verify CSS and JS chunks are created with content hashes
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Create root App component with navigation
  - [x] 4.1 Create src/App.vue with main layout and tab navigation
    - Port header with RpiDNS branding
    - Port b-tabs navigation structure
    - Port menu toggle functionality
    - Set up tab switching with cfgTab state
    - _Requirements: 2.2, 2.3, 7.1_
  - [x] 4.2 Create src/composables/useApi.js for API calls
    - Implement get, post, put, delete methods
    - Use axios with /rpi_admin/rpidata.php base URL
    - _Requirements: 3.5_
  - [x] 4.3 Create src/composables/useWindowSize.js for responsive behavior
    - Port update_window_size function
    - Provide reactive windowInnerWidth and logs_height
    - _Requirements: 2.2_

- [x] 5. Migrate Dashboard component
  - [x] 5.1 Create src/components/Dashboard.vue
    - Port TopX Allowed Requests table with b-table
    - Port TopX Allowed Clients table
    - Port TopX Blocked Requests table
    - Port TopX Blocked Clients table
    - Port TopX Feeds and Servers tables
    - Port RpiDNS stats table
    - _Requirements: 7.1, 7.2, 7.3, 7.4_
  - [x] 5.2 Integrate ApexCharts for QPS chart
    - Port qps_options and qps_series configuration
    - Port refreshDashQPS method
    - _Requirements: 7.5_
  - [x] 5.3 Port dashboard refresh and period selection
    - Port refreshDash method
    - Port period_options radio group
    - _Requirements: 7.6, 7.7_

- [x] 6. Checkpoint - Test Dashboard functionality
  - Verify all dashboard tables load data
  - Verify QPS chart renders
  - Verify refresh and period selection work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Migrate Query Log component
  - [x] 7.1 Create src/components/QueryLog.vue
    - Port query log table with pagination
    - Port filter input and clear button
    - Port Logs/Stats toggle
    - Port column field definitions (qlogs_fields_logs, qlogs_fields_stats)
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 7.2 Port row click actions and popovers
    - Port action popovers with research links
    - Port Allow/Block quick actions
    - Port filter-by actions
    - _Requirements: 8.4, 8.5_

- [x] 8. Migrate RPZ Hits component
  - [x] 8.1 Create src/components/RpzHits.vue
    - Port hits table with pagination
    - Port filter input and clear button
    - Port Logs/Stats toggle
    - Port column field definitions (hits_fields_logs, hits_fields_stats)
    - _Requirements: 9.1, 9.2, 9.3_
  - [x] 8.2 Port row click actions and popovers
    - Port action popovers with research links
    - Port Allow quick action
    - Port filter-by actions
    - _Requirements: 9.4, 9.5_

- [x] 9. Checkpoint - Test Query Log and RPZ Hits
  - Verify both tables load and paginate
  - Verify filters work
  - Verify popovers appear on row click
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Migrate Admin component structure
  - [x] 10.1 Create src/components/Admin/AdminTabs.vue
    - Port admin card with nested b-tabs
    - Set up sub-tab routing
    - _Requirements: 10.1_
  - [x] 10.2 Create src/components/Admin/Assets.vue
    - Port assets table with CRUD buttons
    - Port filter input
    - Port row selection
    - _Requirements: 10.1_
  - [x] 10.3 Create src/components/Admin/RpzFeeds.vue
    - Port RPZ feeds table
    - Port retransfer action button
    - _Requirements: 10.2_

- [x] 11. Migrate Block/Allow lists
  - [x] 11.1 Create src/components/Admin/BlockList.vue
    - Port blacklist table with CRUD buttons
    - Port toggle switches for active/subdomains
    - Port filter input
    - _Requirements: 10.3_
  - [x] 11.2 Create src/components/Admin/AllowList.vue
    - Port whitelist table with CRUD buttons
    - Port toggle switches for active/subdomains
    - Port filter input
    - _Requirements: 10.4_

- [x] 12. Migrate Settings and Tools
  - [x] 12.1 Create src/components/Admin/Settings.vue
    - Port retention table with editable inputs
    - Port miscellaneous settings (assets_autocreate, assets_by, dashboard_topx)
    - Port save button with setSettings method
    - _Requirements: 10.5_
  - [x] 12.2 Create src/components/Admin/Tools.vue
    - Port CA certificate download card
    - Port database download/import card
    - Port bind logs download card
    - _Requirements: 10.6_

- [x] 13. Migrate modal dialogs
  - [x] 13.1 Create src/components/modals/AddAsset.vue
    - Port add/edit asset modal
    - Port form fields and validation
    - Port add_asset method
    - _Requirements: 10.7_
  - [x] 13.2 Create src/components/modals/AddIOC.vue
    - Port add/edit IOC modal
    - Port form fields including subdomains toggle
    - Port add_ioc method
    - _Requirements: 10.7_
  - [x] 13.3 Create src/components/modals/ImportDB.vue
    - Port import DB modal
    - Port file upload with progress
    - Port import type checkboxes
    - Port import_db and cancel_upload methods
    - _Requirements: 10.7_

- [x] 14. Checkpoint - Test Admin functionality
  - Test all admin sub-tabs render
  - Test CRUD operations on Assets
  - Test CRUD operations on Block/Allow lists
  - Test Settings save
  - Test all modal dialogs
  - Ensure all tests pass, ask the user if questions arise.

- [x] 15. Update PHP integration
  - [x] 15.1 Update www/rpi_admin/index.php to load built assets
    - Remove old script/link tags
    - Add RPIDNS_CONFIG script injection
    - Add references to dist/assets files
    - _Requirements: 3.1, 3.2, 3.4, 2.4_
  - [x] 15.2 Create build script to copy dist to www/rpi_admin/
    - Add npm script to copy dist directory
    - Ensure webfonts are included
    - _Requirements: 2.5_

- [x] 16. Update deployment scripts
  - [x] 16.1 Update scripts/rpidns_install.sh for Vite builds
    - Add Node.js/npm installation
    - Add npm install command
    - Add npm run build command
    - Remove curl commands for individual libraries
    - Add copy command for dist to /opt/rpidns/www/rpi_admin/
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [x] 16.2 Update rpidns-docker/web/Dockerfile for Vite builds
    - Add Node.js build stage
    - Add npm install and build commands
    - Copy built assets to final image
    - Use multi-stage build pattern
    - _Requirements: 6.1, 6.2, 6.3, 6.6_
  - [x] 16.3 Update rpidns-docker/web/entrypoint.sh
    - Remove any frontend build commands if present
    - Ensure static assets are served directly
    - _Requirements: 6.4_

- [x] 17. Write property tests for build output
  - [x] 17.1 Write property test for content hash verification
    - **Property 1: Build Output Contains Content Hashes**
    - **Validates: Requirements 3.3**
  - [x] 17.2 Write property test for no external URLs
    - **Property 2: No External Network Dependencies**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7**

- [x] 18. Final Phase 1 Checkpoint
  - Run full production build
  - Deploy to test environment (container or non-container)
  - Verify all tabs function correctly
  - Verify no external network requests for libraries
  - Ensure all tests pass, ask the user if questions arise.

### Phase 2: Vue 3 Migration

- [ ] 19. Upgrade to Vue 3
  - [ ] 19.1 Update package.json dependencies for Vue 3
    - Replace vue@2 with vue@3
    - Replace @vitejs/plugin-vue2 with @vitejs/plugin-vue
    - Replace bootstrap-vue with bootstrap-vue-next
    - Replace vue-apexcharts with vue3-apexcharts
    - _Requirements: 11.1, 11.2, 11.6_
  - [ ] 19.2 Update vite.config.js for Vue 3
    - Change plugin import to @vitejs/plugin-vue
    - Update any Vue 3 specific configuration
    - _Requirements: 11.1_
  - [ ] 19.3 Update src/main.js for Vue 3
    - Change to createApp() syntax
    - Update BootstrapVue plugin registration for bootstrap-vue-next
    - Update ApexCharts registration for vue3-apexcharts
    - _Requirements: 11.1, 11.2, 11.6_

- [ ] 20. Migrate components to Vue 3 syntax
  - [ ] 20.1 Update App.vue for Vue 3 and bootstrap-vue-next
    - Update template syntax for bootstrap-vue-next components
    - Update any deprecated Vue 2 patterns
    - _Requirements: 11.3, 11.4_
  - [ ] 20.2 Update Dashboard.vue for Vue 3
    - Update b-table to bootstrap-vue-next syntax
    - Update b-card, b-button components
    - Update ApexCharts component registration
    - _Requirements: 11.3, 11.4, 11.5_
  - [ ] 20.3 Update QueryLog.vue and RpzHits.vue for Vue 3
    - Update table components
    - Update popover components
    - Update pagination components
    - _Requirements: 11.3, 11.4, 11.5_
  - [ ] 20.4 Update Admin components for Vue 3
    - Update all admin sub-components
    - Update form components
    - Update modal components
    - _Requirements: 11.3, 11.4, 11.5_

- [ ] 21. Final Phase 2 Checkpoint
  - Run full production build with Vue 3
  - Deploy to test environment
  - Verify all tabs function correctly
  - Run property tests
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each checkpoint allows for testing before proceeding
- Phase 1 must be fully tested before starting Phase 2
- Keep Vue 2 version as fallback until Phase 2 is verified
- Property tests validate build output correctness
