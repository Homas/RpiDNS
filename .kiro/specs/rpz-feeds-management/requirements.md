# Requirements Document

## Introduction

This document specifies the requirements for enhancing the RpiDNS Admin RPZ Feeds page to provide comprehensive feed management capabilities. The feature enables users to add, remove, enable, disable, and reorder RPZ feeds from three sources: ioc2rpz.net (via API), local feeds, and third-party sources. The system must handle BIND configuration updates and service reload/restart operations, supporting both same-host and containerized BIND deployments. Feed order is critical as BIND validates queries against feeds in the order they are configured.

## Glossary

- **RPZ_Feed**: A Response Policy Zone feed that provides domain blocking rules to the BIND DNS server
- **ioc2rpz_API**: The external API endpoint at ioc2rpz.net that returns available feeds based on a TSIG key
- **TSIG_Key**: Transaction Signature key used for authenticating DNS zone transfers and API requests
- **BIND_Service**: The ISC BIND DNS server that processes DNS queries and applies RPZ policies
- **Feed_Source**: The origin type of a feed - either "ioc2rpz" (from ioc2rpz.net), "local" (user-defined), or "third-party" (external sources)
- **Feed_Status**: The enabled/disabled state of a feed in the BIND configuration
- **named_conf**: The BIND configuration file (named.conf or named.conf.options) containing RPZ zone definitions
- **Policy_Action**: The response action for matched queries - nxdomain, nodata, passthru, drop, CNAME (redirect), or given (use feed-defined action)

## Requirements

### Requirement 1: Display Current Feed Configuration

**User Story:** As an administrator, I want to see all currently configured RPZ feeds with their status and order, so that I can understand my current DNS protection setup.

#### Acceptance Criteria

1. WHEN the RPZ Feeds page loads, THE System SHALL display all feeds currently configured in BIND in their configured order
2. THE System SHALL show each feed's name, policy action, description, source type, and enabled/disabled status
3. THE System SHALL visually distinguish between enabled and disabled feeds
4. THE System SHALL indicate which feeds are from ioc2rpz.net, local, or third-party sources

### Requirement 2: Add ioc2rpz.net Feeds

**User Story:** As an administrator, I want to add RPZ feeds from ioc2rpz.net to my BIND configuration, so that I can protect my network from additional threat categories.

#### Acceptance Criteria

1. WHEN an administrator clicks Add ioc2rpz Feed, THE System SHALL extract the TSIG key name from the BIND configuration file
2. WHEN a valid TSIG key is found, THE System SHALL query the ioc2rpz.net API endpoint and display available feeds in a modal
3. THE System SHALL display each available feed with its name, description, type, feed_type (community/custom), and rules_count
4. THE System SHALL allow selection of one or more feeds to add simultaneously
5. THE System SHALL use the default policy action "given" (as defined by ioc2rpz.net) unless overridden by the user
6. THE System SHALL allow optional override of policy action (nxdomain, nodata, passthru, drop, CNAME, given)
7. WHEN feeds are added, THE System SHALL configure zone transfer settings using the existing TSIG key
7. WHEN feeds are added, THE System SHALL trigger a BIND configuration reload
8. IF the API request fails, THEN THE System SHALL display an error message and allow manual retry
9. IF no TSIG key is configured, THEN THE System SHALL display a message indicating ioc2rpz.net feeds are unavailable

### Requirement 3: Add Local Feeds

**User Story:** As an administrator, I want to create local RPZ feeds, so that I can define custom blocking rules specific to my network.

#### Acceptance Criteria

1. WHEN an administrator clicks Add Local Feed, THE System SHALL display a form for feed configuration
2. THE System SHALL require a unique feed name following DNS naming conventions
3. THE System SHALL allow selection of policy action (nxdomain, nodata, passthru, drop, CNAME) with nxdomain as default
4. IF CNAME action is selected, THEN THE System SHALL require a redirect target domain
5. THE System SHALL allow an optional description
6. THE System SHALL create the zone file and add the zone configuration to named_conf
7. WHEN the local feed is created, THE System SHALL trigger a BIND configuration reload

### Requirement 4: Add Third-Party Feeds

**User Story:** As an administrator, I want to add RPZ feeds from third-party sources, so that I can integrate external threat intelligence into my DNS protection.

#### Acceptance Criteria

1. WHEN an administrator clicks Add Third-Party Feed, THE System SHALL display a form for feed configuration
2. THE System SHALL require: feed name and primary server IP/hostname
3. THE System SHALL optionally accept: TSIG key name and secret for authenticated transfers
4. THE System SHALL allow selection of policy action (nxdomain, nodata, passthru, drop, CNAME) with nxdomain as default
5. IF CNAME action is selected, THEN THE System SHALL require a redirect target domain
6. THE System SHALL allow an optional description
7. WHEN the feed is added, THE System SHALL add the zone configuration to named_conf and trigger reload

### Requirement 5: Edit Feeds

**User Story:** As an administrator, I want to edit local and third-party feed configurations, so that I can update settings without removing and re-adding feeds.

#### Acceptance Criteria

1. WHEN an administrator selects a local or third-party feed and clicks Edit, THE System SHALL display the feed configuration form
2. THE System SHALL allow modification of policy action, description, and (for third-party) server settings
3. THE System SHALL NOT allow editing of ioc2rpz.net feed configurations except for policy action
4. WHEN changes are saved, THE System SHALL update the BIND configuration and trigger reload

### Requirement 6: Remove Feeds

**User Story:** As an administrator, I want to remove RPZ feeds from my configuration, so that I can stop using feeds that are no longer needed.

#### Acceptance Criteria

1. WHEN an administrator selects a feed and clicks Remove, THE System SHALL display a confirmation dialog
2. WHEN confirmed, THE System SHALL remove the feed's zone configuration from named_conf
3. THE System SHALL remove the feed from the response-policy statement
4. WHEN the feed is removed, THE System SHALL trigger a BIND configuration reload
5. IF the feed is a local feed, THEN THE System SHALL optionally delete the zone file

### Requirement 7: Enable/Disable Feeds

**User Story:** As an administrator, I want to enable or disable feeds without removing them, so that I can temporarily adjust my DNS protection without losing configuration.

#### Acceptance Criteria

1. WHEN an administrator toggles a feed's status, THE System SHALL update the feed's enabled state
2. WHEN a feed is disabled, THE System SHALL comment out or remove the feed from the response-policy statement while preserving zone configuration
3. WHEN a feed is enabled, THE System SHALL add the feed back to the response-policy statement
4. THE System SHALL trigger a BIND configuration reload after status changes

### Requirement 8: Reorder Feeds

**User Story:** As an administrator, I want to change the order of RPZ feeds, so that I can control the priority of feed evaluation.

#### Acceptance Criteria

1. THE System SHALL provide drag-and-drop or up/down controls to reorder feeds
2. WHEN feed order is changed, THE System SHALL update the response-policy statement order in named_conf
3. THE System SHALL trigger a BIND configuration reload after order changes
4. THE System SHALL preserve the new order across page reloads

### Requirement 9: BIND Service Management

**User Story:** As an administrator, I want the system to properly reload BIND after configuration changes, so that my changes take effect without manual intervention.

#### Acceptance Criteria

1. WHEN configuration changes are made, THE System SHALL validate the new configuration using named-checkconf
2. IF validation passes, THEN THE System SHALL reload BIND using rndc reload
3. IF validation fails, THEN THE System SHALL rollback changes and display the validation error
4. THE System SHALL support BIND running on the same host or in a container
5. THE System SHALL detect the BIND deployment type and use appropriate reload commands

### Requirement 10: Help Documentation

**User Story:** As an administrator, I want comprehensive help documentation for the RPZ Feeds management feature, so that I can understand how to effectively manage my feeds.

#### Acceptance Criteria

1. THE Help_Content SHALL include a dedicated section for RPZ Feeds management
2. THE Help_Content SHALL explain the three feed source types and their use cases
3. THE Help_Content SHALL explain the importance of feed order and how to reorder feeds
4. THE Help_Content SHALL provide step-by-step instructions for adding, editing, removing, and managing feeds
5. THE Help_Content SHALL document the available policy actions and when to use each
6. THE Help_Content SHALL document the relationship between feeds and BIND configuration
7. THE Help_Content SHALL maintain the same style and detail level as existing help sections
