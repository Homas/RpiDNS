# Requirements Document

## Introduction

This document specifies the requirements for migrating the RpiDNS web UI from a legacy Vue 2 + Bootstrap-Vue setup (loaded via CDN/local scripts) to a modern Vite-based build system with Vue 3 and bootstrap-vue-next. The migration will be performed in two phases: first migrating to Vite while keeping Vue 2, then upgrading to Vue 3 with bootstrap-vue-next.

## Glossary

- **RpiDNS**: A DNS management web application with dashboard, query logs, RPZ hits, and admin functionality
- **Vite**: A modern frontend build tool that provides fast development and optimized production builds
- **Vue_2**: The current JavaScript framework version used by the application
- **Vue_3**: The target JavaScript framework version for the final migration
- **Bootstrap_Vue**: The current Vue 2 component library for Bootstrap styling
- **Bootstrap_Vue_Next**: The Vue 3 compatible version of Bootstrap-Vue
- **Build_System**: The toolchain that compiles, bundles, and optimizes frontend assets
- **Install_Script**: The rpidns_install.sh script used for non-container deployments
- **Web_Dockerfile**: The Dockerfile for the web container (rpidns-docker/web/Dockerfile)
- **Entrypoint_Script**: The entrypoint.sh script that initializes the web container
- **PHP_Backend**: The PHP files (rpidata.php, rpisettings.php, rpidns_vars.php) that provide API endpoints
- **Static_Assets**: Built JavaScript, CSS, and font files served by the web server

## Requirements

### Requirement 1: Vite Build System Setup

**User Story:** As a developer, I want to set up a Vite-based build system, so that I can modernize the frontend toolchain while maintaining Vue 2 compatibility.

#### Acceptance Criteria

1. THE Build_System SHALL use Vite as the build tool with Vue 2 plugin support
2. THE Build_System SHALL generate production-ready static assets in a dist directory
3. THE Build_System SHALL bundle all JavaScript dependencies (Vue, Bootstrap-Vue, Axios, ApexCharts) into optimized chunks
4. THE Build_System SHALL bundle all CSS dependencies (Bootstrap, Bootstrap-Vue, FontAwesome) into optimized stylesheets
5. THE Build_System SHALL copy FontAwesome webfonts to the output directory
6. WHEN running in development mode, THE Build_System SHALL provide hot module replacement (HMR)
7. THE Build_System SHALL generate a manifest file mapping source files to output files

### Requirement 2: Project Structure Migration

**User Story:** As a developer, I want to restructure the frontend code into a standard Vite project layout, so that the codebase follows modern conventions.

#### Acceptance Criteria

1. THE Build_System SHALL use a src directory containing Vue components and entry points
2. THE Build_System SHALL maintain the existing rpi_admin.js logic as the main Vue application
3. THE Build_System SHALL preserve all existing Vue component templates from index.php
4. THE Build_System SHALL separate HTML templates from PHP server-side logic
5. WHEN building for production, THE Build_System SHALL output assets to www/rpi_admin/dist directory
6. THE Build_System SHALL configure asset paths to work with the /rpi_admin/ URL prefix

### Requirement 3: PHP Integration

**User Story:** As a developer, I want the built frontend to integrate seamlessly with the PHP backend, so that server-side variables and API endpoints continue to work.

#### Acceptance Criteria

1. THE PHP_Backend SHALL serve the built index.html with injected PHP variables
2. WHEN index.php loads, THE PHP_Backend SHALL inject rpiver, assets_by, and other configuration variables
3. THE Build_System SHALL generate assets with content hashes for cache busting
4. THE PHP_Backend SHALL reference built assets using the manifest file or predictable paths
5. THE Build_System SHALL preserve all API endpoint URLs (/rpi_admin/rpidata.php)

### Requirement 4: Local Asset Storage

**User Story:** As a system administrator, I want all external libraries stored locally, so that the application works without internet connectivity.

#### Acceptance Criteria

1. THE Build_System SHALL bundle Vue 2 library locally (no CDN references)
2. THE Build_System SHALL bundle Bootstrap-Vue library locally
3. THE Build_System SHALL bundle Bootstrap CSS locally
4. THE Build_System SHALL bundle Axios library locally
5. THE Build_System SHALL bundle ApexCharts and Vue-ApexCharts libraries locally
6. THE Build_System SHALL include FontAwesome CSS and webfonts locally
7. WHEN the application loads, THE Static_Assets SHALL NOT require any external network requests for libraries

### Requirement 5: Non-Container Deployment Update

**User Story:** As a system administrator, I want the install script updated for Vite builds, so that non-container deployments work with the new build system.

#### Acceptance Criteria

1. THE Install_Script SHALL install Node.js and npm as build dependencies
2. THE Install_Script SHALL run npm install to fetch frontend dependencies
3. THE Install_Script SHALL run npm run build to generate production assets
4. THE Install_Script SHALL copy built assets to /opt/rpidns/www/rpi_admin/dist
5. THE Install_Script SHALL NOT download individual library files via curl (replaced by npm)
6. WHEN the install completes, THE Static_Assets SHALL be ready to serve

### Requirement 6: Container Deployment Update

**User Story:** As a system administrator, I want the Docker build updated for Vite, so that container deployments include pre-built frontend assets.

#### Acceptance Criteria

1. THE Web_Dockerfile SHALL include a build stage with Node.js for frontend compilation
2. THE Web_Dockerfile SHALL run npm install and npm run build during image creation
3. THE Web_Dockerfile SHALL copy built assets to the final image
4. THE Entrypoint_Script SHALL NOT need to build frontend assets at runtime
5. WHEN the container starts, THE Static_Assets SHALL be immediately available
6. THE Web_Dockerfile SHALL use multi-stage builds to minimize final image size

### Requirement 7: Functional Parity - Dashboard

**User Story:** As a user, I want the Dashboard tab to work identically after migration, so that I can monitor DNS statistics.

#### Acceptance Criteria

1. WHEN the Dashboard loads, THE Build_System SHALL render TopX Allowed Requests table
2. WHEN the Dashboard loads, THE Build_System SHALL render TopX Allowed Clients table
3. WHEN the Dashboard loads, THE Build_System SHALL render TopX Blocked Requests table
4. WHEN the Dashboard loads, THE Build_System SHALL render TopX Blocked Clients table
5. WHEN the Dashboard loads, THE Build_System SHALL render Queries per Minute chart using ApexCharts
6. WHEN the refresh button is clicked, THE Build_System SHALL update all dashboard data
7. WHEN a period option is selected, THE Build_System SHALL filter data by the selected time range

### Requirement 8: Functional Parity - Query Log

**User Story:** As a user, I want the Query Log tab to work identically after migration, so that I can view DNS query history.

#### Acceptance Criteria

1. WHEN the Query Log tab is selected, THE Build_System SHALL display paginated query results
2. WHEN a filter is entered, THE Build_System SHALL filter query results
3. WHEN Logs/Stats toggle is changed, THE Build_System SHALL switch between log and statistics views
4. WHEN a row is clicked, THE Build_System SHALL show action popover with research links
5. THE Build_System SHALL preserve all column sorting functionality

### Requirement 9: Functional Parity - RPZ Hits

**User Story:** As a user, I want the RPZ Hits tab to work identically after migration, so that I can view blocked DNS requests.

#### Acceptance Criteria

1. WHEN the RPZ Hits tab is selected, THE Build_System SHALL display paginated hit results
2. WHEN a filter is entered, THE Build_System SHALL filter hit results
3. WHEN Logs/Stats toggle is changed, THE Build_System SHALL switch between log and statistics views
4. WHEN a row is clicked, THE Build_System SHALL show action popover with Allow/Research options
5. THE Build_System SHALL preserve all column sorting functionality

### Requirement 10: Functional Parity - Admin

**User Story:** As a user, I want the Admin tab to work identically after migration, so that I can manage assets, lists, and settings.

#### Acceptance Criteria

1. WHEN the Assets sub-tab is selected, THE Build_System SHALL display and manage network assets
2. WHEN the RPZ Feeds sub-tab is selected, THE Build_System SHALL display feed information with retransfer action
3. WHEN the Block sub-tab is selected, THE Build_System SHALL manage blacklist entries
4. WHEN the Allow sub-tab is selected, THE Build_System SHALL manage whitelist entries
5. WHEN the Settings sub-tab is selected, THE Build_System SHALL display and save retention/misc settings
6. WHEN the Tools sub-tab is selected, THE Build_System SHALL provide download links and import functionality
7. THE Build_System SHALL preserve all modal dialogs (Add Asset, Add IOC, Import DB)

### Requirement 11: Vue 3 Migration

**User Story:** As a developer, I want to migrate from Vue 2 to Vue 3, so that the application uses a supported framework version.

#### Acceptance Criteria

1. THE Build_System SHALL use Vue 3 as the JavaScript framework after Phase 2
2. THE Build_System SHALL use bootstrap-vue-next as the component library
3. THE Build_System SHALL migrate all Vue 2 Options API code to Vue 3 compatible syntax
4. THE Build_System SHALL update all Bootstrap-Vue components to bootstrap-vue-next equivalents
5. WHEN Vue 3 migration is complete, THE Build_System SHALL pass all functional parity tests
6. THE Build_System SHALL update ApexCharts integration for Vue 3 compatibility

### Requirement 12: Data Persistence

**User Story:** As a system administrator, I want database and settings files stored outside containers, so that data persists across container restarts.

#### Acceptance Criteria

1. THE Web_Dockerfile SHALL mount /opt/rpidns/www/db as a volume for SQLite database
2. THE Web_Dockerfile SHALL mount rpisettings.php as a volume for application settings
3. WHEN the container restarts, THE PHP_Backend SHALL retain all database records
4. WHEN the container restarts, THE PHP_Backend SHALL retain all application settings
5. THE Build_System SHALL NOT include database or settings files in the built assets

### Requirement 13: Incremental Testing

**User Story:** As a developer, I want to test functionality after each migration task, so that I can identify and fix issues early.

#### Acceptance Criteria

1. WHEN Vite setup is complete, THE Build_System SHALL serve the application in development mode
2. WHEN production build is complete, THE Static_Assets SHALL be servable by nginx/openresty
3. WHEN each tab migration is complete, THE Build_System SHALL maintain functional parity with the original
4. THE Build_System SHALL provide clear error messages for build failures
5. WHEN Vue 3 migration begins, THE Build_System SHALL maintain a working Vue 2 version as fallback
