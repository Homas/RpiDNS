# Implementation Plan: Help Documentation

## Overview

This implementation plan covers creating comprehensive in-app help documentation for RpiDNS. The documentation will be implemented as a new Vue component (`HelpContent.vue`) that replaces the placeholder content in the existing Help tab.

## Tasks

- [x] 1. Create HelpContent component structure
  - [x] 1.1 Create `rpidns-frontend/src/components/HelpContent.vue` with basic component structure
    - Set up template with BCard wrapper
    - Add script setup with scrollToSection method
    - Add scoped styles for help content
    - _Requirements: 1.1, 1.4, 13.1_

  - [x] 1.2 Implement table of contents navigation
    - Create navigation section with anchor links to all major sections
    - Implement smooth scroll behavior for navigation links
    - Add "Back to Top" button/link
    - _Requirements: 1.1, 1.3, 1.4_

- [x] 2. Implement Getting Started section
  - [x] 2.1 Write overview and introduction content
    - Document RpiDNS purpose (DNS-based ad-blocking and security monitoring)
    - Explain key capabilities
    - _Requirements: 2.1_

  - [x] 2.2 Document authentication and navigation
    - Write login process documentation
    - Document main tab navigation structure
    - Explain user dropdown menu (Change Password, Logout)
    - _Requirements: 2.2, 2.3, 2.4_

- [x] 3. Implement Dashboard documentation section
  - [x] 3.1 Document Dashboard widgets
    - Write documentation for all 8 widgets (TopX Allowed Requests, TopX Allowed Clients, TopX Allowed Request Types, RpiDNS Stats, TopX Blocked Requests, TopX Blocked Clients, TopX Feeds, TopX Servers)
    - Explain what each widget shows
    - _Requirements: 3.1_

  - [x] 3.2 Document time controls and refresh
    - Explain time period selection (30m, 1h, 1d, 1w, 30d, custom)
    - Document custom period picker usage
    - Explain auto-refresh toggle and manual refresh
    - _Requirements: 3.2, 3.3, 3.4_

  - [x] 3.3 Document interactive actions and chart
    - Explain popover actions (show queries, show hits, block, allow)
    - Document research links feature
    - Explain Queries per Minute chart
    - _Requirements: 3.5, 3.6_

- [x] 4. Implement Query Log documentation section
  - [x] 4.1 Document Query Log views and columns
    - Explain Logs vs Stats view toggle
    - Document all table columns with descriptions
    - Explain field selection checkboxes in Stats view
    - _Requirements: 4.1, 4.2, 4.7_

  - [x] 4.2 Document Query Log controls
    - Explain filtering functionality and filter syntax (field=value)
    - Document pagination controls
    - Explain time period selection and auto-refresh
    - _Requirements: 4.3, 4.4, 4.5, 4.6_

- [x] 5. Implement RPZ Hits documentation section
  - [x] 5.1 Document RPZ Hits views and columns
    - Explain Logs vs Stats view toggle
    - Document all table columns with descriptions
    - _Requirements: 5.1, 5.2_

  - [x] 5.2 Document RPZ Hits controls
    - Explain filtering functionality
    - Document pagination controls
    - Explain time period selection and auto-refresh
    - _Requirements: 5.3, 5.4, 5.5, 5.6_

- [x] 6. Implement Admin Panel documentation sections
  - [x] 6.1 Document Assets management
    - Explain Assets table columns
    - Document add/edit/delete operations
    - Explain search/filter functionality
    - Document MAC vs IP address tracking
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

  - [ ] 6.2 Document RPZ Feeds (comprehensive update needed)
    - Document the three feed source types (ioc2rpz.net, Local, Third-Party)
    - Explain RPZ Feeds table columns (Feed, Action, Source, Status, Description)
    - Document toolbar actions (Add dropdown, Edit, Delete, Enable/Disable, Retransfer, Refresh)
    - Explain how to add feeds from each source type with step-by-step instructions
    - Document drag-and-drop reordering and feed order importance
    - Explain all policy actions (nxdomain, nodata, passthru, drop, cname, given)
    - Document predefined feeds and their restrictions
    - Explain Retransfer is only for non-local (secondary) zones
    - Document BIND configuration relationship and automatic reload
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11_

  - [x] 6.3 Document Block List management
    - Explain Block List table columns
    - Document add/edit/delete operations
    - Explain Active and Subdomains toggles
    - Document search/filter functionality
    - _Requirements: 8.1, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8_

  - [x] 6.4 Document Allow List management
    - Explain Allow List table columns
    - Document add/edit/delete operations
    - Explain Active and Subdomains toggles
    - Document search/filter functionality
    - _Requirements: 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8_

  - [x] 6.5 Document Settings
    - Explain Data Statistics and Retention section
    - Document retention period configuration
    - Explain miscellaneous settings (auto-create assets, track by, dashboard top X)
    - Document Account Security and password change
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [x] 6.6 Document Tools
    - Explain CA Root Certificate download and purpose
    - Document Database backup and import
    - Explain ISC Bind log file downloads
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [x] 6.7 Document User Management (Admin only)
    - Note admin-only visibility
    - Document add user, reset password, delete user operations
    - Explain admin privilege indicator
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 7. Implement Common Actions section
  - [x] 7.1 Document common actions and UI controls
    - Explain how to block a domain from any view
    - Explain how to allow a domain from any view
    - Document navigation between tabs via dashboard links
    - Explain research links feature
    - Document menu collapse/expand functionality
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 8. Integrate component and finalize
  - [x] 8.1 Update App.vue to use HelpContent component
    - Import HelpContent component
    - Replace placeholder Help tab content with HelpContent component
    - _Requirements: 1.1_

  - [x] 8.2 Apply styling and accessibility
    - Ensure consistent styling with application theme
    - Verify heading hierarchy (h2, h3, h4)
    - Add appropriate icons matching application usage
    - Test responsive behavior
    - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [x] 9. Checkpoint - Manual testing and review
  - Verify all sections are present and complete
  - Test all navigation links work correctly
  - Verify content accuracy against actual application behavior
  - Test on different screen sizes
  - Check accessibility with screen reader

## Notes

- This feature is primarily static content, so no property-based tests are applicable
- Manual testing is the primary validation method for documentation accuracy
- Icons should match Font Awesome icons used throughout the application
- Content should be kept concise but comprehensive
