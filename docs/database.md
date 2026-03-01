# Database Schema

## Overview

RpiDNS uses a single SQLite database file (`rpidns.sqlite`) stored at `/opt/rpidns/www/db/`. The schema is initialized by `scripts/init_db.php` and managed via a versioned migration system in `www/rpi_admin/db_migrate.php`.

The database stores DNS query logs, RPZ hit logs, network assets, local block/allow list entries, user accounts, sessions, and schema version history. Query and hit data use a multi-tier aggregation strategy (raw → 5-minute → 1-hour → 1-day) to balance storage efficiency with query performance.

Current schema version: **2** (defined as `DBVersion` in `www/rpidns_vars.php`).

---

## Tables

### DNS Query Tables

These four tables store DNS query logs at different aggregation levels. Raw data is inserted by `scripts/parse_bind_logs.php` every minute via cron. Aggregated tiers are computed in the same script after raw insertion.

#### queries_raw

Individual DNS query records parsed from BIND query logs.

| Column | Type | Purpose |
|--------|------|---------|
| `dt` | INTEGER | Unix timestamp of the query |
| `client_ip` | TEXT | IP address of the requesting client |
| `client_port` | TEXT | Source port of the client request |
| `mac` | TEXT | MAC address resolved via ARP table lookup |
| `fqdn` | TEXT | Fully qualified domain name queried |
| `type` | TEXT | DNS record type (A, AAAA, CNAME, etc.) |
| `class` | TEXT | DNS class (typically IN) |
| `options` | TEXT | Query options/flags |
| `server` | TEXT | DNS server IP that handled the query |
| `action` | TEXT | Resolution result: `allowed` or `blocked` |

#### queries_5m / queries_1h / queries_1d

Aggregated query summaries at 5-minute, 1-hour, and 1-day intervals. These share the same schema, which is identical to `queries_raw` except `client_port` is dropped and a `cnt` column is added.

| Column | Type | Purpose |
|--------|------|---------|
| `dt` | INTEGER | Unix timestamp (floored to interval boundary) |
| `client_ip` | TEXT | Client IP address |
| `mac` | TEXT | MAC address |
| `fqdn` | TEXT | Queried domain name |
| `type` | TEXT | DNS record type |
| `class` | TEXT | DNS class |
| `options` | TEXT | Query options/flags |
| `server` | TEXT | Handling DNS server |
| `action` | TEXT | `allowed` or `blocked` |
| `cnt` | INTEGER | Number of matching queries in the interval |

### RPZ Hit Tables

These four tables store RPZ (Response Policy Zone) hit records at different aggregation levels. Raw hits are inserted by `scripts/parse_bind_logs.php`; aggregated tiers are computed in the same run.

#### hits_raw

Individual RPZ policy hit records.

| Column | Type | Purpose |
|--------|------|---------|
| `dt` | INTEGER | Unix timestamp of the hit |
| `client_ip` | TEXT | IP address of the requesting client |
| `client_port` | TEXT | Source port of the client request |
| `mac` | TEXT | MAC address resolved via ARP table lookup |
| `fqdn` | TEXT | Domain name that triggered the RPZ policy |
| `action` | TEXT | Policy action applied (NXDOMAIN, CNAME, Log, etc.) |
| `rule_type` | TEXT | RPZ rule type (QNAME, IP) |
| `rule` | TEXT | The RPZ rule that matched |
| `feed` | TEXT | Name of the RPZ feed that provided the rule |

#### hits_5m / hits_1h / hits_1d

Aggregated RPZ hit summaries at 5-minute, 1-hour, and 1-day intervals. Same schema as `hits_raw` minus `client_port`, plus a `cnt` column.

| Column | Type | Purpose |
|--------|------|---------|
| `dt` | INTEGER | Unix timestamp (floored to interval boundary) |
| `client_ip` | TEXT | Client IP address |
| `mac` | TEXT | MAC address |
| `fqdn` | TEXT | Domain name that triggered the policy |
| `action` | TEXT | Policy action applied |
| `rule_type` | TEXT | RPZ rule type |
| `rule` | TEXT | Matching RPZ rule |
| `feed` | TEXT | RPZ feed name |
| `cnt` | INTEGER | Number of matching hits in the interval |

### Asset and Local Zone Tables

#### assets

Network devices discovered or manually added. Used to resolve IP addresses and MAC addresses to friendly names in query/hit displays.

| Column | Type | Purpose |
|--------|------|---------|
| `name` | TEXT | User-assigned device name |
| `address` | TEXT | IP or MAC address (unique constraint) |
| `vendor` | TEXT | Hardware vendor from IEEE OUI lookup |
| `comment` | TEXT | User-provided notes |
| `added_dt` | INTEGER | Unix timestamp when the asset was added |

Unique constraint on `address` prevents duplicate entries. Assets can be auto-created by `parse_bind_logs.php` when `$assets_autocreate` is enabled in `www/rpisettings.php`.

#### localzone

Local block/allow list entries provisioned as RPZ records into BIND via `nsupdate`.

| Column | Type | Purpose |
|--------|------|---------|
| `ioc` | TEXT | Indicator of Compromise — domain or IP (unique constraint) |
| `type` | TEXT | Record type (e.g., domain, IP) |
| `ltype` | TEXT | List type: `block`, `allow`, `block-ip`, `allow-ip` |
| `comment` | TEXT | User-provided description |
| `active` | BOOLEAN | Whether the entry is currently active |
| `subdomains` | BOOLEAN | Whether to include all subdomains |
| `added_dt` | INTEGER | Unix timestamp when the entry was added |
| `provisioned` | TEXT | Provisioning status/timestamp |

### Authentication Tables (Schema v2)

Added in the v1→v2 migration. These tables support session-based authentication with bcrypt password hashing and IP-based rate limiting.

#### users

Registered user accounts.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | INTEGER | Primary key (autoincrement) |
| `username` | TEXT | Login username (unique, not null) |
| `password_hash` | TEXT | Bcrypt password hash (cost 12) |
| `is_admin` | INTEGER | Admin flag: `1` = admin, `0` = regular user |
| `created_at` | INTEGER | Unix timestamp of account creation |
| `updated_at` | INTEGER | Unix timestamp of last modification |

A default `admin` user is created during database initialization with a random 16-character password. Credentials are written to `/opt/rpidns/conf/default_credentials.txt`.

#### sessions

Active user sessions. Tokens are cryptographically secure 64-character hex strings.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | INTEGER | Primary key (autoincrement) |
| `user_id` | INTEGER | Foreign key → `users(id)` with CASCADE delete |
| `token` | TEXT | Session token (unique, not null) |
| `created_at` | INTEGER | Unix timestamp of session creation |
| `expires_at` | INTEGER | Unix timestamp of session expiry (24h default) |
| `ip_address` | TEXT | Client IP that created the session |
| `user_agent` | TEXT | Client user agent string |

#### login_attempts

Tracks login attempts for IP-based rate limiting (max 5 failed attempts per 15-minute window).

| Column | Type | Purpose |
|--------|------|---------|
| `id` | INTEGER | Primary key (autoincrement) |
| `ip_address` | TEXT | Client IP address (not null) |
| `attempted_at` | INTEGER | Unix timestamp of the attempt (not null) |
| `success` | INTEGER | `1` = successful login, `0` = failed attempt |

#### schema_version

Tracks applied database migrations.

| Column | Type | Purpose |
|--------|------|---------|
| `version` | INTEGER | Schema version number (not null) |
| `applied_at` | INTEGER | Unix timestamp when the migration was applied (not null) |

---

## Multi-Tier Aggregation Strategy

RpiDNS uses a four-tier aggregation approach to manage storage and query performance for time-series DNS data. Both query and hit data follow the same pattern.

### Tiers

| Tier | Table Suffix | Interval | Granularity |
|------|-------------|----------|-------------|
| Raw | `_raw` | Per-event | Individual DNS queries/hits with full detail |
| 5-minute | `_5m` | 300s | Grouped by all fields, counted |
| 1-hour | `_1h` | 3600s | Grouped by all fields, counted |
| 1-day | `_1d` | 86400s | Grouped by all fields, counted |

### Aggregation Process

Aggregation runs inside `scripts/parse_bind_logs.php`, which is executed every minute by cron. After inserting new raw records, the script performs `INSERT INTO ... SELECT` statements that:

1. Floor timestamps to the interval boundary using `dt - dt % interval`
2. Group records by all non-timestamp, non-port columns
3. Count matching rows as `cnt`
4. Only aggregate records newer than the latest entry in the target tier (incremental)
5. Only aggregate complete intervals (exclude the current in-progress interval)

For example, the 5-minute aggregation:
```sql
INSERT INTO queries_5m (dt, client_ip, mac, fqdn, type, class, options, server, action, cnt)
SELECT (dt - dt % 300) AS dtz, client_ip, mac, fqdn, type, class, options, server, action,
       count(rowid) AS cnt
FROM queries_raw
WHERE dt > ifnull((SELECT max(dt)+300 FROM queries_5m), 0)
  AND dt <= ((SELECT max(dt) FROM queries_raw) - (SELECT max(dt) FROM queries_raw) % 300 - 1)
GROUP BY dtz, client_ip, mac, fqdn, type, class, options, server, action;
```

### API Tier Selection

The backend API (`www/rpi_admin/rpidata.php`) selects which tiers to query based on the requested time duration:

| Duration | Primary Tier | Fill Strategy |
|----------|-------------|---------------|
| ≤ 1 hour | `_raw` | Raw data only |
| ≤ 1 day | `_5m` | `_raw` UNION `_5m` (raw fills gap since last aggregation) |
| ≤ 7 days | `_1h` | `_raw` UNION `_5m` UNION `_1h` (cascading fill) |
| > 7 days | `_1d` | `_raw` UNION `_5m` UNION `_1h` UNION `_1d` (cascading fill) |

For periods longer than 1 hour, UNION queries combine data from multiple tiers so that recently ingested raw data (not yet aggregated) is included alongside pre-aggregated summaries. Each tier contributes data newer than the maximum timestamp in the next-higher tier.

See [backend-api.md — Aggregation Tier Selection](./backend-api.md#time-period-aggregation-tier-selection) for full details on predefined and custom period handling.

---

## Data Retention

### Configuration

Retention periods are configured in `www/rpisettings.php` as the `$retention` associative array. Values are in days.

| Table | Default Retention | Setting Key |
|-------|------------------|-------------|
| `queries_raw` | 7 days | `$retention['queries_raw']` |
| `queries_5m` | 14 days | `$retention['queries_5m']` |
| `queries_1h` | 60 days | `$retention['queries_1h']` |
| `queries_1d` | 180 days | `$retention['queries_1d']` |
| `hits_raw` | 14 days | `$retention['hits_raw']` |
| `hits_5m` | 30 days | `$retention['hits_5m']` |
| `hits_1h` | 180 days | `$retention['hits_1h']` |
| `hits_1d` | 365 days | `$retention['hits_1d']` |

Retention settings can be modified through the Admin → Settings UI, which calls the `PUT RPIsettings` API endpoint.

### Enforcement

Data cleanup is performed by `scripts/clean_db.php`, scheduled daily via cron (at 2:42 AM). For each table, it deletes rows where:

```sql
DELETE FROM {table} WHERE dt < strftime('%s', 'now') - {retention_days} * 86400;
```

A separate cron job runs `VACUUM` daily (at 3:42 AM) to reclaim disk space after deletions.

---

## Database Indexes

All indexes are created by `scripts/init_db.php` using `CREATE INDEX IF NOT EXISTS`.

### Query Table Indexes

| Index Name | Table | Column(s) | Purpose |
|-----------|-------|-----------|---------|
| `queries_raw_dt` | `queries_raw` | `dt` | Time-range filtering for log retrieval and aggregation |
| `queries_raw_client_ip` | `queries_raw` | `client_ip` | Client-based filtering and dashboard top-N queries |
| `queries_raw_fqdn` | `queries_raw` | `fqdn` | Domain name search and filtering |
| `queries_raw_server` | `queries_raw` | `server` | Server-based filtering and dashboard top-N servers |
| `queries_raw_action` | `queries_raw` | `action` | Filtering by allowed/blocked status |
| `queries_5m_dt` | `queries_5m` | `dt` | Time-range filtering |
| `queries_5m_client_ip` | `queries_5m` | `client_ip` | Client-based filtering |
| `queries_5m_fqdn` | `queries_5m` | `fqdn` | Domain name filtering |
| `queries_5m_server` | `queries_5m` | `server` | Server-based filtering |
| `queries_5m_action` | `queries_5m` | `action` | Action filtering |
| `queries_1h_dt` | `queries_1h` | `dt` | Time-range filtering |
| `queries_1h_client_ip` | `queries_1h` | `client_ip` | Client-based filtering |
| `queries_1h_fqdn` | `queries_1h` | `fqdn` | Domain name filtering |
| `queries_1h_server` | `queries_1h` | `server` | Server-based filtering |
| `queries_1h_action` | `queries_1h` | `action` | Action filtering |
| `queries_1d_dt` | `queries_1d` | `dt` | Time-range filtering |
| `queries_1d_client_ip` | `queries_1d` | `client_ip` | Client-based filtering |
| `queries_1d_fqdn` | `queries_1d` | `fqdn` | Domain name filtering |
| `queries_1d_server` | `queries_1d` | `server` | Server-based filtering |
| `queries_1d_action` | `queries_1d` | `action` | Action filtering |

### Hit Table Indexes

| Index Name | Table | Column(s) | Purpose |
|-----------|-------|-----------|---------|
| `hits_raw_dt` | `hits_raw` | `dt` | Time-range filtering for log retrieval and aggregation |
| `hits_raw_client_ip` | `queries_raw`* | `client_ip` | Client-based filtering |
| `hits_raw_fqdn` | `hits_raw` | `fqdn` | Domain name filtering |
| `hits_raw_feed` | `hits_raw` | `feed` | Feed-based filtering and dashboard top-N feeds |
| `hits_5m_dt` | `hits_5m` | `dt` | Time-range filtering |
| `hits_5m_client_ip` | `hits_5m` | `client_ip` | Client-based filtering |
| `hits_5m_fqdn` | `hits_5m` | `fqdn` | Domain name filtering |
| `hits_5m_feed` | `hits_5m` | `feed` | Feed-based filtering |
| `hits_1h_dt` | `hits_1h` | `dt` | Time-range filtering |
| `hits_1h_client_ip` | `hits_1h` | `client_ip` | Client-based filtering |
| `hits_1h_fqdn` | `hits_1h` | `fqdn` | Domain name filtering |
| `hits_1h_feed` | `hits_1h` | `feed` | Feed-based filtering |
| `hits_1d_dt` | `hits_1d` | `dt` | Time-range filtering |
| `hits_1d_client_ip` | `hits_1d` | `client_ip` | Client-based filtering |
| `hits_1d_fqdn` | `hits_1d` | `fqdn` | Domain name filtering |
| `hits_1d_feed` | `hits_1d` | `feed` | Feed-based filtering |

*Note: `hits_raw_client_ip` is defined on `queries_raw(client_ip)` in the source code — this appears to be a copy-paste artifact in `init_db.php`.

### Asset and Local Zone Indexes

| Index Name | Table | Column(s) | Purpose |
|-----------|-------|-----------|---------|
| `assets_name` | `assets` | `name` | Name-based lookup for asset resolution |
| `assets_address` | `assets` | `address` | Address-based lookup (IP/MAC → asset name) |
| `assets_itype` | `localzone` | `ltype` | Filtering by list type (block/allow/block-ip/allow-ip) |

### Authentication Indexes

| Index Name | Table | Column(s) | Purpose |
|-----------|-------|-----------|---------|
| `idx_users_username` | `users` | `username` | Fast username lookup during login |
| `idx_sessions_token` | `sessions` | `token` | Session token validation on every authenticated request |
| `idx_sessions_user_id` | `sessions` | `user_id` | Finding all sessions for a user (logout all, cascade) |
| `idx_sessions_expires` | `sessions` | `expires_at` | Expired session cleanup |
| `idx_login_attempts_ip` | `login_attempts` | `ip_address` | Rate limit checks per IP |
| `idx_login_attempts_time` | `login_attempts` | `attempted_at` | Pruning old login attempt records |

---

## Schema Versioning

RpiDNS tracks database schema versions using a dual mechanism for compatibility.

### Version Tracking

1. **`PRAGMA user_version`** — SQLite built-in integer metadata. Set during `init_db.php` and updated after each migration. Used as a fallback when the `schema_version` table doesn't exist.
2. **`schema_version` table** — Stores a history of applied migrations with timestamps. The `DbMigration` class reads `MAX(version)` from this table as the authoritative version.

The target version is defined by the `DBVersion` constant in `www/rpidns_vars.php` (currently `2`).

### Migration System

**Source:** `www/rpi_admin/db_migrate.php` (`DbMigration` class)

The migration process:

1. `getSchemaVersion()` reads the current version from the `schema_version` table, falling back to `PRAGMA user_version` if the table doesn't exist.
2. Compares current version against `DBVersion`.
3. Runs migration methods sequentially: `migrateV{N}ToV{N+1}()` for each version gap.
4. Each migration runs inside a transaction — on failure, the transaction is rolled back.
5. After each successful migration, the new version is recorded in both `schema_version` and `PRAGMA user_version`.

### Available Migrations

| Migration | From → To | Description |
|-----------|-----------|-------------|
| `migrateV1ToV2` | v1 → v2 | Creates `users`, `sessions`, and `login_attempts` tables with indexes. Imports existing users from `.htpasswd` if present. Creates a default admin user if no users are imported. |

### Running Migrations

Migrations run automatically when `AuthService` is instantiated (once per request). They can also be triggered manually via CLI:

```bash
php /opt/rpidns/www/rpi_admin/db_migrate.php
```

### Fresh Database Initialization

When no database exists, `scripts/init_db.php` creates all tables (including v2 auth tables), sets `PRAGMA user_version` to the current `DBVersion`, inserts an initial `schema_version` record, and creates the default admin user.

---

## Related Documentation

- [Backend API Reference](./backend-api.md) — API endpoints, aggregation tier selection, authentication system, and migration details
- [Scripts Reference](./scripts.md) — `init_db.php` (initialization), `parse_bind_logs.php` (data ingestion and aggregation), `clean_db.php` (retention enforcement)
- [Architecture Overview](./architecture.md) — system data flow from DNS query to database storage
- [Configuration Files](./configuration-files.md) — `rpisettings.php` retention settings and `rpidns_vars.php` constants
- [Frontend](./frontend.md) — Vue 3 components that display database data
- [Docker Deployment](./docker-deployment.md) — container volume mounts for the SQLite database directory
- [README](../README.md) — project overview and quick start
