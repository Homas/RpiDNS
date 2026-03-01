# Scripts

## Overview

RpiDNS includes shell and PHP scripts in the `scripts/` directory for installation, database initialization, log parsing, data cleanup, and database import. These scripts support both bare-metal (Raspbian/OpenWrt) and Docker-based deployments.

| Script | Language | Purpose |
|--------|----------|---------|
| `rpidns_install.sh` | Bash | Raspbian installation and frontend build |
| `rpidns_install_openwrt.sh` | Shell | OpenWrt installation variant |
| `init_db.php` | PHP | Database initialization and default admin user creation |
| `parse_bind_logs.php` | PHP | BIND log parsing, data aggregation, asset tracking |
| `clean_db.php` | PHP | Retention-based data cleanup |
| `import_db.php` | PHP | Database import, schema upgrade, RPZ provisioning |

---

## rpidns_install.sh

**File:** `scripts/rpidns_install.sh`

### Purpose

Installs RpiDNS on a Raspbian (Debian-based) system. Handles system dependency installation, Node.js setup, frontend build with Vite, database initialization, cron job configuration, and BIND (`rndc`) configuration. In container environments (when `RPIDNS_INSTALL_TYPE` is set), it skips local setup and only fixes file ownership and downloads root hints.

### Prerequisites

- Raspbian or Debian-based Linux
- Root/sudo access
- Internet connectivity (for package downloads, NodeSource repository, MAC vendor database)

### Installation Steps

1. **System dependencies** — Installs `php-fpm`, `sqlite3`, `php-sqlite3`, `curl`, `ca-certificates`, `gnupg`, and `file` via `apt-get`.

2. **Node.js installation** — Adds the NodeSource repository (Node.js 20 LTS) and installs `nodejs`. Verifies the installation by checking common binary paths (`/usr/bin/node`, `/usr/local/bin/node`, `/opt/nodejs/bin/node`).

3. **Database initialization** — Creates the database directory at `/opt/rpidns/www/db/`, sets ownership to `$SUDO_USER:www-data` with appropriate permissions, and runs `init_db.php` to create the schema and default admin user.

4. **Cron jobs** — Installs three cron entries for the current user:
   ```
   * * * * *    /usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
   42 2 * * *   sleep 25;/usr/bin/php /opt/rpidns/scripts/clean_db.php
   42 3 * * *   sleep 25;/usr/bin/sqlite3 /opt/rpidns/www/db/rpidns.sqlite 'VACUUM;'
   ```

5. **User/group setup** — Adds `www-data` to the `bind` group and configures cross-group membership so the web server can manage BIND. Sets `$bind_host` to `127.0.0.1` in `rpidns_vars.php` for local installations.

6. **rndc configuration** — Generates an `rndc` key if not present, creates `rndc.conf`, and adds `controls` and key `include` directives to `named.conf.options` for BIND remote management.

7. **Frontend build** — Calls the `build_frontend()` function which:
   - Locates `npm` across common paths
   - Installs Node.js if `npm` is not found
   - Runs `npm install` and `npm run build` in `/opt/rpidns/rpidns-frontend`
   - Copies the built `dist/` to `/opt/rpidns/www/rpi_admin/dist`
   - Supports a `RPIDNS_BUILD_MODE` environment variable (`production` or `development`)

8. **MAC vendor database** — Downloads the Wireshark manufacturer database from GitLab to `scripts/mac.db` (used by `parse_bind_logs.php` for vendor lookup).

### Container Mode

When `RPIDNS_INSTALL_TYPE` is set (container deployment), the script skips steps 1–7 and only:
- Fixes ownership on `named.conf` for the BIND container user (UID 82)
- Downloads BIND root hints from InterNIC
- Downloads the MAC vendor database

---

## rpidns_install_openwrt.sh

**File:** `scripts/rpidns_install_openwrt.sh`

### Purpose

Installs RpiDNS on OpenWrt routers. This is a legacy variant of the installation script tailored for OpenWrt's package manager (`opkg`) and directory structure.

### Differences from Standard Install

| Aspect | Raspbian (`rpidns_install.sh`) | OpenWrt (`rpidns_install_openwrt.sh`) |
|--------|-------------------------------|---------------------------------------|
| Package manager | `apt-get` | `opkg` |
| PHP binary | `/usr/bin/php` | `/usr/bin/php-cli` |
| PHP version | PHP 8.3 | PHP 7 (`php7`, `php7-cgi`, `php7-mod-sqlite3`) |
| Web server | Nginx/OpenResty (via Docker) | `uhttpd` (OpenWrt built-in) |
| Frontend build | Vite build via npm | CDN downloads (Vue 2, Bootstrap Vue, Axios, ApexCharts, FontAwesome) |
| Install path | `/opt/rpidns` | `/opt/rpidns` (with symlinks to `/www`) |
| Node.js | Required (for Vite build) | Not required |
| Source | Already on disk | Cloned from GitHub (`dev` branch) |

### Installation Steps

1. **System packages** — Installs `php7`, `php7-cgi`, `sqlite3-cli`, `php7-mod-sqlite3`, `php7-mod-filter`, `git`, `git-http`, and `unzip` via `opkg`.

2. **Clone repository** — Clones the RpiDNS `dev` branch from GitHub into `/tmp/RpiDNS` and copies `www/` and `scripts/` to `/opt/rpidns/`.

3. **Symlinks** — Creates symlinks from OpenWrt's web root (`/www`) to the RpiDNS directories:
   - `/www/rpi_admin` → `/opt/rpidns/www/rpi_admin`
   - `/www/db` → `/opt/rpidns/www/db`
   - `/www/rpidns_vars.php` → `/opt/rpidns/www/rpidns_vars.php`
   - `/www/rpisettings.php` → `/opt/rpidns/www/rpisettings.php`

4. **Database initialization** — Creates the SQLite database file and runs `init_db.php` via `php-cli`.

5. **Cron jobs** — Same schedule as the Raspbian install but using `/usr/bin/php-cli`.

6. **Frontend libraries** — Downloads frontend dependencies directly from CDNs (Vue.js, Bootstrap Vue, Axios, ApexCharts, FontAwesome) instead of building with Vite.

7. **MAC vendor database** — Downloads the Wireshark manufacturer database.

### LuCI Integration (Commented Out)

The script contains commented-out code for integrating RpiDNS into OpenWrt's LuCI web interface as a set of tabs (Dashboard, Query Log, RPZ Hits, Admin) using iframe embedding. This integration creates a Lua controller at `/usr/lib/lua/luci/controller/rpidns/rpidns.lua` and HTML templates under `/usr/lib/lua/luci/view/rpidns/`.

---

## init_db.php

**File:** `scripts/init_db.php`

### Purpose

Initializes the RpiDNS SQLite database with the complete schema, creates a default admin user with a random password, and writes credentials to a file for first-time setup.

### Dependencies

- Requires `www/rpidns_vars.php` for the `DBFile` and `DBVersion` constants and database helper functions.

### Database Initialization

The `initSQLiteDB()` function creates the database at `/opt/rpidns/www/db/<DBFile>` and executes the following operations:

1. **Sets schema version** — `PRAGMA user_version` is set to the current `DBVersion` constant.

2. **Creates query tables** — Four tiers of query log tables with identical column structures (except `client_port` is omitted in aggregated tables and `cnt` is added):
   - `queries_raw` — Raw query data (dt, client_ip, client_port, mac, fqdn, type, class, options, server, action)
   - `queries_5m` — 5-minute aggregated data (adds `cnt` column)
   - `queries_1h` — 1-hour aggregated data
   - `queries_1d` — 1-day aggregated data

3. **Creates RPZ hit tables** — Four tiers of RPZ hit tables:
   - `hits_raw` — Raw hit data (dt, client_ip, client_port, mac, fqdn, action, rule_type, rule, feed)
   - `hits_5m` — 5-minute aggregated data (adds `cnt` column)
   - `hits_1h` — 1-hour aggregated data
   - `hits_1d` — 1-day aggregated data

4. **Creates supporting tables:**
   - `assets` — Network device tracking (name, address, vendor, comment, added_dt; unique on address)
   - `localzone` — Local block/allow list entries (ioc, type, ltype, comment, active, subdomains, added_dt, provisioned; unique on ioc)
   - `users` — Authentication users (id, username, password_hash, is_admin, created_at, updated_at)
   - `sessions` — User sessions with foreign key to users (token, expires_at, ip_address, user_agent)
   - `login_attempts` — Rate limiting data (ip_address, attempted_at, success)
   - `schema_version` — Migration tracking (version, applied_at)

5. **Creates indexes** — Indexes on `dt`, `client_ip`, `fqdn`, `server`, `action`, and `feed` columns across all tables for query performance. See [Database Documentation](./database.md) for the full index listing.

6. **Inserts schema version record** — Records the current `DBVersion` and timestamp in `schema_version`.

### Default Admin User

The `createDefaultAdminUser()` function:

1. Generates a 16-character random hex password using `random_bytes(8)`.
2. Hashes the password with `bcrypt` (cost factor 12).
3. Checks if an `admin` user already exists (skips creation if so).
4. Inserts the user with `is_admin = 1`.
5. Logs the credentials to `stderr` with prominent markers.
6. Writes credentials to `/opt/rpidns/conf/default_credentials.txt` (permissions `0600`).
7. Outputs credentials to `stdout`.

The credential file includes a notice to change the password immediately after first login.

---

## parse_bind_logs.php

**File:** `scripts/parse_bind_logs.php`

### Purpose

Parses BIND DNS query and RPZ hit logs, resolves client MAC addresses via the ARP table, looks up hardware vendors, inserts raw data into SQLite, performs multi-tier data aggregation, auto-creates asset records, and triggers pending database imports.

### Cron Schedule

Runs every minute via cron:
```
* * * * *   /usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
```

### Dependencies

- `www/rpidns_vars.php` — Database constants and helpers
- `www/rpisettings.php` — Asset tracking mode (`$assets_by`) and auto-creation setting
- `scripts/import_db.php` — Database import functions (called at end of each run)
- `scripts/mac.db` — Wireshark MAC vendor database

### Process Flow

1. **PID file check** — Creates `/opt/rpidns/logs/rpidns_parser.pid` to prevent concurrent execution. If a previous instance is still running (verified via `posix_getpgid`), the script exits.

2. **ARP table resolution** — Executes `/bin/ip neigh show` to build a mapping of IP addresses to MAC addresses. For each MAC, looks up the vendor prefix (first 8 characters) in `scripts/mac.db` using `grep`.

3. **Log file discovery** — Scans `/opt/rpidns/logs/*_queries.log` for BIND log files.

4. **Incremental parsing** — For each log file:
   - Reads the last-processed byte position from a `.pos` companion file
   - Detects log rotation (if file size < saved position, switches to `.log.0` or `.log.1`)
   - Parses lines from the saved position forward

5. **Query log parsing** — Matches lines against the BIND query log format using regex:
   ```
   client <IP>#<port> (<fqdn>): query: <fqdn> <class> <type> <options> (<server>)
   ```
   Supports both local BIND log format and syslog-forwarded format.

6. **RPZ hit parsing** — Matches lines against the BIND RPZ log format:
   ```
   rpz: ... client <IP>#<port> (<fqdn>): [disabled] rpz <rule_type> <action> rewrite <domain> via <rule>
   ```
   Handles both active and disabled (log-only) RPZ actions. Supports `QNAME` and `IP` rule types.

7. **SQLite insertion** — Bulk-inserts parsed data into:
   - `queries_raw` — With an `action` field set to `blocked` or `allowed` based on whether the client+FQDN pair appears in RPZ hits
   - `hits_raw` — With feed name extracted from the RPZ rule

8. **Data aggregation** — Aggregates raw data into summary tables:
   - `queries_5m` / `hits_5m` — 5-minute buckets (`dt - dt % 300`)
   - `queries_1h` / `hits_1h` — 1-hour buckets (`dt - dt % 3600`)
   - `queries_1d` / `hits_1d` — 1-day buckets (`dt - dt % 86400`)

   Aggregation is incremental — only processes rows newer than the latest entry in each summary table.

9. **Asset auto-creation** — If `$assets_autocreate` is enabled, inserts new asset records for discovered devices. The `$assets_by` setting controls whether assets are keyed by MAC address or IP address.

10. **Import trigger** — Checks for a pending import file at `<TMPDir>/rpidns_import_ready`. If found, reads the file path and object list, calls `importSQLiteDB()`, and removes the trigger file. This is how the web UI's database import feature communicates with the parser.

11. **Cleanup** — Removes the PID file.

---

## clean_db.php

**File:** `scripts/clean_db.php`

### Purpose

Deletes expired data from all query and RPZ hit tables based on configurable retention periods defined in `www/rpisettings.php`.

### Cron Schedule

Runs daily at 2:42 AM (with a 25-second delay):
```
42 2 * * *   sleep 25;/usr/bin/php /opt/rpidns/scripts/clean_db.php
```

A separate SQLite `VACUUM` runs at 3:42 AM to reclaim disk space after cleanup:
```
42 3 * * *   sleep 25;/usr/bin/sqlite3 /opt/rpidns/www/db/rpidns.sqlite 'VACUUM;'
```

### Dependencies

- `www/rpidns_vars.php` — Database constants and helpers (`DBFile`, `DB_open`, `DB_execute`, `DB_close`)
- `www/rpisettings.php` — Retention periods per table (`$retention` array)

### Retention Configuration

Retention periods are defined in `www/rpisettings.php` as days:

| Table | Default Retention |
|-------|-------------------|
| `queries_raw` | 7 days |
| `queries_5m` | 14 days |
| `queries_1h` | 60 days |
| `queries_1d` | 180 days |
| `hits_raw` | 14 days |
| `hits_5m` | 30 days |
| `hits_1h` | 180 days |
| `hits_1d` | 365 days |

### Cleanup Logic

The `cleanDB()` function executes a single SQL batch that deletes rows from all eight tables where `dt < now - (retention_days × 86400)`. The deletion threshold is calculated using SQLite's `strftime('%s', 'now')` for the current Unix timestamp.

---

## import_db.php

**File:** `scripts/import_db.php`

### Purpose

Imports data from an external RpiDNS SQLite database into the active database. Handles schema upgrades on the import file, supports selective object import, and provisions block/allow list entries into BIND RPZ zones via `nsupdate`.

### Dependencies

- `www/rpidns_vars.php` — Database constants and helpers (`DBVersion`, `DB_selectArray`, `DB_execute`)

### Invocation

The import is not called directly from cron. Instead, it is triggered by `parse_bind_logs.php` when it detects a trigger file at `<TMPDir>/rpidns_import_ready`. The web UI's import feature writes this trigger file with the format:
```
<import_db_path>|<comma_separated_objects>
```

### Schema Upgrade

The `upgrade_db()` function reads the import database's `PRAGMA user_version` and applies migrations:

- **Version 0 → current**: Adds the `provisioned` column to the `localzone` table and updates the version pragma.

If the import database is already at the current version, no upgrade is performed.

### Importable Objects

The `importSQLiteDB()` function accepts a comma-separated list of object identifiers:

| Object ID | Target Table | Conflict Handling |
|-----------|-------------|-------------------|
| `assets` | `assets` | Upsert — updates `name` and `comment` on address conflict |
| `q_raw` | `queries_raw` | Insert, skip on conflict |
| `q_5m` | `queries_5m` | Insert, skip on conflict |
| `q_1h` | `queries_1h` | Insert, skip on conflict |
| `q_1d` | `queries_1d` | Insert, skip on conflict |
| `h_raw` | `hits_raw` | Insert, skip on conflict |
| `h_5m` | `hits_5m` | Insert, skip on conflict |
| `h_1h` | `hits_1h` | Insert, skip on conflict |
| `h_1d` | `hits_1d` | Insert, skip on conflict |
| `bl` / `block` | `localzone` (ltype=block) | Insert, skip on conflict + RPZ provisioning |
| `wl` / `allow` | `localzone` (ltype=allow) | Insert, skip on conflict + RPZ provisioning |

### Import Process

1. **Attach** — The import database is attached as `db_import` to the active database connection.
2. **Copy data** — For each requested object, rows are copied from `db_import.<table>` to the main database using `INSERT ... ON CONFLICT` statements.
3. **RPZ provisioning** (block/allow lists only) — After importing block or allow list entries, the script queries all active entries and provisions them into BIND using `nsupdate`:
   - Adds a `CNAME .` record (NXDOMAIN) to the appropriate RPZ zone (`block.ioc2rpz.rpidns` or `allow.ioc2rpz.rpidns`)
   - If `subdomains` is enabled, also adds a wildcard entry (`*.<ioc>.<zone>`)
   - Uses the `$bind_host` variable to target the correct BIND server
4. **Detach and cleanup** — Detaches the import database and deletes the import file.

---

## Related Documentation

- [Database Schema](./database.md) — Table definitions, indexes, aggregation tiers, and retention configuration
- [BIND Configuration](./bind-configuration.md) — RPZ zones, named.conf structure, and feed management
- [Architecture](./architecture.md) — Data flow pipeline and cron-based processing
- [Docker Deployment](./docker-deployment.md) — Container crontab configuration and volume mounts
- [Configuration Files](./configuration-files.md) — `rpisettings.php` retention settings and `rpidns_vars.php` constants
- [Backend API](./backend-api.md) — API endpoints that trigger import and use parsed data
- [README](../README.md) — Project overview and scripts table
