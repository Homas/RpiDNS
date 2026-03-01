# Configuration Files

## Overview

RpiDNS uses several configuration files to control application behavior, container deployment, web serving, and log routing. This document covers the PHP application configuration, environment variables, Nginx/OpenResty web server setup, and rsyslog log forwarding.

---

## `www/rpidns_vars.php` — Application Constants and Database Helpers

This file is included by all PHP backend scripts and defines global constants, database abstraction functions, request parsing, and filter field lists.

### Global Constants

| Constant / Variable | Type | Default | Purpose |
|---|---|---|---|
| `$rpiver` | string | `1.0.0.0` | Application version string |
| `$RpiPath` | string | `/opt/rpidns` | Base installation path inside the container |
| `DBFile` | const | `rpidns.sqlite` | SQLite database filename (relative to `www/db/`) |
| `DB` | const | `sqlite` | Database engine identifier (only `sqlite` is implemented) |
| `TMPDir` | const | `/tmp/rpidns` | Temporary directory for file operations |
| `DBVersion` | const | `2` | Expected database schema version; compared against `PRAGMA user_version` during migrations |
| `$bind_host` | string | `bind` | Hostname of the Bind container on the Docker bridge network (`rpidns-net`) |

### Filter Field Definitions

Two arrays define the columns available for filtering in the query log and RPZ hits views:

| Variable | Fields | Used By |
|---|---|---|
| `$filter_fields_q` | `client_ip`, `fqdn`, `mac`, `type`, `class`, `server`, `options`, `action` | Query log endpoints (`queries_raw`) |
| `$filter_fields_h` | `client_ip`, `fqdn`, `mac`, `rule_type`, `rule`, `feed`, `action` | RPZ hits endpoints (`hits_raw`) |

These arrays are consumed by the API layer in `rpidata.php` to build dynamic SQL `WHERE` clauses from user-supplied filter parameters.

### Request Parsing — `getRequest()`

Merges query-string parameters (`$_REQUEST`) with a JSON request body (`php://input`) into a single associative array. The `method` key is set from `$_SERVER['REQUEST_METHOD']`. This allows the API to accept both form-encoded and JSON payloads uniformly.

### Utility Functions

| Function | Purpose |
|---|---|
| `getProto()` | Returns `https://` or `http://` based on the current request's HTTPS status or port 443 |
| `secHeaders()` | Emits a `Content-Security-Policy: frame-ancestors 'self'` header to prevent clickjacking |

### Database Abstraction Layer

All database access goes through a set of wrapper functions that switch on the `DB` constant. Currently only the `sqlite` case is implemented, using PHP's `SQLite3` extension.

| Function | Description |
|---|---|
| `DB_open($file)` | Opens an SQLite3 connection, sets a 5-second busy timeout, and enables WAL journal mode |
| `DB_close($db)` | Closes the database connection |
| `DB_select($db, $sql)` | Executes a query and returns the raw result set |
| `DB_selectArray($db, $sql)` | Executes a query and returns all rows as an array of associative arrays (`SQLITE3_ASSOC`) |
| `DB_selectArrayNum($db, $sql)` | Same as above but returns numerically-indexed arrays (`SQLITE3_NUM`) |
| `DB_fetchRecord($db, $sql)` | Executes a query and returns the first row as an associative array |
| `DB_fetchArray($result)` | Fetches the next row from a result set as an associative array |
| `DB_fetchArrayNum($result)` | Fetches the next row from a result set as a numerically-indexed array |
| `DB_execute($db, $sql)` | Executes a non-query statement (INSERT, UPDATE, DELETE, DDL) |
| `DB_escape($db, $text)` | Escapes a string for safe inclusion in SQL |
| `DB_boolval($val)` | Converts a value to a database-appropriate boolean (`1` or `0`) |
| `DB_lasterror($db)` | Returns the last error message from the database connection |

The WAL journal mode and 5-second busy timeout are set on every connection open, which is important for concurrent access from the web server and cron scripts.

---

## `www/rpisettings.php` — Application Settings

This file stores runtime application settings that control asset tracking behavior, data retention policies, and dashboard display limits. It is writable by the web server — the Settings admin page updates this file through the API.

The file is bind-mounted into the Web container at `/opt/rpidns/www/rpisettings.php` so changes persist across container restarts.

### Settings Reference

| Variable | Type | Default | Purpose |
|---|---|---|---|
| `$assets_by` | string | `mac` | Asset identification mode. `mac` tracks devices by MAC address; `ip` tracks by IP address. Determines how the dashboard and query log group traffic per device. |
| `$assets_autocreate` | bool | `true` | When enabled, new assets are automatically created in the `assets` table when an unknown MAC/IP is seen during log parsing. When disabled, traffic from unknown devices is still logged but not linked to a named asset. |
| `$retention['hits_raw']` | int | `14` | Days to retain raw RPZ hit records |
| `$retention['hits_5m']` | int | `30` | Days to retain 5-minute aggregated RPZ hit data |
| `$retention['hits_1h']` | int | `180` | Days to retain 1-hour aggregated RPZ hit data |
| `$retention['hits_1d']` | int | `365` | Days to retain 1-day aggregated RPZ hit data |
| `$retention['queries_raw']` | int | `7` | Days to retain raw DNS query records |
| `$retention['queries_5m']` | int | `14` | Days to retain 5-minute aggregated query data |
| `$retention['queries_1h']` | int | `60` | Days to retain 1-hour aggregated query data |
| `$retention['queries_1d']` | int | `180` | Days to retain 1-day aggregated query data |
| `$dash_topx` | int | `50` | Maximum number of entries shown in dashboard "Top X" widgets (top clients, top domains, top blocked, etc.) |

### Retention Policy

The `$retention` array is consumed by `scripts/clean_db.php`, which runs daily at 2:42 AM via cron. For each table, rows older than the configured number of days are deleted. The multi-tier strategy keeps recent data at full resolution while progressively compressing older data:

```
Raw (7–14 days) → 5-minute (14–30 days) → 1-hour (60–180 days) → 1-day (180–365 days)
```

See [Database Documentation](./database.md) for table schemas and [Scripts Documentation](./scripts.md) for `clean_db.php` details.

---

## `.env` File — Environment Variables

Docker Compose reads a `.env` file (placed alongside `docker-compose.yml`) to substitute variables into the service definitions. All variables have sensible defaults defined in `docker-compose.yml` using the `${VAR:-default}` syntax, so the `.env` file is optional for basic deployments.

### Variable Reference

| Variable | Default | Containers | Purpose |
|---|---|---|---|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Bind, Web | Sets the container hostname and is used as the CN in auto-generated SSL certificates. Also used by the Bind entrypoint for logging. |
| `RPIDNS_DNS_TYPE` | `primary` | Bind | DNS server role. Currently only `primary` is used. Reserved for future primary/secondary replication support. |
| `RPIDNS_DNS_IPNET` | `192.168.0.0/16` | Bind | IP network range allowed to query the DNS server. Used in the BIND `named.conf` ACL to restrict recursive queries to trusted clients. |
| `RPIDNS_LOGGING` | `local` | Bind, Web | Logging mode. `local` — the Web container receives syslog from the Bind container over TCP port 10514 (default). `forward` — logs are forwarded to an external syslog host specified by `RPIDNS_LOGGING_HOST`. |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Bind, Web | Syslog destination hostname/IP when `RPIDNS_LOGGING=forward`. The Bind container forwards `local4` facility logs to this host on port 10514. Ignored when `RPIDNS_LOGGING=local`. |
| `PHP_FPM_VERSION` | `83` | Web | PHP-FPM binary version suffix. The Web container runs `php-fpm${PHP_FPM_VERSION}` (e.g., `php-fpm83` for PHP 8.3). |

### How Variables Affect Container Behavior

**Bind container (`rpidns-bind`):**
- `RPIDNS_HOSTNAME` — logged at startup for identification.
- `RPIDNS_DNS_TYPE` — logged at startup; reserved for future use.
- `RPIDNS_DNS_IPNET` — injected into the BIND ACL to control which clients can make recursive queries.
- `RPIDNS_LOGGING=forward` + `RPIDNS_LOGGING_HOST` — the entrypoint writes a custom `/etc/rsyslog.conf` that forwards `local4` logs to the specified host on port 10514, then starts `rsyslogd` before launching `named`.

**Web container (`rpidns-web`):**
- `RPIDNS_HOSTNAME` — used as the CN/subject in auto-generated SSL certificates (both the server cert and the CA cert for dynamic SSL).
- `RPIDNS_LOGGING` — controls rsyslog configuration at startup:
  - `local` (default): rsyslog listens on TCP port 10514 to receive logs from the Bind container and writes them to per-source-IP log files.
  - `forward`: rsyslog forwards `local4` logs to `RPIDNS_LOGGING_HOST:10514` instead of receiving them.
- `PHP_FPM_VERSION` — determines which PHP-FPM binary is started (e.g., `php-fpm83`).

### Example `.env` File

```env
RPIDNS_HOSTNAME=dns.example.com
RPIDNS_DNS_TYPE=primary
RPIDNS_DNS_IPNET=10.0.0.0/8
RPIDNS_LOGGING=local
PHP_FPM_VERSION=83
```

See [Docker Deployment Documentation](./docker-deployment.md) for the full `docker-compose.yml` reference.

---

## `rpidns-docker/web/nginx.conf.template` — OpenResty/Nginx Configuration

The Web container runs OpenResty (Nginx with Lua support) to serve the frontend, proxy PHP requests to PHP-FPM, and dynamically generate SSL certificates for blocked domains. The configuration file is located at `rpidns-docker/web/nginx.conf.template`.

### Architecture

```
Client Browser
    │
    ├── HTTP :80  ──→  Block page (all requests → blocked.php)
    │
    └── HTTPS :443 ──→  Dynamic SSL cert generation (Lua)
                          ├── /rpi_admin/*  → Admin UI (static files + PHP-FPM)
                          ├── *.php         → PHP-FPM (upstream 127.0.0.1:9000)
                          └── /*            → Block page (blocked.php)
```

### Global Settings

| Directive | Value | Purpose |
|---|---|---|
| `worker_processes` | `auto` | Matches the number of CPU cores |
| `error_log` | `/opt/rpidns/logs/nginx/nginx_error.log warn` | Error log location and level |
| `worker_connections` | `1024` | Max simultaneous connections per worker |
| `client_max_body_size` | `512M` | Allows large database imports via the admin UI |
| `keepalive_timeout` | `65` | Seconds to keep idle connections open |

### Gzip Compression

Enabled for text-based content types: `text/plain`, `text/css`, `text/xml`, `application/json`, `application/javascript`, `application/xml`. Compression level is set to 6.

### Lua Shared Dictionaries

| Dictionary | Size | Purpose |
|---|---|---|
| `ssl_cert_cache` | `10m` | Caches dynamically generated SSL certificates in memory |
| `ioc2rpz_locks` | `1m` | Prevents parallel generation of the same certificate using `resty.lock` |

### PHP-FPM Upstream

PHP requests are proxied to `127.0.0.1:9000` (PHP-FPM running inside the same container). All `.php` files are handled via FastCGI with standard `fastcgi_params`.

### HTTP Server (Port 80)

The HTTP server acts as a catch-all block page for DNS-blocked domains. Every request is routed to `blocked.php`, which displays a "this domain has been blocked" page. This server does not serve the admin interface.

### HTTPS Server (Port 443)

The HTTPS server handles both the admin interface and blocked-domain pages with dynamic SSL certificate generation.

#### Dynamic SSL Certificate Generation

When a browser requests a blocked HTTPS domain, OpenResty uses a Lua `ssl_certificate_by_lua_block` to generate a valid certificate on-the-fly:

1. Extracts the requested domain from the TLS SNI (`ssl.server_name()`)
2. Checks the file-based cache (`/opt/rpidns/conf/ssl_cache/`) for an existing cert
3. If cached, loads and uses the existing certificate and private key
4. If not cached, acquires a lock (prevents duplicate generation) and:
   - Generates a new ECC key pair (`secp384r1` — chosen for speed on Raspberry Pi)
   - Creates an X.509 certificate signed by the intermediate CA (`ioc2rpzInt`)
   - Builds a full certificate chain: leaf → intermediate → CA
   - Caches the cert and key to disk for future requests
   - Certificate validity: 820 days

The CA and intermediate certificates are auto-generated by the Web container's `entrypoint.sh` on first startup and stored in `/opt/rpidns/conf/ssl_sign/`.

#### SSL/TLS Settings

| Setting | Value |
|---|---|
| Protocols | TLSv1, TLSv1.1, TLSv1.2, TLSv1.3 |
| Ciphers | `HIGH:!aNULL:!eNULL:!EXPORT:!CAMELLIA:!DES:!MD5:!PSK:!RC4` |
| Session cache | `builtin:1000 shared:SSL:10m` |
| HTTP/2 | Enabled |
| Server cipher preference | Enabled |

#### Location Blocks (HTTPS)

| Location | Priority | Behavior |
|---|---|---|
| `^~ /rpi_admin` | Highest | Admin interface — serves static assets directly, proxies `.php` to PHP-FPM. Must appear before blocking rules. |
| `~* \.(jpg\|jpeg)$` | High | Serves `blocked/blocked.jpg` for image requests on blocked domains |
| `~* \.(png)$` | High | Serves `blocked/blocked.png` for PNG requests on blocked domains |
| `~* \.(js)$` | High | Serves `blocked/blocked.js` for JavaScript requests on blocked domains |
| `~* \.(css)$` | High | Serves `blocked/blocked.css` for CSS requests on blocked domains |
| `= /blocked.php` | Normal | Block page PHP handler |
| `~ \.php$` | Normal | General PHP file handler via PHP-FPM |
| `/` | Default | Catch-all — serves block page via `try_files` fallback |
| `~ /\.` | Security | Denies access to hidden files (e.g., `.htaccess`, `.env`) |
| `~ \.db$` | Security | Denies access to database files |

### Lua Package Paths

The configuration includes both OpenResty bundled Lua libraries and Alpine-packaged `lua-resty` modules:
- `/usr/lib/nginx/lualib/` — OpenResty bundled modules
- `/usr/share/lua/common/` and `/usr/share/lua/5.1/` — Alpine system Lua packages

---

## `rpidns-docker/web/rsyslog.conf` — Syslog Log Routing

The Web container runs rsyslog to receive DNS query logs from the Bind container over TCP. The configuration is dynamically generated by the Web container's `entrypoint.sh` based on the `RPIDNS_LOGGING` environment variable.

### Operating Modes

**Local mode** (`RPIDNS_LOGGING=local`, default):
The Web container listens on TCP port 10514 and receives syslog messages from the Bind container. This is the standard single-host deployment mode.

**Forward mode** (`RPIDNS_LOGGING=forward`):
The Web container forwards `local4` facility logs to an external syslog host (`RPIDNS_LOGGING_HOST:10514`). Used in distributed deployments where log aggregation happens on a separate server.

### Local Mode Configuration (Default)

#### Modules

| Module | Purpose |
|---|---|
| `imuxsock` | Local system logging via Unix socket |
| `imtcp` | TCP syslog reception on port 10514 |

#### Global Directives

| Directive | Value | Purpose |
|---|---|---|
| `$ActionFileDefaultTemplate` | `RSYSLOG_FileFormat` | RFC3339 timestamp format for log entries |
| `$FileCreateMode` | `0640` | Default permissions for new log files |
| `$DirCreateMode` | `0755` | Default permissions for new log directories |
| `$WorkDirectory` | `/var/lib/rsyslog` | Rsyslog working directory for state files |

#### Log Templates

| Template | Output | Purpose |
|---|---|---|
| `RpiDNSBindLog` | `/opt/rpidns/logs/bind_%fromhost-ip%_queries.log` | Dynamic filename using the source IP of the sending Bind container. Enables per-instance log separation in multi-Bind deployments. |
| `RFC3339Format` | `%timegenerated:::date-rfc3339% %fromhost-ip% %syslogtag%%msg%\n` | Standardized log line format with RFC3339 timestamps |

#### Routing Rules

The primary routing rule matches BIND/named log messages by three criteria:
- Program name is `named` or `bind`
- Syslog facility is `local4`

Matching messages are written to per-source-IP log files using the `RpiDNSBindLog` dynamic filename template and the `RFC3339Format` line template. The `stop` directive prevents these messages from also being written to default log files.

All other messages follow standard syslog routing:

| Selector | Destination |
|---|---|
| `*.info` (excluding mail, authpriv, cron) | `/var/log/messages` |
| `authpriv.*` | `/var/log/secure` |
| `mail.*` | `/var/log/maillog` |
| `cron.*` | `/var/log/cron` |
| `*.emerg` | All logged-in users (`:omusrmsg:*`) |
| `local7.*` | `/var/log/boot.log` |

### Data Flow

```
Bind Container                    Web Container
┌──────────┐                     ┌──────────────────┐
│  named   │──syslog (local4)──→│ rsyslog :10514   │
│          │    TCP              │                  │
└──────────┘                     │  ↓ route by      │
                                 │    program name  │
                                 │                  │
                                 │  bind_<IP>_      │
                                 │  queries.log     │
                                 │       ↓          │
                                 │  parse_bind_     │
                                 │  logs.php (cron) │
                                 │       ↓          │
                                 │  SQLite DB       │
                                 └──────────────────┘
```

The per-source-IP log files (e.g., `bind_172.18.0.2_queries.log`) are then parsed every minute by `parse_bind_logs.php` via cron, which inserts the data into the SQLite database for display in the frontend.

---

## Related Documentation

- [Database](./database.md) — table schemas, aggregation tiers, retention policies
- [Docker Deployment](./docker-deployment.md) — container definitions, volumes, networking, health checks
- [Backend API](./backend-api.md) — PHP endpoints, authentication, BindConfigManager
- [Scripts](./scripts.md) — `parse_bind_logs.php`, `clean_db.php`, and other maintenance scripts
- [BIND Configuration](./bind-configuration.md) — DNS server setup, RPZ feeds, zone definitions
- [Architecture](./architecture.md) — system overview and data flow
- [Frontend](./frontend.md) — Vue 3 components that use configuration settings
- [README](../README.md) — project overview and environment variable reference
