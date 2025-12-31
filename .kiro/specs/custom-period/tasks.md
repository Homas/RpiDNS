# Implementation Plan: Custom Period Feature

## Overview

This implementation plan breaks down the custom period feature into discrete tasks that can be validated incrementally. Backend and frontend changes are grouped together so each task produces a testable result.

## Tasks

- [x] 1. Create CustomPeriodPicker component
  - Create new `rpidns-frontend/src/components/CustomPeriodPicker.vue` component
  - Implement date and time input fields for start and end
  - Implement validation logic (start must be before end)
  - Implement Apply and Cancel buttons
  - Emit `apply` event with start_dt and end_dt as Unix timestamps
  - Style to match existing Bootstrap-Vue design
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ]* 1.1 Write property test for date validation
  - **Property 1: Date Validation Correctness**
  - **Validates: Requirements 1.4, 1.5**

- [x] 2. Add backend support for custom period in queries endpoint
  - Modify `www/rpi_admin/rpidata.php` to accept `start_dt` and `end_dt` parameters
  - Add new case `custom` in the period switch statement
  - Implement aggregation level selection based on duration
  - Build SQL queries using absolute timestamps instead of relative time
  - Support both logs and stats modes for queries_raw endpoint
  - _Requirements: 5.1, 5.2, 5.3, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ]* 2.1 Write property test for aggregation level selection
  - **Property 5: Aggregation Level Selection**
  - **Validates: Requirements 6.1, 6.2, 6.3, 6.4**

- [x] 3. Add backend support for custom period in hits endpoint
  - Extend custom period handling in `rpidata.php` for hits_raw endpoint
  - Implement aggregation level selection for hits tables
  - Support both logs and stats modes for hits_raw endpoint
  - _Requirements: 5.4, 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 4. Add backend support for custom period in dashboard endpoints
  - Extend custom period handling for dash_topX_req endpoint
  - Extend custom period handling for dash_topX_client endpoint
  - Extend custom period handling for dash_topX_req_type endpoint
  - Extend custom period handling for dash_topX_server endpoint
  - Extend custom period handling for dash_topX_breq endpoint
  - Extend custom period handling for dash_topX_bclient endpoint
  - Extend custom period handling for dash_topX_feeds endpoint
  - Extend custom period handling for qps_chart endpoint
  - _Requirements: 5.5, 5.6_

- [x] 5. Checkpoint - Backend validation
  - Ensure backend changes work correctly
  - Test API endpoints manually with custom period parameters
  - Verify aggregation level selection works as expected

- [x] 6. Integrate CustomPeriodPicker into Dashboard
  - Import CustomPeriodPicker component in Dashboard.vue
  - Add custom period state (customPeriodStart, customPeriodEnd, showCustomPicker)
  - Enable the "custom" button in period_options
  - Handle custom button click to show picker modal
  - Update fetchTableData to include custom period params
  - Update refreshDashQPS to include custom period params
  - Update navigation emit to pass custom period to child tabs
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ]* 6.1 Write property test for Dashboard API calls with custom period
  - **Property 3: Frontend API Calls Include Custom Period Parameters**
  - **Validates: Requirements 2.1, 2.2, 2.3**

- [x] 7. Integrate CustomPeriodPicker into QueryLog
  - Import CustomPeriodPicker component in QueryLog.vue
  - Add custom period state and props for receiving from parent
  - Enable the "custom" button in qperiod_options
  - Handle custom button click to show picker modal
  - Update apiUrl computed property to include custom period params
  - Ensure custom period persists when switching between logs/stats modes
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ]* 7.1 Write property test for QueryLog view mode preservation
  - **Property 6: View Mode Preservation**
  - **Validates: Requirements 3.4**

- [x] 8. Integrate CustomPeriodPicker into RpzHits
  - Import CustomPeriodPicker component in RpzHits.vue
  - Add custom period state and props for receiving from parent
  - Enable the "custom" button in period_options
  - Handle custom button click to show picker modal
  - Update apiUrl computed property to include custom period params
  - Ensure custom period persists when switching between logs/stats modes
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 9. Implement period state persistence in App.vue
  - Add custom period state at App level
  - Pass custom period props to Dashboard, QueryLog, RpzHits
  - Handle navigation events to preserve custom period across tabs
  - _Requirements: 7.1, 7.2, 7.3_

- [ ]* 9.1 Write property test for navigation period persistence
  - **Property 7: Navigation Period Persistence**
  - **Validates: Requirements 7.1, 7.2, 7.3**

- [x] 10. Final checkpoint - Full integration testing
  - Ensure all tests pass
  - Verify custom period works end-to-end on Dashboard
  - Verify custom period works end-to-end on QueryLog (logs and stats)
  - Verify custom period works end-to-end on RpzHits (logs and stats)
  - Verify navigation preserves custom period
  - Ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Backend tasks (2, 3, 4) are grouped before frontend integration (6, 7, 8) to allow API testing
- Checkpoints (5, 10) ensure incremental validation
- Property tests validate universal correctness properties
