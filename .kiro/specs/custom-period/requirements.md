# Requirements Document

## Introduction

This document specifies the requirements for implementing custom period functionality in the RpiDNS application. The feature allows users to select a custom date/time range for viewing Dashboard, Query Log, and RPZ Hits data instead of being limited to predefined periods (30m, 1h, 1d, 1w, 30d).

## Glossary

- **Custom_Period_Selector**: A UI component that allows users to select start and end date/time for data queries
- **Dashboard**: The main overview tab showing TopX statistics and QPS charts
- **Query_Log**: The tab displaying DNS query logs with filtering and pagination
- **RPZ_Hits**: The tab displaying blocked DNS requests (RPZ hits) with filtering and pagination
- **Aggregation_Level**: The granularity of log data storage (raw, 5m, 1h, 1d)
- **Period**: A time range used to filter log data
- **Backend_API**: The PHP endpoint (rpidata.php) that serves data to the frontend

## Requirements

### Requirement 1: Custom Period UI Component

**User Story:** As a user, I want to select a custom date/time range, so that I can view logs and statistics for any specific time period.

#### Acceptance Criteria

1. WHEN a user clicks the "custom" period button, THE Custom_Period_Selector SHALL display a date/time picker interface
2. THE Custom_Period_Selector SHALL allow selection of start date and time
3. THE Custom_Period_Selector SHALL allow selection of end date and time
4. WHEN start or end date/time is changed, THE Custom_Period_Selector SHALL validate that start is before end
5. IF start date/time is after end date/time, THEN THE Custom_Period_Selector SHALL display a validation error and prevent data fetch
6. THE Custom_Period_Selector SHALL provide an "Apply" button to confirm the selection
7. WHEN the "Apply" button is clicked with valid dates, THE Custom_Period_Selector SHALL trigger a data refresh with the selected period

### Requirement 2: Dashboard Custom Period Support

**User Story:** As a user, I want to view Dashboard statistics for a custom period, so that I can analyze DNS activity for specific time ranges.

#### Acceptance Criteria

1. WHEN a custom period is selected on Dashboard, THE Dashboard SHALL fetch TopX statistics for the specified date range
2. WHEN a custom period is selected on Dashboard, THE Dashboard SHALL fetch QPS chart data for the specified date range
3. THE Dashboard SHALL pass start and end timestamps to the Backend_API
4. WHEN navigating from Dashboard to Query_Log or RPZ_Hits, THE Dashboard SHALL pass the custom period parameters

### Requirement 3: Query Log Custom Period Support

**User Story:** As a user, I want to view Query Logs for a custom period, so that I can investigate DNS queries during specific time ranges.

#### Acceptance Criteria

1. WHEN a custom period is selected on Query_Log in "logs" view mode, THE Query_Log SHALL fetch individual log entries for the specified date range
2. WHEN a custom period is selected on Query_Log in "stats" view mode, THE Query_Log SHALL fetch aggregated statistics for the specified date range
3. THE Query_Log SHALL pass start and end timestamps to the Backend_API for both logs and stats modes
4. WHEN switching between "logs" and "stats" view modes, THE Query_Log SHALL preserve the custom period selection
5. THE Query_Log SHALL display timestamp column in "logs" mode for custom period queries

### Requirement 4: RPZ Hits Custom Period Support

**User Story:** As a user, I want to view RPZ Hits for a custom period, so that I can investigate blocked requests during specific time ranges.

#### Acceptance Criteria

1. WHEN a custom period is selected on RPZ_Hits in "logs" view mode, THE RPZ_Hits SHALL fetch individual hit entries for the specified date range
2. WHEN a custom period is selected on RPZ_Hits in "stats" view mode, THE RPZ_Hits SHALL fetch aggregated statistics for the specified date range
3. THE RPZ_Hits SHALL pass start and end timestamps to the Backend_API for both logs and stats modes
4. WHEN switching between "logs" and "stats" view modes, THE RPZ_Hits SHALL preserve the custom period selection
5. THE RPZ_Hits SHALL display timestamp column in "logs" mode for custom period queries

### Requirement 5: Backend API Custom Period Support

**User Story:** As a developer, I want the backend to support custom period queries, so that the frontend can request data for arbitrary date ranges.

#### Acceptance Criteria

1. WHEN the Backend_API receives start_dt and end_dt parameters, THE Backend_API SHALL filter data by the specified date range
2. THE Backend_API SHALL accept start_dt and end_dt as Unix timestamps
3. THE Backend_API SHALL support custom period for queries_raw endpoint
4. THE Backend_API SHALL support custom period for hits_raw endpoint
5. THE Backend_API SHALL support custom period for all dash_topX endpoints
6. THE Backend_API SHALL support custom period for qps_chart endpoint

### Requirement 6: Aggregation Level Selection

**User Story:** As a user, I want the system to automatically select the appropriate data aggregation level, so that I get the most detailed data available for my selected period.

#### Acceptance Criteria

1. WHEN the custom period duration is less than or equal to 1 hour, THE Backend_API SHALL use raw data tables
2. WHEN the custom period duration is greater than 1 hour and less than or equal to 1 day, THE Backend_API SHALL use 5-minute aggregated tables combined with raw data
3. WHEN the custom period duration is greater than 1 day and less than or equal to 7 days, THE Backend_API SHALL use 1-hour aggregated tables combined with more granular data
4. WHEN the custom period duration is greater than 7 days, THE Backend_API SHALL use 1-day aggregated tables combined with more granular data
5. THE Backend_API SHALL combine data from multiple aggregation levels to provide complete coverage for the requested period

### Requirement 7: Period State Persistence

**User Story:** As a user, I want my custom period selection to persist when switching between tabs, so that I can compare data across different views.

#### Acceptance Criteria

1. WHEN a custom period is selected and user navigates to another tab, THE system SHALL preserve the custom period selection
2. WHEN navigating from Dashboard to Query_Log via click actions, THE system SHALL pass the current period (including custom) to the target tab
3. WHEN navigating from Dashboard to RPZ_Hits via click actions, THE system SHALL pass the current period (including custom) to the target tab
