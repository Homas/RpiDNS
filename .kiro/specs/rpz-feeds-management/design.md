# Design Document: RPZ Feeds Management

## Overview

This design document describes the architecture and implementation approach for the RPZ Feeds Management feature in RpiDNS. The feature enhances the existing Admin RPZ Feeds page to provide comprehensive feed management capabilities including adding, editing, removing, enabling/disabling, and reordering feeds from three sources: ioc2rpz.net, local feeds, and third-party sources.

The implementation follows the existing RpiDNS patterns using Vue 3 Composition API for the frontend and PHP for the backend API, with direct BIND configuration file manipulation for feed management.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (Vue 3)                          │
├─────────────────────────────────────────────────────────────────┤
│  RpzFeeds.vue (Enhanced)                                        │
│  ├── Feed List with drag-drop reordering                        │
│  ├── Toolbar (Add, Edit, Delete, Enable/Disable, Refresh)       │
│  └── Modals:                                                    │
│      ├── AddIoc2rpzFeed.vue (multi-select from API)             │
│      ├── AddLocalFeed.vue                                       │
│      ├── AddThirdPartyFeed.vue                                  │
│      └── EditFeed.vue                                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Backend API (PHP)                            │
├─────────────────────────────────────────────────────────────────┤
│  rpidata.php                                                    │
│  ├── GET rpz_feeds - List configured feeds with metadata        │
│  ├── GET ioc2rpz_available - Fetch available feeds from API     │
│  ├── POST rpz_feed - Add new feed(s)                            │
│  ├── PUT rpz_feed - Update feed configuration                   │
│  ├── DELETE rpz_feed - Remove feed                              │
│  └── PUT rpz_feeds_order - Reorder feeds                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   BIND Configuration Layer                       │
├─────────────────────────────────────────────────────────────────┤
│  BindConfigManager (PHP class)                                  │
│  ├── Parse named.conf / named.conf.options                      │
│  ├── Extract TSIG key for ioc2rpz.net                           │
│  ├── Modify response-policy statement                           │
│  ├── Add/remove zone configurations                             │
│  ├── Validate with named-checkconf                              │
│  └── Reload BIND via rndc                                       │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  User    │───▶│ Frontend │───▶│ Backend  │───▶│  BIND    │
│  Action  │    │  Vue.js  │    │   PHP    │    │  Config  │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
                     │               │               │
                     │               │               ▼
                     │               │         ┌──────────┐
                     │               │         │ named-   │
                     │               │         │ checkconf│
                     │               │         └──────────┘
                     │               │               │
                     │               │               ▼
                     │               │         ┌──────────┐
                     │               │         │  rndc    │
                     │               │         │  reload  │
                     │               │         └──────────┘
                     │               │               │
                     ◀───────────────◀───────────────┘
                         Response
```

## Components and Interfaces

### Frontend Components

#### 1. RpzFeeds.vue (Enhanced)

The main component for displaying and managing RPZ feeds.

```javascript
// Component structure
{
  name: 'RpzFeeds',
  props: {
    logs_height: { type: Number, default: 150 }
  },
  emits: ['show-info'],
  
  // State
  data: {
    tableItems: [],           // Array of feed objects
    selectedFeed: null,       // Currently selected feed
    isLoading: false,
    draggedItem: null         // For drag-drop reordering
  },
  
  // Methods
  methods: {
    fetchData(),              // Load feeds from backend
    openAddIoc2rpzModal(),    // Open ioc2rpz feed selector
    openAddLocalModal(),      // Open local feed form
    openAddThirdPartyModal(), // Open third-party feed form
    openEditModal(),          // Open edit form for selected feed
    confirmDelete(),          // Show delete confirmation
    toggleFeedStatus(feed),   // Enable/disable feed
    retransferRPZ(feed),      // Request feed retransfer
    handleDragStart(event, index),
    handleDragOver(event, index),
    handleDrop(event, index),
    saveFeedOrder()           // Persist new order to backend
  }
}
```

#### 2. AddIoc2rpzFeed.vue (New Modal)

Modal for selecting and adding feeds from ioc2rpz.net.

```javascript
{
  name: 'AddIoc2rpzFeed',
  emits: ['show-info', 'refresh-feeds'],
  
  data: {
    availableFeeds: [],       // Feeds from ioc2rpz API
    selectedFeeds: [],        // User-selected feeds to add
    policyAction: 'given',    // Default action
    isLoading: false,
    error: null,
    tsigKeyFound: false
  },
  
  methods: {
    show(),                   // Open modal and fetch available feeds
    hide(),
    fetchAvailableFeeds(),    // Call backend to get ioc2rpz feeds
    toggleFeedSelection(feed),
    selectAll(),
    deselectAll(),
    addSelectedFeeds()        // Submit selected feeds to backend
  }
}
```

#### 3. AddLocalFeed.vue (New Modal)

Modal for creating local RPZ feeds.

```javascript
{
  name: 'AddLocalFeed',
  emits: ['show-info', 'refresh-feeds'],
  
  data: {
    feedName: '',
    policyAction: 'nxdomain',
    cnameTarget: '',          // Required if action is CNAME
    description: ''
  },
  
  methods: {
    show(),
    hide(),
    validateFeedName(),       // DNS naming validation
    addFeed()
  }
}
```

#### 4. AddThirdPartyFeed.vue (New Modal)

Modal for adding third-party RPZ feeds.

```javascript
{
  name: 'AddThirdPartyFeed',
  emits: ['show-info', 'refresh-feeds'],
  
  data: {
    feedName: '',
    primaryServer: '',        // IP or hostname
    tsigKeyName: '',          // Optional
    tsigKeySecret: '',        // Optional
    policyAction: 'nxdomain',
    cnameTarget: '',
    description: ''
  },
  
  methods: {
    show(),
    hide(),
    addFeed()
  }
}
```

#### 5. EditFeed.vue (New Modal)

Modal for editing existing feed configurations.

```javascript
{
  name: 'EditFeed',
  props: {
    feed: Object              // Feed to edit
  },
  emits: ['show-info', 'refresh-feeds'],
  
  data: {
    policyAction: '',
    cnameTarget: '',
    description: '',
    primaryServer: '',        // Only for third-party
    tsigKeyName: '',          // Only for third-party
    tsigKeySecret: ''         // Only for third-party
  },
  
  methods: {
    show(),
    hide(),
    onShow(),                 // Populate form with feed data
    saveFeed()
  }
}
```

### Backend API Endpoints

#### GET rpz_feeds (Enhanced)

Returns all configured feeds with extended metadata.

```php
// Request
GET /rpi_admin/rpidata.php?req=rpz_feeds

// Response
{
  "status": "ok",
  "records": 5,
  "data": [
    {
      "feed": "malicious.ioc2rpz",
      "action": "nxdomain",
      "desc": "Malicious domains feed",
      "source": "ioc2rpz",
      "enabled": true,
      "order": 1
    },
    {
      "feed": "block.ioc2rpz.rpidns",
      "action": "nxdomain",
      "desc": "Local block list",
      "source": "local",
      "enabled": true,
      "order": 2
    }
  ]
}
```

#### GET ioc2rpz_available (New)

Fetches available feeds from ioc2rpz.net API.

```php
// Request
GET /rpi_admin/rpidata.php?req=ioc2rpz_available

// Response
{
  "status": "ok",
  "tsig_key_found": true,
  "tsig_key_name": "ioc2rpz-net-abc123",
  "data": [
    {
      "rpz": "malicious.ioc2rpz",
      "description": "Malicious domains...",
      "type": "m",
      "ip": "94.130.30.123",
      "ipv6": "2a01:4f8:121:43ea::100:53",
      "feed_type": "community",
      "rules_count": "0",
      "already_configured": false
    }
  ]
}
```

#### POST rpz_feed (New)

Adds one or more new feeds.

```php
// Request
POST /rpi_admin/rpidata.php?req=rpz_feed
Content-Type: application/json

{
  "feeds": [
    {
      "feed": "malicious.ioc2rpz",
      "source": "ioc2rpz",
      "action": "given",
      "description": "Malicious domains"
    }
  ]
}

// Response
{
  "status": "success",
  "added": 1,
  "details": "Feed(s) added successfully"
}
```

#### PUT rpz_feed (New)

Updates an existing feed configuration.

```php
// Request
PUT /rpi_admin/rpidata.php?req=rpz_feed
Content-Type: application/json

{
  "feed": "malicious.ioc2rpz",
  "action": "nxdomain",
  "description": "Updated description",
  "enabled": true
}

// Response
{
  "status": "success",
  "details": "Feed updated successfully"
}
```

#### DELETE rpz_feed (New)

Removes a feed from configuration.

```php
// Request
DELETE /rpi_admin/rpidata.php?req=rpz_feed&feed=malicious.ioc2rpz

// Response
{
  "status": "success",
  "details": "Feed removed successfully"
}
```

#### PUT rpz_feeds_order (New)

Updates the order of feeds.

```php
// Request
PUT /rpi_admin/rpidata.php?req=rpz_feeds_order
Content-Type: application/json

{
  "order": ["feed1.ioc2rpz", "feed2.ioc2rpz", "block.local"]
}

// Response
{
  "status": "success",
  "details": "Feed order updated"
}
```

#### PUT rpz_feed_status (New)

Enables or disables a feed.

```php
// Request
PUT /rpi_admin/rpidata.php?req=rpz_feed_status
Content-Type: application/json

{
  "feed": "malicious.ioc2rpz",
  "enabled": false
}

// Response
{
  "status": "success",
  "details": "Feed disabled"
}
```

### BIND Configuration Manager

PHP class for managing BIND configuration files.

```php
class BindConfigManager {
    private $configPath;
    private $backupPath;
    
    public function __construct() {
        $this->configPath = file_exists('/etc/bind/named.conf.options') 
            ? '/etc/bind/named.conf.options' 
            : '/etc/bind/named.conf';
    }
    
    // Extract TSIG key name from config
    public function getTsigKeyName(): ?string
    
    // Get all configured feeds with metadata
    public function getFeeds(): array
    
    // Add feed(s) to configuration
    public function addFeeds(array $feeds): bool
    
    // Update feed configuration
    public function updateFeed(string $feedName, array $config): bool
    
    // Remove feed from configuration
    public function removeFeed(string $feedName): bool
    
    // Update feed order in response-policy
    public function updateFeedOrder(array $order): bool
    
    // Enable/disable feed
    public function setFeedEnabled(string $feedName, bool $enabled): bool
    
    // Create backup before modifications
    private function backup(): string
    
    // Restore from backup on failure
    private function restore(string $backupFile): bool
    
    // Validate configuration
    public function validate(): array // [success: bool, error: string]
    
    // Reload BIND service
    public function reloadBind(): array // [success: bool, output: string]
}
```

## Data Models

### Feed Object

```typescript
interface Feed {
  feed: string;           // Zone name (e.g., "malicious.ioc2rpz")
  action: string;         // Policy action: nxdomain|nodata|passthru|drop|cname|given
  desc: string;           // Description
  source: 'ioc2rpz' | 'local' | 'third-party';
  enabled: boolean;
  order: number;
  
  // For CNAME action
  cnameTarget?: string;
  
  // For third-party feeds
  primaryServer?: string;
  tsigKeyName?: string;
  tsigKeySecret?: string;
}
```

### ioc2rpz API Feed Object

```typescript
interface Ioc2rpzFeed {
  rpz: string;            // Zone name
  description: string;
  type: string;           // Feed type code
  ip: string;             // IPv4 server
  ipv6: string;           // IPv6 server
  feed_type: 'community' | 'custom';
  rules_count: string;
}
```

### BIND Configuration Structure

The system manages the following sections in named.conf:

```
// Response policy statement (feed order matters)
response-policy {
  zone "allow.ioc2rpz.rpidns" policy passthru;
  zone "malicious.ioc2rpz" policy given;
  zone "block.ioc2rpz.rpidns" policy nxdomain;
  // Disabled feeds are commented out:
  // zone "disabled-feed.ioc2rpz" policy nxdomain;
};

// Zone definitions
zone "malicious.ioc2rpz" {
  type slave;
  masters { 94.130.30.123; };
  file "/var/cache/bind/malicious.ioc2rpz";
};

zone "block.ioc2rpz.rpidns" {
  type master;
  file "/var/cache/bind/block.ioc2rpz.rpidns";
  allow-update { key "local-key"; };
};
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Feed Display Completeness

*For any* feed object returned by the backend, the rendered display SHALL include the feed's name, policy action, description, source type, and enabled/disabled status.

**Validates: Requirements 1.2, 1.4, 2.3**

### Property 2: TSIG Key Extraction Consistency

*For any* BIND configuration file containing a TSIG key definition in the format `key "keyname" { ... }`, the extraction function SHALL return the correct key name that matches the key used in zone transfer configurations.

**Validates: Requirements 2.1, 2.7**

### Property 3: Valid Policy Actions

*For any* feed configuration (ioc2rpz, local, or third-party), the policy action SHALL be one of: nxdomain, nodata, passthru, drop, cname, or given. For local and third-party feeds, the default SHALL be nxdomain. For ioc2rpz feeds, the default SHALL be given.

**Validates: Requirements 2.5, 2.6, 3.3, 4.4**

### Property 4: CNAME Action Requires Target

*For any* feed configuration where the policy action is CNAME, the configuration SHALL require a non-empty redirect target domain. Configurations with CNAME action and empty target SHALL be rejected.

**Validates: Requirements 3.4, 4.5**

### Property 5: DNS Feed Name Validation

*For any* string submitted as a feed name, the validation function SHALL accept only strings that conform to DNS naming conventions (alphanumeric, hyphens, dots, no leading/trailing hyphens, max 253 characters total, max 63 characters per label).

**Validates: Requirements 3.2, 4.2**

### Property 6: Third-Party Feed TSIG Configuration

*For any* third-party feed configuration, if a TSIG key name is provided, the generated zone configuration SHALL include the TSIG key reference. If no TSIG key is provided, the zone configuration SHALL omit TSIG authentication.

**Validates: Requirements 4.3**

### Property 7: Feed Edit Restrictions by Source Type

*For any* edit operation on a feed, if the feed source is "ioc2rpz", only the policy action field SHALL be modifiable. If the feed source is "local" or "third-party", policy action, description, and (for third-party) server settings SHALL be modifiable.

**Validates: Requirements 5.2, 5.3**

### Property 8: Feed Removal Completeness

*For any* feed removal operation, the feed SHALL be removed from both the response-policy statement AND the zone configuration section of named_conf. After removal, the feed SHALL NOT appear in either section.

**Validates: Requirements 6.2, 6.3**

### Property 9: Enable/Disable State Consistency

*For any* feed, when disabled, the feed SHALL NOT appear in the response-policy statement but its zone configuration SHALL be preserved. When enabled, the feed SHALL appear in the response-policy statement at its configured position.

**Validates: Requirements 7.1, 7.2, 7.3**

### Property 10: Feed Order Persistence

*For any* sequence of feed reorder operations, the order of feeds in the response-policy statement SHALL match the order specified by the user, and this order SHALL be preserved across configuration reloads.

**Validates: Requirements 8.2, 8.4**

### Property 11: Configuration Validation and Rollback

*For any* configuration change that produces an invalid BIND configuration (as determined by named-checkconf), the system SHALL rollback to the previous valid configuration and return an error. The original configuration SHALL remain unchanged.

**Validates: Requirements 9.1, 9.3**

## Error Handling

### Frontend Error Handling

1. **API Request Failures**: Display error message via `show-info` event, allow retry
2. **Validation Errors**: Highlight invalid fields, show inline error messages
3. **Network Timeouts**: Show timeout message, offer retry option
4. **Session Expiration**: Redirect to login (handled by useApi interceptor)

### Backend Error Handling

1. **Configuration Parse Errors**: Return detailed error message, no changes applied
2. **Validation Failures**: Return named-checkconf output, rollback changes
3. **BIND Reload Failures**: Return rndc output, configuration remains valid
4. **ioc2rpz API Failures**: Return error status, allow manual retry
5. **File Permission Errors**: Return specific error, suggest remediation

### Error Response Format

```json
{
  "status": "error",
  "reason": "Human-readable error message",
  "details": "Technical details for debugging",
  "code": "ERROR_CODE"
}
```

### Error Codes

| Code | Description |
|------|-------------|
| `TSIG_NOT_FOUND` | No TSIG key configured for ioc2rpz.net |
| `IOC2RPZ_API_ERROR` | Failed to fetch feeds from ioc2rpz.net |
| `CONFIG_PARSE_ERROR` | Failed to parse BIND configuration |
| `CONFIG_VALIDATION_FAILED` | named-checkconf validation failed |
| `BIND_RELOAD_FAILED` | rndc reload command failed |
| `FEED_NOT_FOUND` | Specified feed not found in configuration |
| `FEED_ALREADY_EXISTS` | Feed with same name already configured |
| `INVALID_FEED_NAME` | Feed name doesn't follow DNS naming conventions |
| `PERMISSION_DENIED` | Insufficient permissions for file operation |

## Testing Strategy

### Unit Tests

Unit tests verify specific examples and edge cases:

1. **Feed Name Validation**: Test DNS naming convention validation with valid/invalid examples
2. **TSIG Key Extraction**: Test parsing various BIND config formats
3. **Policy Action Parsing**: Test all action types including CNAME with target
4. **Configuration Generation**: Test zone and response-policy statement generation
5. **Default Action Values**: Test that ioc2rpz defaults to "given", others to "nxdomain"

### Property-Based Tests

Property-based tests verify universal properties across all inputs using fast-check (JavaScript) for frontend and a PHP property testing approach for backend. Each property test will run minimum 100 iterations.

| Property | Test Description | Tag |
|----------|------------------|-----|
| Property 1 | Feed display completeness | **Feature: rpz-feeds-management, Property 1: Feed display completeness** |
| Property 2 | TSIG key extraction consistency | **Feature: rpz-feeds-management, Property 2: TSIG key extraction** |
| Property 3 | Valid policy actions | **Feature: rpz-feeds-management, Property 3: Valid policy actions** |
| Property 4 | CNAME action requires target | **Feature: rpz-feeds-management, Property 4: CNAME requires target** |
| Property 5 | DNS feed name validation | **Feature: rpz-feeds-management, Property 5: DNS name validation** |
| Property 6 | Third-party TSIG configuration | **Feature: rpz-feeds-management, Property 6: Third-party TSIG config** |
| Property 7 | Feed edit restrictions by source | **Feature: rpz-feeds-management, Property 7: Edit restrictions** |
| Property 8 | Feed removal completeness | **Feature: rpz-feeds-management, Property 8: Removal completeness** |
| Property 9 | Enable/disable state consistency | **Feature: rpz-feeds-management, Property 9: Enable/disable consistency** |
| Property 10 | Feed order persistence | **Feature: rpz-feeds-management, Property 10: Order persistence** |
| Property 11 | Configuration validation and rollback | **Feature: rpz-feeds-management, Property 11: Validation rollback** |

### Integration Tests

1. **End-to-end feed addition**: Add feed via UI, verify BIND config updated
2. **Feed removal with reload**: Remove feed, verify BIND reloads successfully
3. **Order change persistence**: Reorder feeds, reload page, verify order preserved
4. **Error recovery**: Simulate validation failure, verify rollback works
5. **ioc2rpz API integration**: Verify API fetch and feed selection workflow
