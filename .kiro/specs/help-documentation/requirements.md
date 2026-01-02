# Requirements Document

## Introduction

This document defines the requirements for comprehensive in-app help documentation for RpiDNS, a DNS-based ad-blocking and security monitoring application. The documentation will be integrated into the existing Help tab and provide users with detailed guidance on all features, actions, and options available in the application.

## Glossary

- **RpiDNS**: The DNS-based ad-blocking and security monitoring application
- **Help_Tab**: The dedicated tab in the application for displaying help documentation
- **Dashboard**: The main overview screen showing DNS statistics and top requests/clients
- **Query_Log**: The tab displaying DNS query history and statistics
- **RPZ_Hits**: The tab showing blocked DNS requests (Response Policy Zone hits)
- **Admin_Panel**: The administrative section containing Assets, RPZ Feeds, Block/Allow lists, Settings, Tools, and User Management
- **IOC**: Indicator of Compromise - a domain or IP address to block or allow
- **Asset**: A network device tracked by MAC or IP address
- **RPZ_Feed**: A Response Policy Zone feed providing blocklists
- **Block_List**: User-defined list of domains/IPs to block
- **Allow_List**: User-defined list of domains/IPs to allow (whitelist)
- **Custom_Period**: User-defined date/time range for filtering data

## Requirements

### Requirement 1: Help Tab Structure

**User Story:** As a user, I want the help documentation to be well-organized with clear navigation, so that I can quickly find information about specific features.

#### Acceptance Criteria

1. THE Help_Tab SHALL display a table of contents with links to all major sections
2. THE Help_Tab SHALL organize content into logical sections matching the application's tab structure
3. WHEN a user clicks a navigation link, THE Help_Tab SHALL scroll to the corresponding section
4. THE Help_Tab SHALL include a "Back to Top" mechanism for easy navigation

### Requirement 2: Getting Started Documentation

**User Story:** As a new user, I want to understand the basics of RpiDNS, so that I can start using the application effectively.

#### Acceptance Criteria

1. THE Help_Tab SHALL include an overview section explaining RpiDNS purpose and capabilities
2. THE Help_Tab SHALL document the login process and authentication requirements
3. THE Help_Tab SHALL explain the main navigation structure (tabs and menu)
4. THE Help_Tab SHALL describe the user dropdown menu options (Change Password, Logout)

### Requirement 3: Dashboard Documentation

**User Story:** As a user, I want to understand all Dashboard features, so that I can effectively monitor my DNS activity.

#### Acceptance Criteria

1. THE Help_Tab SHALL document all eight Dashboard widgets (TopX Allowed Requests, TopX Allowed Clients, TopX Allowed Request Types, RpiDNS Stats, TopX Blocked Requests, TopX Blocked Clients, TopX Feeds, TopX Servers)
2. THE Help_Tab SHALL explain the time period selection options (30m, 1h, 1d, 1w, 30d, custom)
3. THE Help_Tab SHALL document the custom period picker functionality
4. THE Help_Tab SHALL explain the auto-refresh toggle and manual refresh button
5. THE Help_Tab SHALL document the interactive actions available on dashboard items (show queries, show hits, block, allow, research links)
6. THE Help_Tab SHALL explain the Queries per Minute chart

### Requirement 4: Query Log Documentation

**User Story:** As a user, I want to understand how to use the Query Log, so that I can analyze DNS queries on my network.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the Logs vs Stats view toggle
2. THE Help_Tab SHALL explain all table columns (Local Time, Client, Server, Request, Type, Class, Options, Count, Action)
3. THE Help_Tab SHALL document the filtering functionality and filter syntax
4. THE Help_Tab SHALL explain pagination controls
5. THE Help_Tab SHALL document the time period selection and custom period options
6. THE Help_Tab SHALL explain the auto-refresh functionality
7. WHEN documenting Stats view, THE Help_Tab SHALL explain the field selection checkboxes

### Requirement 5: RPZ Hits Documentation

**User Story:** As a user, I want to understand the RPZ Hits tab, so that I can monitor blocked DNS requests.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the Logs vs Stats view toggle
2. THE Help_Tab SHALL explain all table columns (Local Time, Client, Request, Action, Rule, Type, Count)
3. THE Help_Tab SHALL document the filtering functionality
4. THE Help_Tab SHALL explain pagination controls
5. THE Help_Tab SHALL document the time period selection and custom period options
6. THE Help_Tab SHALL explain the auto-refresh functionality

### Requirement 6: Admin Panel - Assets Documentation

**User Story:** As a user, I want to understand how to manage network assets, so that I can track devices on my network.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the Assets table columns (Address, Name, Vendor, Added, Comment)
2. THE Help_Tab SHALL explain how to add a new asset
3. THE Help_Tab SHALL explain how to edit an existing asset
4. THE Help_Tab SHALL explain how to delete an asset
5. THE Help_Tab SHALL document the search/filter functionality
6. THE Help_Tab SHALL explain the difference between MAC and IP address tracking

### Requirement 7: Admin Panel - RPZ Feeds Documentation

**User Story:** As a user, I want to understand RPZ Feeds management, so that I can control which blocklists are active and manage my DNS protection.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the three feed source types: ioc2rpz.net, Local, and Third-Party
2. THE Help_Tab SHALL explain the RPZ Feeds table columns (Feed, Action, Source, Status, Description)
3. THE Help_Tab SHALL document the toolbar actions (Add dropdown, Edit, Delete, Enable/Disable, Retransfer, Refresh)
4. THE Help_Tab SHALL explain how to add feeds from each source type with step-by-step instructions
5. THE Help_Tab SHALL document the drag-and-drop reordering functionality and explain feed order importance
6. THE Help_Tab SHALL explain all policy actions (nxdomain, nodata, passthru, drop, cname, given) and when to use each
7. THE Help_Tab SHALL document predefined feeds and their restrictions (allow feeds use passthru only, block feeds cannot use passthru/given)
8. THE Help_Tab SHALL explain that predefined feeds cannot be deleted
9. THE Help_Tab SHALL document the Retransfer action and explain it's only available for non-local (secondary) zones
10. THE Help_Tab SHALL explain the relationship between feeds and BIND configuration
11. THE Help_Tab SHALL document automatic configuration validation and rollback behavior

### Requirement 8: Admin Panel - Block/Allow Lists Documentation

**User Story:** As a user, I want to understand how to manage Block and Allow lists, so that I can customize DNS filtering.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the Block List table columns (Domain/IP, Added, Active, Subdomains, Comment)
2. THE Help_Tab SHALL document the Allow List table columns (Domain/IP, Added, Active, Subdomains, Comment)
3. THE Help_Tab SHALL explain how to add entries to Block/Allow lists
4. THE Help_Tab SHALL explain how to edit existing entries
5. THE Help_Tab SHALL explain how to delete entries
6. THE Help_Tab SHALL document the Active toggle functionality
7. THE Help_Tab SHALL document the Subdomains (*.) toggle functionality
8. THE Help_Tab SHALL explain the search/filter functionality

### Requirement 9: Admin Panel - Settings Documentation

**User Story:** As a user, I want to understand all application settings, so that I can configure RpiDNS to my needs.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the Data Statistics and Retention section
2. THE Help_Tab SHALL explain retention period configuration for each table type
3. THE Help_Tab SHALL document the "Automatically create assets" setting
4. THE Help_Tab SHALL explain the "Track assets by" setting (MAC vs IP)
5. THE Help_Tab SHALL document the "Dashboard show Top X" setting
6. THE Help_Tab SHALL explain the Account Security section and password change functionality

### Requirement 10: Admin Panel - Tools Documentation

**User Story:** As a user, I want to understand the available tools, so that I can perform maintenance and backup tasks.

#### Acceptance Criteria

1. THE Help_Tab SHALL document the CA Root Certificate download and its purpose
2. THE Help_Tab SHALL explain the Database backup download functionality
3. THE Help_Tab SHALL document the Database import functionality
4. THE Help_Tab SHALL explain the ISC Bind log file downloads (bind.log, bind_queries.log, bind_rpz.log)

### Requirement 11: Admin Panel - User Management Documentation

**User Story:** As an administrator, I want to understand user management features, so that I can manage application access.

#### Acceptance Criteria

1. THE Help_Tab SHALL document that User Management is only visible to administrators
2. THE Help_Tab SHALL explain how to add new users
3. THE Help_Tab SHALL document how to reset user passwords
4. THE Help_Tab SHALL explain how to delete users
5. THE Help_Tab SHALL document the admin privilege indicator

### Requirement 12: Common Actions Documentation

**User Story:** As a user, I want quick reference for common actions, so that I can perform tasks efficiently.

#### Acceptance Criteria

1. THE Help_Tab SHALL document how to block a domain from any view
2. THE Help_Tab SHALL document how to allow a domain from any view
3. THE Help_Tab SHALL explain how to navigate between tabs using dashboard links
4. THE Help_Tab SHALL document the research links feature for domain investigation
5. THE Help_Tab SHALL explain the menu collapse/expand functionality

### Requirement 13: Visual Design and Accessibility

**User Story:** As a user, I want the help documentation to be readable and accessible, so that I can easily consume the information.

#### Acceptance Criteria

1. THE Help_Tab SHALL use consistent styling matching the application theme
2. THE Help_Tab SHALL use appropriate heading hierarchy for screen readers
3. THE Help_Tab SHALL include visual indicators (icons) matching those used in the application
4. THE Help_Tab SHALL be responsive and readable on different screen sizes
