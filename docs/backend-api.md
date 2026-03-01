# Backend API Reference

## Overview

The RpiDNS backend API is a PHP-based REST API that serves the Vue 3 frontend. All data endpoints are dispatched through `www/rpi_admin/rpidata.php` using a `METHOD request_name` pattern. Authentication is handled separately by `www/rpi_admin/auth.php`. RPZ feed management operations delegate to the `BindConfigManager` class in `www/rpi_admin/BindConfigManager.php`.

All API responses are JSON. The standard response envelope is:

```json
{
  "status": "ok" | "success" | "error" | "failed",
  "records": "count",
  "data": [ ... ],
  "reason": "error description"
}
```

## Request Dispatch

Requests are dispatched in `rpidata.php` via a `switch` on `$REQUEST['method'] . ' ' . $REQUEST['req']`. The `$REQUEST` array is built by `getRequest()` (defined in `www/rpidns_vars.php`), which merges `$_REQUEST` query/form parameters with any JSON body from `php://input` and adds the HTTP method.

### Common Parameters

These parameters are accepted by most list/query endpoints:

| Parameter | Type | Description |
|-----------|------|-------------|
| `req` | string | The request name (endpoint identifier) |
| `period` | string | Time period: `30m`, `1h`, `1d`, `1w`, `30d`, `custom` |
| `start_dt` | int | Start timestamp (Unix epoch) — required when `period=custom` |
| `end_dt` | int | End timestamp (Unix epoch) — required when `period=custom` |
| `sortBy` | string | Column to sort by (validated against allowed list) |
| `sortDesc` | string | `true` for descending sort |
| `pp` | int | Per-page limit (1–500, default 100) |
| `cp` | int | Current page number |
| `filter` | string | Free-text filter or `field=value` exact filter |
| `ltype` | string | Set to `stats` for aggregated/grouped results |
| `fields` | string | Comma-separated list of fields to include |

---

## API Endpoints by Category

### Query Log

| Method | Request Name | Description |
|--------|-------------|-------------|
| `GET` | `queries_raw` | Retrieve DNS query log entries |
| `GET` | `hits_raw` | Retrieve RPZ hit (blocked query) log entries |

Both endpoints support all common parameters. The response includes paginated records with a total count:

```json
{
  "status": "ok",
  "records": "1234",
  "data": [
    {
      "rowid": 1,
      "dtz": "2024-01-15T10:30:00Z",
      "client_ip": "192.168.1.100",
      "mac": "aa:bb:cc:dd:ee:ff",
      "fqdn": "example.com",
      "type": "A",
      "class": "IN",
      "options": "+E(0)",
      "server": "192.168.1.1",
      "action": "allowed",
      "cnt": "1",
      "cname": "My Device",
      "vendor": "Apple",
      "comment": ""
    }
  ]
}
```

For `hits_raw`, the fields differ slightly — `type`/`class`/`options`/`server` are replaced by `rule_type`, `rule`, and `feed`.

When `ltype=stats`, results are grouped by unique field combinations with summed counts instead of individual timestamped rows.

### Dashboard Widgets

All dashboard endpoints accept `period` (and `start_dt`/`end_dt` for custom periods). They return top-N results based on the `$dash_topx` setting (configured in [configuration-files.md](./configuration-files.md)).

| Method | Request Name | Description |
|--------|-------------|-------------|
| `GET` | `dash_topX_req` | Top requested FQDNs (allowed queries) |
| `GET` | `dash_topX_server` | Top DNS servers handling queries |
| `GET` | `dash_topX_req_type` | Top query types (A, AAAA, CNAME, etc.) |
| `GET` | `dash_topX_client` | Top clients by allowed query count |
| `GET` | `dash_topX_breq` | Top blocked FQDNs (RPZ hits) |
| `GET` | `dash_topX_bclient` | Top clients by blocked query count |
| `GET` | `dash_topX_feeds` | Top RPZ feeds by hit count |

Response format:

```json
{
  "status": "ok",
  "data": [
    { "fname": "example.com", "cnt": 42 }
  ]
}
```

For client endpoints (`dash_topX_client`, `dash_topX_bclient`), the `fname` field resolves to the asset name if available, falling back to MAC address or IP. A `mac` field is also included.

### Charts

| Method | Request Name | Description |
|--------|-------------|-------------|
| `GET` | `qps_chart` | Queries-per-minute and blocked-per-minute time series |

Returns an ApexCharts-compatible array of two series:

```json
[
  { "name": "Queries", "data": [[1705312200000, 15], [1705312260000, 22]] },
  { "name": "Blocked", "data": [[1705312200000, 3], [1705312260000, 5]] }
]
```

Timestamps are Unix epoch milliseconds. The aggregation granularity depends on the requested period (see [Aggregation Tier Selection](#time-period-aggregation-tier-selection)).

### Assets

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `GET` | `assets` | — | List all assets |
| `POST` | `assets` | `name`, `address`, `vendor`, `comment` | Create a new asset |
| `PUT` | `assets` | `id`, `name`, `address`, `vendor`, `comment` | Update an asset |
| `DELETE` | `assets` | `id` | Delete an asset |

GET response:

```json
{
  "status": "ok",
  "records": "5",
  "data": [
    {
      "rowid": 1,
      "dtz": "2024-01-15T10:30:00Z",
      "name": "My Laptop",
      "address": "192.168.1.100",
      "vendor": "Apple",
      "comment": "Work laptop"
    }
  ]
}
```

### Block List / Allow List

Both block list and allow list share the same endpoint structure. The `req` value determines which list is affected: `blacklist` maps to the `block` local RPZ zone, `whitelist` maps to the `allow` local RPZ zone.

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `GET` | `blacklist` | — | List all block list entries |
| `GET` | `whitelist` | — | List all allow list entries |
| `POST` | `blacklist` | `ioc`, `active`, `subdomains`, `comment` | Add a block list entry |
| `POST` | `whitelist` | `ioc`, `active`, `subdomains`, `comment` | Add an allow list entry |
| `PUT` | `blacklist` | `id`, `ioc`, `active`, `subdomains`, `comment` | Update a block list entry |
| `PUT` | `whitelist` | `id`, `ioc`, `active`, `subdomains`, `comment` | Update an allow list entry |
| `DELETE` | `blacklist` | `id` | Delete a block list entry |
| `DELETE` | `whitelist` | `id` | Delete an allow list entry |

The `ioc` parameter is validated as a domain name using `FILTER_VALIDATE_DOMAIN`. When `active=true`, the IOC is pushed to the BIND DNS server via `nsupdate`. When `subdomains=true`, a wildcard entry (`*.domain`) is also added.

GET response:

```json
{
  "status": "ok",
  "records": "10",
  "data": [
    {
      "rowid": 1,
      "dtz": "2024-01-15T10:30:00Z",
      "ioc": "malware.example.com",
      "comment": "Known malware domain",
      "subdomains": "1",
      "active": "1"
    }
  ]
}
```

### RPZ Feeds

RPZ feed management endpoints use the `BindConfigManager` class to parse and modify the BIND configuration file directly.

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `GET` | `rpz_feeds` | — | List all configured RPZ feeds |
| `GET` | `ioc2rpz_available` | — | Fetch available feeds from ioc2rpz.net API |
| `POST` | `rpz_feed` | `feeds[]` (JSON body) | Add one or more RPZ feeds |
| `PUT` | `rpz_feed` | `feed`, `action`, `description`, `cnameTarget`, `primaryServer`, `tsigKeyName`, `tsigAlgorithm`, `tsigKeySecret` | Update a feed's configuration |
| `DELETE` | `rpz_feed` | `feed`, `delete_zone_file` | Remove a feed |
| `PUT` | `rpz_feeds_order` | `order[]` (JSON body) | Reorder feeds in response-policy |
| `PUT` | `rpz_feed_status` | `feed`, `enabled` | Enable or disable a feed |
| `PUT` | `retransfer_feed` | `feed` | Request zone retransfer (secondary zones only) |

`GET rpz_feeds` response:

```json
{
  "status": "ok",
  "records": 6,
  "data": [
    {
      "feed": "allow.ioc2rpz.rpidns",
      "action": "passthru",
      "desc": "Local allow list",
      "source": "local",
      "enabled": true,
      "order": 1,
      "cnameTarget": null,
      "primaryServer": null,
      "tsigKeyName": null,
      "tsigAlgorithm": null
    }
  ]
}
```

All write operations (POST, PUT, DELETE) create a backup of the BIND config before modifying it, validate the result with `named-checkconf`, and reload BIND via `rndc reload`. If validation fails, the config is rolled back automatically.

### Settings

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `GET` | `RPIsettings` | — | Get current settings and table statistics |
| `PUT` | `RPIsettings` | `assets_by`, `assets_autocreate`, `hits_raw`, `hits_5m`, `hits_1h`, `hits_1d`, `queries_raw`, `queries_5m`, `queries_1h`, `queries_1d`, `dash_topx` | Update settings |

GET response includes per-table record counts, date ranges, sizes, and retention settings:

```json
{
  "status": "success",
  "retention": [
    ["queries_raw", 1048576, 50000, "2024-01-01T00:00:00Z", "2024-01-15T23:59:00Z", 14]
  ],
  "assets_by": "mac",
  "assets_autocreate": true,
  "dashboard_topx": 100
}
```

PUT writes the settings to `www/rpisettings.php` as a PHP file. Retention values are integers (days). See [configuration-files.md](./configuration-files.md) for details on the settings file format.

### Server Stats

| Method | Request Name | Description |
|--------|-------------|-------------|
| `GET` | `server_stats` | System health metrics |

Response:

```json
{
  "status": "ok",
  "records": "4",
  "data": [
    { "fname": "CPU load", "cnt": "12.5%, 10.2%, 8.1%" },
    { "fname": "Memory usage", "cnt": "45.2%" },
    { "fname": "Disk usage", "cnt": "32%" },
    { "fname": "Uptime", "cnt": "15 days 3 hours 42 min 10 sec" },
    { "fname": "Temp", "cnt": "42.5'C" }
  ]
}
```

### Downloads

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `GET` | `download` | `file` | Download a file |

Supported `file` values:

| Value | File | Content-Type |
|-------|------|-------------|
| `DB` | SQLite database (gzip compressed) | `application/gzip` |
| `CA` | ioc2rpz CA certificate | `application/x-pem-file` |
| `bind.log` | BIND general log (gzip) | `application/gzip` |
| `bind_queries.log` | BIND query log (gzip) | `application/gzip` |
| `bind_rpz.log` | BIND RPZ log (gzip) | `application/gzip` |

The response is a binary file download (not JSON).

### Import

| Method | Request Name | Parameters | Description |
|--------|-------------|------------|-------------|
| `POST` | `import` | `file` (multipart upload), `objects` | Import a database file |

Accepts SQLite, gzip-compressed SQLite, or zip-compressed SQLite files. The uploaded file is extracted to `/tmp/rpidns/` and a trigger file (`rpidns_import_ready`) is written for the import script (`scripts/import_db.php`) to process.

The `objects` parameter specifies which data categories to import.

---

## Authentication System

Authentication is handled by `www/rpi_admin/auth.php`, which defines the `AuthService` class and exposes its own set of API endpoints.

### AuthService Class

**Source:** `www/rpi_admin/auth.php`

The `AuthService` class manages user authentication, session tokens, rate limiting, and user administration. It uses the SQLite database for storage.

#### Configuration Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `SESSION_DURATION` | 86400 (24h) | Session lifetime in seconds |
| `TOKEN_LENGTH` | 32 bytes (64 hex chars) | Cryptographic session token size |
| `BCRYPT_COST` | 12 | bcrypt cost factor for password hashing |
| `MIN_PASSWORD_LENGTH` | 8 | Minimum password length (with complexity) |
| `PASSPHRASE_LENGTH` | 18 | Minimum length for passphrase (no complexity required) |
| `MAX_LOGIN_ATTEMPTS` | 5 | Failed attempts before rate limiting |
| `RATE_LIMIT_WINDOW` | 900 (15min) | Rate limit window in seconds |

#### Password Complexity Rules

Passwords must satisfy one of two policies:

1. **Standard password** (8+ characters): must contain at least one uppercase letter, one lowercase letter, one number, and one symbol.
2. **Passphrase** (18+ characters): no complexity requirements — length alone is sufficient.

#### Session Management

- Sessions are token-based using cryptographically secure random tokens (`random_bytes`).
- Tokens are stored in the `sessions` table with expiration timestamps.
- An HTTP-only cookie (`rpidns_session`) is set with `SameSite=Strict` and `Secure` (when HTTPS).
- On password change, all other sessions for the user are invalidated.
- Expired sessions are cleaned up on access.

#### Rate Limiting

- Failed login attempts are tracked per IP address in the `login_attempts` table.
- After 5 failed attempts within 15 minutes, further login attempts from that IP are blocked.
- Old attempt records are probabilistically cleaned up (1% chance per request).

#### Legacy Password Migration

The `AuthService` supports verifying passwords hashed with legacy formats from `.htpasswd` files:

- bcrypt (`$2y$`, `$2a$`, `$2b$`)
- Apache MD5 (`$apr1$`)
- SHA1 (`{SHA}`)
- Plain crypt (13-character)

On successful login with a non-bcrypt hash, the password is automatically rehashed to bcrypt.

### Auth API Endpoints

All auth endpoints are accessed via `www/rpi_admin/auth.php` with an `action` parameter.

| Action | Method | Parameters | Auth Required | Admin Required | Description |
|--------|--------|------------|---------------|----------------|-------------|
| `login` | POST | `username`, `password` | No | No | Authenticate and create session |
| `logout` | POST | — | No | No | Invalidate current session |
| `verify` | GET | — | Yes | No | Verify session and return user info |
| `change_password` | POST | `current_password`, `new_password` | Yes | No | Change own password |
| `users` | GET | — | Yes | Yes | List all users |
| `create_user` | POST | `username`, `password`, `is_admin` | Yes | Yes | Create a new user |
| `delete_user` | DELETE/POST | `user_id` | Yes | Yes | Delete a user |
| `reset_password` | POST | `user_id` | Yes | Yes | Reset a user's password (returns new random password) |

Login response:

```json
{
  "status": "success",
  "message": "Login successful",
  "token": "a1b2c3...",
  "user": {
    "id": 1,
    "username": "admin",
    "is_admin": true
  },
  "expires_at": 1705401600
}
```

The last admin account cannot be deleted (enforced server-side).

---

## BindConfigManager Class

**Source:** `www/rpi_admin/BindConfigManager.php`

The `BindConfigManager` class handles all interactions with the BIND DNS server configuration file for RPZ feed management.

### Configuration Detection

The class auto-detects the BIND config file by checking these paths in order:

1. `/etc/bind/named.conf.options`
2. `/etc/bind/named.conf`
3. `/etc/named.conf`
4. `/etc/named/named.conf`

An explicit path can be provided via the constructor for testing.

### Feed Source Types

| Source | Description | Zone Type | Example |
|--------|-------------|-----------|---------|
| `ioc2rpz` | Feeds from ioc2rpz.net | secondary | `dga.ioc2rpz` |
| `local` | Locally managed RPZ zones | primary | `block.ioc2rpz.rpidns` |
| `third-party` | External RPZ feed providers | secondary | `custom-rpz.example.com` |

Source type is determined by:
- Names containing `.ioc2rpz` (without `.rpidns`) → `ioc2rpz`
- Names containing `.rpidns` → `local`
- Primary/master zone type → `local`
- Secondary/slave zones with ioc2rpz.net IPs (`94.130.30.123`) → `ioc2rpz`
- All other secondary zones → `third-party`

### Predefined Feeds

Four local RPZ zones are predefined and cannot be deleted:

| Feed | Type | Allowed Actions |
|------|------|----------------|
| `allow.ioc2rpz.rpidns` | Allow list | `passthru` only |
| `allow-ip.ioc2rpz.rpidns` | Allow list (IP) | `passthru` only |
| `block.ioc2rpz.rpidns` | Block list | `nxdomain`, `nodata`, `drop`, `cname` |
| `block-ip.ioc2rpz.rpidns` | Block list (IP) | `nxdomain`, `nodata`, `drop`, `cname` |

### Valid Policy Actions

| Action | Description |
|--------|-------------|
| `nxdomain` | Return NXDOMAIN (domain does not exist) |
| `nodata` | Return NODATA (domain exists but no records) |
| `passthru` | Allow the query (bypass blocking) |
| `drop` | Silently drop the query |
| `cname` | Redirect to a CNAME target |
| `given` | Use the policy defined in the RPZ zone data |

### Key Methods

| Method | Description |
|--------|-------------|
| `getFeeds()` | Parse config and return all RPZ feeds with order, action, source, enabled state |
| `addFeeds(array $feeds)` | Add one or more feeds (zone config + response-policy entry) |
| `updateFeed(string $name, array $config)` | Update a feed's action, description, or server config |
| `removeFeed(string $name, bool $deleteZoneFile)` | Remove a feed from config (predefined feeds cannot be removed) |
| `updateFeedOrder(array $order)` | Reorder feeds in the response-policy statement |
| `setFeedEnabled(string $name, bool $enabled)` | Enable/disable a feed by commenting/uncommenting in response-policy |
| `retransferZone(string $name)` | Request zone retransfer via `rndc retransfer` (secondary zones only) |
| `getTsigKeyName()` | Extract the TSIG key name from config |
| `getTsigKeyConfig(string $name)` | Get full TSIG key details (name, algorithm, secret) |
| `backup()` | Create a timestamped backup in `/opt/rpidns/backups/bind/` |
| `restore(string $path)` | Restore config from a backup file |
| `validate()` | Validate config using `named-checkconf` |
| `reloadBind()` | Validate then reload BIND via `rndc reload` |

### Backup and Restore

- Backups are stored in `/opt/rpidns/backups/bind/` with timestamped filenames.
- A maximum of 10 backups are retained; older ones are automatically cleaned up.
- Every write operation (add, update, remove, reorder, enable/disable) creates a backup before modifying the config.
- If `named-checkconf` validation fails after a change, the config is automatically rolled back to the backup.

### Deployment Detection

The class detects whether BIND runs locally or in a Docker container:
- Checks for `/.dockerenv` file
- Checks `/proc/1/cgroup` for Docker/LXC indicators
- Checks `BIND_CONTAINER_NAME` environment variable

For container deployments, `rndc` commands are executed via `docker exec`.

---

## Time-Period Aggregation Tier Selection

The API uses a multi-tier aggregation strategy to balance query performance with data granularity. When a time period is requested, the API selects which database tables to query based on the duration. See [database.md](./database.md) for table schemas.

### Predefined Periods

| Period | Duration | Primary Table | Chart Grouping |
|--------|----------|---------------|----------------|
| `30m` | 1,800s | `_raw` | per minute |
| `1h` | 3,600s | `_5m` | per minute |
| `1d` | 86,400s | `_1h` | 30-minute buckets |
| `1w` | 604,800s | `_1d` | 6-hour buckets |
| `30d` | 2,592,000s | `_1d` | 24-hour buckets |

### Custom Periods

For `period=custom`, the tier is selected based on the duration (`end_dt - start_dt`):

| Duration | Primary Table | Supplementary Tables |
|----------|---------------|---------------------|
| ≤ 1 hour | `_raw` | — |
| ≤ 1 day | `_5m` | `_raw` (for data newer than last 5m aggregation) |
| ≤ 7 days | `_1h` | `_5m` + `_raw` (for recent unaggregated data) |
| > 7 days | `_1d` | `_1h` + `_5m` + `_raw` (cascading fill) |

### Union Query Strategy

For periods longer than 1 hour, the API uses `UNION` queries to combine data from multiple tiers. This ensures that recently ingested raw data (not yet aggregated) is included alongside pre-aggregated summaries. Each tier contributes data that is newer than the maximum timestamp in the next-higher aggregation tier.

For example, a 1-day query combines:
1. `queries_raw` rows where `dt > max(dt) from queries_5m` (unaggregated recent data)
2. `queries_5m` rows where `dt > max(dt) from queries_1h` (5-minute summaries not yet rolled into hourly)
3. `queries_1h` rows for the full period (hourly summaries)

---

## Database Migration System

**Source:** `www/rpi_admin/db_migrate.php`

The `DbMigration` class handles incremental schema upgrades using a versioned migration pattern.

### Schema Versioning

The current schema version is tracked in two places:
- `PRAGMA user_version` — SQLite built-in version pragma
- `schema_version` table — records each migration with a timestamp

The target version is defined by the `DBVersion` constant in `www/rpidns_vars.php` (currently `2`).

### Migration Process

1. `getSchemaVersion()` reads the current version from `schema_version` table (falling back to `PRAGMA user_version`).
2. `migrate()` runs all pending migrations sequentially from `currentVersion + 1` to `targetVersion`.
3. Each migration runs in a transaction — on failure, the transaction is rolled back.
4. Migration methods follow the naming convention `migrateV{from}ToV{to}()` (e.g., `migrateV1ToV2()`).
5. After each successful migration, the version is recorded in both `schema_version` and `PRAGMA user_version`.

### Available Migrations

| Migration | Description |
|-----------|-------------|
| `migrateV1ToV2` | Creates authentication tables (`users`, `sessions`, `login_attempts`) with indexes. Imports existing users from `.htpasswd` if present. Creates a default admin user if no users are imported. |

### htpasswd Import

During the v1→v2 migration, the system attempts to import users from `/opt/rpidns/conf/.htpasswd`:
- The first imported user is granted admin privileges.
- Password hashes are preserved as-is (bcrypt, Apache MD5, SHA1, crypt) and will be rehashed to bcrypt on first successful login.
- If no `.htpasswd` file exists, a default `admin` user is created with a random 16-character password written to `/opt/rpidns/conf/default_credentials.txt`.

### CLI Usage

The migration can be run manually from the command line:

```bash
php www/rpi_admin/db_migrate.php
```

The `AuthService` also triggers migration checks automatically on instantiation.

---

## Related Documentation

- [Architecture Overview](./architecture.md) — system architecture and data flow
- [Database Schema](./database.md) — table definitions, indexes, and aggregation tiers
- [Configuration Files](./configuration-files.md) — `rpidns_vars.php`, `rpisettings.php`, and environment variables
- [Scripts](./scripts.md) — maintenance scripts including `parse_bind_logs.php` and `clean_db.php`
- [BIND Configuration](./bind-configuration.md) — `named.conf` structure and RPZ zone setup
- [Frontend](./frontend.md) — Vue 3 components that consume these API endpoints
- [Docker Deployment](./docker-deployment.md) — container configuration, volumes, and deployment procedures
- [README](../README.md) — project overview and getting started
