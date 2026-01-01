# Implementation Plan: RPZ Feeds Management

## Overview

This implementation plan breaks down the RPZ Feeds Management feature into discrete, verifiable tasks. The approach is incremental: starting with backend infrastructure, then frontend components, followed by integration and testing. Each task builds on previous work and can be verified independently.

## Tasks

- [x] 1. Create BindConfigManager PHP class
  - [x] 1.1 Create BindConfigManager class with config file detection
    - Create `/www/rpi_admin/BindConfigManager.php`
    - Implement constructor that detects named.conf vs named.conf.options
    - Implement `getConfigPath()` method
    - _Requirements: 9.4_

  - [x] 1.2 Implement TSIG key extraction
    - Implement `getTsigKeyName()` method to parse TSIG key from config
    - Handle various TSIG key definition formats
    - Return null if no key found
    - _Requirements: 2.1_

  - [ ]* 1.3 Write property test for TSIG key extraction
    - **Property 2: TSIG key extraction consistency**
    - **Validates: Requirements 2.1, 2.7**

  - [x] 1.4 Implement feed parsing from config
    - Implement `getFeeds()` method to extract all configured feeds
    - Parse response-policy statement for feed order and actions
    - Parse zone definitions for feed metadata
    - Determine source type (ioc2rpz, local, third-party) from zone config
    - _Requirements: 1.1, 1.2_

  - [x] 1.5 Implement configuration backup and restore
    - Implement `backup()` method to create timestamped backup
    - Implement `restore()` method to restore from backup
    - _Requirements: 9.3_

  - [x] 1.6 Implement configuration validation
    - Implement `validate()` method using named-checkconf
    - Return success/failure with error details
    - _Requirements: 9.1_

  - [x] 1.7 Implement BIND reload
    - Implement `reloadBind()` method using rndc reload
    - Support both local and container deployments
    - _Requirements: 9.2, 9.4, 9.5_

- [x] 2. Checkpoint - Verify BindConfigManager
  - Ensure BindConfigManager can parse existing config
  - Test backup/restore functionality manually
  - Ask the user if questions arise

- [x] 3. Implement feed modification methods in BindConfigManager
  - [x] 3.1 Implement addFeeds method
    - Add zone configuration for new feeds
    - Add feeds to response-policy statement
    - Support ioc2rpz, local, and third-party feed types
    - Handle TSIG key configuration for zone transfers
    - _Requirements: 2.7, 3.6, 4.7_

  - [x] 3.2 Implement updateFeed method
    - Update policy action in response-policy
    - Update zone configuration for third-party feeds
    - Preserve feed order
    - _Requirements: 5.4_

  - [x] 3.3 Implement removeFeed method
    - Remove from response-policy statement
    - Remove zone configuration
    - Optionally delete zone file for local feeds
    - _Requirements: 6.2, 6.3, 6.5_

  - [ ]* 3.4 Write property test for feed removal completeness
    - **Property 8: Feed removal completeness**
    - **Validates: Requirements 6.2, 6.3**

  - [x] 3.5 Implement setFeedEnabled method
    - Comment out/uncomment feed in response-policy
    - Preserve zone configuration when disabled
    - _Requirements: 7.2, 7.3_

  - [ ]* 3.6 Write property test for enable/disable consistency
    - **Property 9: Enable/disable state consistency**
    - **Validates: Requirements 7.1, 7.2, 7.3**

  - [x] 3.7 Implement updateFeedOrder method
    - Reorder feeds in response-policy statement
    - Preserve all feed configurations
    - _Requirements: 8.2_

  - [ ]* 3.8 Write property test for feed order persistence
    - **Property 10: Feed order persistence**
    - **Validates: Requirements 8.2, 8.4**

- [x] 4. Checkpoint - Verify feed modification methods
  - Test add/update/remove operations manually
  - Verify BIND config remains valid after operations
  - Ask the user if questions arise

- [x] 5. Implement backend API endpoints
  - [x] 5.1 Enhance GET rpz_feeds endpoint
    - Use BindConfigManager to get feeds with full metadata
    - Include source type, enabled status, and order
    - _Requirements: 1.1, 1.2, 1.4_

  - [x] 5.2 Implement GET ioc2rpz_available endpoint
    - Extract TSIG key name using BindConfigManager
    - Fetch available feeds from ioc2rpz.net API
    - Mark feeds already configured
    - Handle API errors gracefully
    - _Requirements: 2.2, 2.3, 2.9, 2.10_

  - [x] 5.3 Implement POST rpz_feed endpoint
    - Accept array of feeds to add
    - Validate feed names and configurations
    - Use BindConfigManager to add feeds
    - Validate and reload BIND
    - Rollback on failure
    - _Requirements: 2.4, 2.8, 3.7, 4.7_

  - [ ]* 5.4 Write property test for DNS feed name validation
    - **Property 5: DNS feed name validation**
    - **Validates: Requirements 3.2, 4.2**

  - [ ]* 5.5 Write property test for valid policy actions
    - **Property 3: Valid policy actions**
    - **Validates: Requirements 2.5, 2.6, 3.3, 4.4**

  - [ ]* 5.6 Write property test for CNAME requires target
    - **Property 4: CNAME action requires target**
    - **Validates: Requirements 3.4, 4.5**

  - [x] 5.7 Implement PUT rpz_feed endpoint
    - Validate edit restrictions by source type
    - Use BindConfigManager to update feed
    - Validate and reload BIND
    - _Requirements: 5.2, 5.3, 5.4_

  - [ ]* 5.8 Write property test for edit restrictions
    - **Property 7: Feed edit restrictions by source type**
    - **Validates: Requirements 5.2, 5.3**

  - [x] 5.9 Implement DELETE rpz_feed endpoint
    - Use BindConfigManager to remove feed
    - Validate and reload BIND
    - _Requirements: 6.2, 6.3, 6.4_

  - [x] 5.10 Implement PUT rpz_feeds_order endpoint
    - Use BindConfigManager to update order
    - Validate and reload BIND
    - _Requirements: 8.2, 8.3_

  - [x] 5.11 Implement PUT rpz_feed_status endpoint
    - Use BindConfigManager to enable/disable
    - Validate and reload BIND
    - _Requirements: 7.1, 7.4_

  - [ ]* 5.12 Write property test for configuration validation and rollback
    - **Property 11: Configuration validation and rollback**
    - **Validates: Requirements 9.1, 9.3**

- [x] 6. Checkpoint - Verify backend API
  - Test all endpoints using curl or API client
  - Verify BIND config updates correctly
  - Ask the user if questions arise

- [x] 7. Create frontend modal components
  - [x] 7.1 Create AddIoc2rpzFeed.vue modal
    - Fetch available feeds on show
    - Display feeds with checkboxes for multi-select
    - Show feed details (name, description, type, rules_count)
    - Policy action selector with "given" default
    - Handle API errors and loading states
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6_

  - [x] 7.2 Create AddLocalFeed.vue modal
    - Feed name input with validation
    - Policy action selector with "nxdomain" default
    - CNAME target input (shown when CNAME selected)
    - Description textarea
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 7.3 Create AddThirdPartyFeed.vue modal
    - Feed name input with validation
    - Primary server input (required)
    - TSIG key name and secret inputs (optional)
    - Policy action selector with "nxdomain" default
    - CNAME target input (shown when CNAME selected)
    - Description textarea
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 7.4 Write property test for third-party TSIG configuration
    - **Property 6: Third-party feed TSIG configuration**
    - **Validates: Requirements 4.3**

  - [x] 7.5 Create EditFeed.vue modal
    - Load feed data on show
    - Conditionally show fields based on source type
    - Disable non-editable fields for ioc2rpz feeds
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 8. Enhance RpzFeeds.vue component
  - [x] 8.1 Update feed table display
    - Add columns for source type and enabled status
    - Add visual indicators for enabled/disabled
    - Add row selection for edit/delete
    - _Requirements: 1.2, 1.3, 1.4_

  - [ ]* 8.2 Write property test for feed display completeness
    - **Property 1: Feed display completeness**
    - **Validates: Requirements 1.2, 1.4, 2.3**

  - [x] 8.3 Add toolbar with action buttons
    - Add dropdown for Add (ioc2rpz, Local, Third-Party)
    - Add Edit button (disabled when no selection)
    - Add Delete button with confirmation
    - Add Enable/Disable toggle
    - Add Refresh button
    - _Requirements: 2.2, 3.1, 4.1, 5.1, 6.1, 7.1_

  - [x] 8.4 Implement drag-and-drop reordering
    - Add drag handles to table rows
    - Implement drag-and-drop event handlers
    - Call reorder API on drop
    - Show loading state during save
    - _Requirements: 8.1_

  - [x] 8.5 Wire up modal components
    - Import and register modal components
    - Implement modal show/hide methods
    - Handle refresh-feeds events
    - _Requirements: All modal-related_

- [x] 9. Checkpoint - Verify frontend functionality
  - Test all CRUD operations through UI
  - Test drag-and-drop reordering
  - Verify error handling and loading states
  - Ask the user if questions arise

- [ ] 10. Update Help documentation
  - [ ] 10.1 Add RPZ Feeds section to HelpContent.vue
    - Add navigation link in sidebar
    - Create section with same style as existing sections
    - _Requirements: 10.1_

  - [ ] 10.2 Document feed source types
    - Explain ioc2rpz.net feeds and TSIG key requirement
    - Explain local feeds and use cases
    - Explain third-party feeds and configuration
    - _Requirements: 10.2_

  - [ ] 10.3 Document feed ordering
    - Explain importance of feed order
    - Document how to reorder feeds
    - _Requirements: 10.3_

  - [ ] 10.4 Document feed management operations
    - Step-by-step for adding each feed type
    - Step-by-step for editing feeds
    - Step-by-step for removing feeds
    - Step-by-step for enabling/disabling feeds
    - _Requirements: 10.4_

  - [ ] 10.5 Document policy actions
    - Explain each action type (nxdomain, nodata, passthru, drop, CNAME, given)
    - Provide guidance on when to use each
    - _Requirements: 10.5_

  - [ ] 10.6 Document BIND configuration relationship
    - Explain how feeds relate to named.conf
    - Explain automatic reload behavior
    - _Requirements: 10.6_

- [ ] 11. Final checkpoint - Complete verification
  - Run all property tests
  - Verify help documentation is complete and consistent
  - Test full workflow end-to-end
  - Ask the user if questions arise

## Notes

- Tasks marked with `*` are optional property-based tests that can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- The implementation follows existing RpiDNS patterns (Vue 3 Composition API, PHP backend)
