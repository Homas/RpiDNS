# Docker Deployment

## Overview

RpiDNS uses a two-container Docker deployment model managed by Docker Compose. The containers are defined in the `rpidns-docker/` directory:

- **Bind container** (`rpidns-bind`) — ISC BIND9 DNS server with RPZ support
- **Web container** (`rpidns-web`) — OpenResty (Nginx + Lua) web server with PHP-FPM, rsyslog, and cron

The containers communicate over a dedicated Docker bridge network (`rpidns-net`) and share data through bind-mounted host directories for logs, configuration, database, and scripts.

## Bind Container

**Source:** `rpidns-docker/bind/`

### Dockerfile

**File:** `rpidns-docker/bind/Dockerfile`

The Bind container is built on Alpine 3.21 and installs:

| Package | Purpose |
|---------|---------|
| `bind` | ISC BIND9 DNS server |
| `bind-tools` | DNS utilities (`dig`, `rndc`, etc.) |
| `bash` | Shell for entrypoint script |
| `rsyslog` | Log forwarding in forward mode |

The image creates the following directory structure:

| Directory | Purpose |
|-----------|---------|
| `/var/cache/bind` | Zone data and cache files |
| `/etc/bind` | BIND configuration files |
| `/opt/rpidns/logs` | Log output directory |
| `/var/run/named` | PID file location |

All directories are owned by the `named` user (the BIND runtime user on Alpine).

Exposed ports: `53/tcp` and `53/udp` (DNS).

### Entrypoint Script

**File:** `rpidns-docker/bind/entrypoint.sh`

The entrypoint performs the following initialization sequence:

1. **rndc key generation** — Generates `/etc/bind/rndc.key` if not present using `rndc-confgen`
2. **rndc.conf creation** — Creates `/etc/bind/rndc.conf` for the rndc client if not present
3. **named.conf patching** — Injects `rndc.key` include and `controls` block into `named.conf` if not already present
4. **Zone file initialization** — Copies `db.empty.pi` to `/var/cache/bind/db.local` if missing
5. **Permission fixing** — Sets ownership on `/var/cache/bind` and `/opt/rpidns/logs` for the `named` user
6. **Syslog forwarding** (conditional) — If `RPIDNS_LOGGING=forward` and `RPIDNS_LOGGING_HOST` is set, configures rsyslog to forward `local4` facility logs to the remote host on port 10514, then starts `rsyslogd`
7. **IPv6 detection** — Checks for a public (non-link-local) IPv6 address; if none found, starts BIND in IPv4-only mode (`-4` flag)
8. **BIND startup** — Runs `named` in foreground (`-f`) as the `named` user (`-u named`)

### named.conf Template

**File:** `rpidns-docker/bind/named.conf`

This is a minimal default configuration bundled in the image. At runtime, the user's configuration is mounted at `/etc/bind/named.conf` via the Docker Compose volume, overriding this default.

The default configuration includes:

- **rndc controls** — Listens on `127.0.0.1:953` with `rndc-key` authentication
- **Options** — Listens on all interfaces, allows queries from any source, enables recursion and DNSSEC validation
- **Logging** — Two channels: `default_log` (general, 5 MB × 3 versions) and `query_log` (queries, 10 MB × 3 versions), both writing to `/opt/rpidns/logs/`
- **Root hints** — Uses Alpine's `/var/bind/root.cache`
- **Localhost zones** — `localhost` and `127.in-addr.arpa` using `db.empty.pi`

For the full BIND configuration structure including RPZ feeds, ACLs, and TSIG keys, see [BIND Configuration](./bind-configuration.md).

### Zone Template

**File:** `rpidns-docker/bind/db.empty.pi`

A minimal SOA zone template used to initialize local zones (allow, block, allow-ip, block-ip). Contains a localhost SOA record with standard TTL values and A/AAAA records pointing to loopback.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Hostname for the DNS server |
| `RPIDNS_DNS_TYPE` | `primary` | DNS server role (primary or secondary) |
| `RPIDNS_DNS_IPNET` | `192.168.0.0/16` | Allowed IP network for DNS queries |
| `RPIDNS_LOGGING` | `local` | Logging mode: `local` (log to files) or `forward` (forward via syslog) |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Remote syslog host for forward mode |

## Web Container

**Source:** `rpidns-docker/web/`

### Dockerfile

**File:** `rpidns-docker/web/Dockerfile`

The Web container uses a multi-stage build:

**Stage 1 — Frontend Builder** (Node 20 Alpine):
- Installs npm dependencies from `rpidns-frontend/package*.json`
- Builds production frontend assets with Vite (`npm run build`)
- Output: compiled static files in `/build/dist`

**Stage 2 — Final Image** (Alpine 3.21):

Installed packages:

| Package | Purpose |
|---------|---------|
| `openresty` | Nginx with Lua support for dynamic SSL |
| `lua-resty-openssl`, `lua-resty-lock` | Lua libraries for SSL certificate generation |
| `php83`, `php83-fpm` | PHP 8.3 with FastCGI Process Manager |
| `php83-sqlite3`, `php83-pdo_sqlite` | SQLite database access |
| `php83-openssl`, `php83-session`, `php83-json`, `php83-curl` | PHP extensions |
| `rsyslog` | Syslog reception from Bind container |
| `dcron` | Cron daemon for scheduled tasks |
| `sqlite` | SQLite CLI for VACUUM operations |
| `docker-cli` | Docker CLI for `rndc reload` via `docker exec` |
| `openssl` | SSL certificate generation |
| `apache2-utils` | htpasswd utility |
| `bind-tools` | DNS utilities |

PHP configuration overrides:
- `upload_max_filesize`: 512M
- `post_max_size`: 512M
- `max_execution_time`: 600s
- `memory_limit`: 512M

The image copies:
- Built frontend assets from Stage 1 → `/opt/rpidns/www/rpi_admin/dist`
- PHP application files (`blocked.php`, `rpidns_vars.php`, `rpisettings.php`, `rpidata.php`, `auth.php`, `db_migrate.php`, `BindConfigManager.php`, `index.php`)
- Blocked content placeholder files (`www/blocked/`)
- Maintenance scripts (`scripts/`)
- Configuration files (`nginx.conf.template` → `/etc/nginx/nginx.conf`, `rsyslog.conf` → `/etc/rsyslog.conf`, `crontab` → `/etc/crontabs/root`)

Exposed ports: `80` (HTTP), `443` (HTTPS), `10514` (syslog).

### Entrypoint Script

**File:** `rpidns-docker/web/entrypoint.sh`

The entrypoint performs the following initialization sequence:

1. **Docker socket access** — If `/var/run/docker.sock` is mounted, adds `www-data` to the `docker` group (needed for `rndc reload` via `docker exec`)
2. **Frontend asset verification** — Checks that pre-built frontend assets exist at `/opt/rpidns/www/rpi_admin/dist`
3. **SSL certificate generation**:
   - Self-signed server certificate (`server.key`, `server.crt`) if not present
   - CA certificate (`ca.key`, `ca.crt`) for dynamic SSL
   - SSL signing CA (`ioc2rpzCA.pkey`, `ioc2rpzCA.crt`) — 4096-bit RSA, 10-year validity
   - Intermediate certificate (`ioc2rpzInt.pkey`, `ioc2rpzInt.crt`) — signed by CA, 5-year validity
   - Fallback certificate (`ioc2rpz.fallback.pkey`, `ioc2rpz.fallback.crt`) — used to start Nginx before dynamic certs are generated
   - Symlinks CA cert to `/opt/rpidns/www/ioc2rpzCA.crt` for download
4. **Database initialization** — Checks `PRAGMA user_version`; if 0 or missing, runs `init_db.php` to create the schema and default admin user
5. **Rsyslog configuration** — Configures based on `RPIDNS_LOGGING` mode:
   - `local` mode: Receives TCP syslog on port 10514, routes BIND logs to per-source-IP files (`bind_<IP>_queries.log`)
   - `forward` mode: Forwards `local4` logs to `RPIDNS_LOGGING_HOST:10514`
6. **Service startup** (in order):
   - `rsyslogd` — syslog daemon
   - `crond` — cron daemon (background)
   - `php-fpm83` — PHP FastCGI (daemon mode)
   - `nginx` — OpenResty in foreground (`daemon off`)

### Nginx Configuration

**File:** `rpidns-docker/web/nginx.conf.template`

OpenResty configuration providing:

**HTTP server (port 80)**:
- Serves `blocked.php` as the default page for DNS-blocked domains
- All requests fall through to `blocked.php`

**HTTPS server (port 443)**:
- Dynamic SSL certificate generation via Lua (`ssl_certificate_by_lua_block`):
  - Checks for cached certificates in `/opt/rpidns/conf/ssl_cache/`
  - If not cached, generates ECC (secp384r1) certificates signed by the intermediate CA
  - Uses `resty.lock` to prevent parallel generation of the same certificate
  - Caches generated certificates to disk
- Admin interface at `/rpi_admin` — serves static assets and proxies PHP to `php-fpm` upstream
- Blocked content handlers — serves placeholder files for `.jpg`, `.png`, `.js`, `.css` requests
- Security: denies access to hidden files (`/\.`) and database files (`\.db$`)

**General settings**:
- PHP-FPM upstream on `127.0.0.1:9000`
- Gzip compression enabled for text, CSS, XML, JSON, JavaScript
- `client_max_body_size`: 512M (for database imports)
- Lua shared dictionaries: `ssl_cert_cache` (10 MB), `ioc2rpz_locks` (1 MB)

For more details on the Nginx configuration, see [Configuration Files](./configuration-files.md).

### Rsyslog Configuration

**File:** `rpidns-docker/web/rsyslog.conf`

Default configuration for local mode (overwritten by `entrypoint.sh` at runtime based on `RPIDNS_LOGGING`):

- **Modules**: `imuxsock` (local logging), `imtcp` (TCP syslog reception on port 10514)
- **Log routing**: BIND logs (matched by program name `named`/`bind` or facility `local4`) are written to per-source-IP files using the dynamic filename template `bind_<fromhost-ip>_queries.log`
- **Timestamp format**: RFC 3339 (`2024-01-15T10:30:45.123456+00:00`)
- **Default rules**: Standard system logging for messages, secure, mail, cron, and boot

### Crontab

**File:** `rpidns-docker/web/crontab`

Scheduled tasks running inside the Web container:

| Schedule | Command | Purpose |
|----------|---------|---------|
| `* * * * *` | `php parse_bind_logs.php` | Parse BIND query logs every minute |
| `42 2 * * *` | `php clean_db.php` (after 25s delay) | Retention-based database cleanup daily at 2:42 AM |
| `42 3 * * *` | `sqlite3 rpidns.sqlite 'VACUUM;'` (after 25s delay) | Database compaction daily at 3:42 AM |
| `0 2 * * *` | `find ... -mtime +30 -delete` | Remove SSL cache certificates older than 30 days |
| `0 3 * * *` | `find ... -atime +7 -delete` | Remove unused SSL certificates after 7 days |
| `0 4 * * *` | `find ... -name "*.log" -mtime +1 -exec gzip` | Compress logs older than 1 day |
| `0 5 * * *` | `find ... -name "*.log.gz" -mtime +30 -delete` | Remove compressed logs older than 30 days |

For details on the PHP scripts, see [Scripts](./scripts.md).

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Hostname used for SSL certificate CN |
| `RPIDNS_LOGGING` | `local` | Logging mode: `local` (receive logs) or `forward` (send logs) |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Remote syslog host for forward mode |
| `PHP_FPM_VERSION` | `83` | PHP-FPM version identifier |

## Docker Compose Configuration

**File:** `rpidns-docker/docker-compose.yml`

### Services

#### bind

| Setting | Value |
|---------|-------|
| Image | `ghcr.io/homas/rpidns-bind:latest` |
| Container name | `rpidns-bind` |
| Restart policy | `unless-stopped` |
| Ports | `53:53/tcp`, `53:53/udp` |
| Networks | `rpidns-net` |

#### web

| Setting | Value |
|---------|-------|
| Image | `ghcr.io/homas/rpidns-web:latest` |
| Container name | `rpidns-web` |
| Restart policy | `unless-stopped` |
| Ports | `80:80`, `443:443`, `10514:10514` |
| Networks | `rpidns-net` |
| Depends on | `bind` (condition: `service_healthy`) |

The Web container waits for the Bind container's health check to pass before starting, ensuring DNS is available when the web UI comes online.

### Volumes

All volumes use bind mounts to host directories relative to the Compose file location:

**Bind container volumes:**

| Host Path | Container Path | Purpose |
|-----------|---------------|---------|
| `./config/bind` | `/etc/bind` | BIND configuration files (named.conf, rndc.key, etc.) |
| `./bind-cache` | `/var/cache/bind` | Zone data and cache files |
| `./logs` | `/opt/rpidns/logs` | Shared log directory |

**Web container volumes:**

| Host Path | Container Path | Purpose |
|-----------|---------------|---------|
| `./config/nginx` | `/opt/rpidns/conf` | Nginx/SSL configuration |
| `./www/rpisettings.php` | `/opt/rpidns/www/rpisettings.php` | Application settings (persisted) |
| `./www/db` | `/opt/rpidns/www/db` | SQLite database directory |
| `./logs` | `/opt/rpidns/logs` | Shared log directory (same as Bind) |
| `./scripts` | `/opt/rpidns/scripts` | Maintenance scripts |
| `./bind-cache` | `/var/cache/bind:ro` | Read-only access to TSIG keys for nsupdate |
| `./config/bind` | `/etc/bind` | BIND config access for BindConfigManager |
| `/var/run/docker.sock` | `/var/run/docker.sock` | Docker socket for `rndc reload` via `docker exec` |

The `./logs` directory is shared between both containers — Bind writes DNS query logs and the Web container's rsyslog receives forwarded logs, while `parse_bind_logs.php` reads them.

### Networks

```yaml
networks:
  rpidns-net:
    driver: bridge
    ipam:
      driver: default
```

A single bridge network (`rpidns-net`) connects both containers. This enables:
- Syslog forwarding from Bind to Web (port 10514) when `RPIDNS_LOGGING=forward`
- DNS resolution between containers
- The Web container to reach the Bind container for `rndc` operations via `docker exec`

### Environment Variables

All environment variables support defaults via the `${VAR:-default}` syntax and can be set in a `.env` file alongside the `docker-compose.yml`:

| Variable | Default | Used By | Description |
|----------|---------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Both | System hostname |
| `RPIDNS_DNS_TYPE` | `primary` | Bind | DNS server role |
| `RPIDNS_DNS_IPNET` | `192.168.0.0/16` | Bind | Allowed IP network |
| `RPIDNS_LOGGING` | `local` | Both | Logging mode (`local` or `forward`) |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Both | Remote syslog host |
| `PHP_FPM_VERSION` | `83` | Web | PHP-FPM version |

### Deployment Quick Start

```bash
# 1. Clone or copy rpidns-docker/ to your deployment directory
# 2. Create required directories
mkdir -p config/bind config/nginx www/db logs bind-cache scripts

# 3. (Optional) Create .env file to override defaults
cat > .env << EOF
RPIDNS_HOSTNAME=rpidns.example.com
RPIDNS_DNS_IPNET=10.0.0.0/8
EOF

# 4. Start the stack
docker-compose up -d

# 5. Check container status
docker-compose ps

# 6. View logs
docker-compose logs -f
```

## Legacy Containers Directory

> **⚠️ DEPRECATED**: The `containers/` directory is superseded by `rpidns-docker/`. Use `rpidns-docker/` for all new deployments.

The `containers/` directory contains an older multi-container architecture that split functionality across five separate containers:

| File | Container | Purpose |
|------|-----------|---------|
| `Dockerfile_RpiDNS_provisioning` | `RpiDNS_provisioning` | Initial setup and BIND configuration generation |
| `Dockerfile_RpiDNS_cron` | `RpiDNS_cron` | Scheduled maintenance tasks |
| `Dockerfile_RpiDNS_openresty` | `RpiDNS_openresty` | Web server |
| `Dockerfile_RpiDNS_rsyslog` | `RpiDNS_rsyslog` | Syslog collection |
| `docker-compose.yml` | *(all)* | Orchestration for all five containers |
| `rpidns_provisioning.sh` | `RpiDNS_provisioning` | BIND config generation and zone provisioning |

The legacy `docker-compose.yml` also used the official `internetsystemsconsortium/bind9:9.18` image for the Bind container and host-path volumes under `/opt/rpidns/`.

**Key differences from the current `rpidns-docker/` deployment:**

| Aspect | Legacy (`containers/`) | Current (`rpidns-docker/`) |
|--------|----------------------|---------------------------|
| Container count | 5 (provisioning, bind, openresty, rsyslog, cron) | 2 (bind, web) |
| BIND image | `internetsystemsconsortium/bind9:9.18` | Custom Alpine-based `rpidns-bind` |
| Web server | Separate OpenResty container | Integrated in Web container |
| Syslog | Separate rsyslog container | Integrated in Web container |
| Cron | Separate cron container | Integrated in Web container |
| Provisioning | Separate provisioning container + script | Handled by entrypoint scripts |
| Dockerfiles | Empty/incomplete stubs | Fully implemented |
| Volume strategy | Host paths under `/opt/rpidns/` | Relative paths from Compose directory |

The legacy Dockerfiles (except `Dockerfile_RpiDNS_provisioning`) are empty stubs and were never completed. The provisioning script (`rpidns_provisioning.sh`) contains early-stage logic for BIND configuration generation that has been replaced by the `BindConfigManager` PHP class and the Bind container's entrypoint script.

## Health Checks and Restart Policies

### Bind Container Health Check

```yaml
healthcheck:
  test: ["CMD", "dig", "@127.0.0.1", "localhost", "+short", "+time=2", "+tries=1"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 10s
```

The Bind health check uses `dig` to query the local DNS server for `localhost`. This verifies that:
- The `named` process is running
- The DNS server is accepting and responding to queries
- The server can resolve at least the built-in localhost zone

The `+time=2` and `+tries=1` flags ensure the check fails quickly if the server is unresponsive. The 10-second `start_period` gives BIND time to load zone files before health checks begin.

### Web Container Health Check

```yaml
healthcheck:
  test: ["CMD", "wget", "-q", "--spider", "--timeout=5", "http://127.0.0.1/blocked.php"]
  interval: 60s
  timeout: 15s
  retries: 5
  start_period: 30s
```

The Web health check uses `wget` to request `blocked.php` via HTTP. This verifies that:
- OpenResty (Nginx) is running and accepting connections
- PHP-FPM is running and processing requests
- The application can serve pages

The `--spider` flag performs a HEAD request without downloading content. The 30-second `start_period` accounts for SSL certificate generation and service startup during the entrypoint.

### Restart Policies

Both containers use `restart: unless-stopped`, which means:
- Containers automatically restart on failure or Docker daemon restart
- Containers do not restart if explicitly stopped by the user (`docker stop`)
- Combined with health checks, this provides automatic recovery from transient failures

### Container Dependency

The Web container declares a dependency on the Bind container with `condition: service_healthy`:

```yaml
depends_on:
  bind:
    condition: service_healthy
```

This ensures the Web container only starts after the Bind container's health check passes (DNS is operational). This ordering is important because:
- The Web container's rsyslog needs to receive logs from a running Bind instance
- `parse_bind_logs.php` expects BIND log files to exist
- The `BindConfigManager` needs a running BIND server for `rndc reload` operations

## Related Documentation

- [Architecture](./architecture.md) — System architecture and data flow overview
- [BIND Configuration](./bind-configuration.md) — Detailed BIND named.conf structure, RPZ feeds, and zone management
- [Configuration Files](./configuration-files.md) — PHP config files, `.env` variables, Nginx and rsyslog configuration details
- [Scripts](./scripts.md) — Maintenance scripts (`parse_bind_logs.php`, `clean_db.php`, `init_db.php`) referenced in the crontab
- [Database](./database.md) — SQLite schema and aggregation tiers managed by the cron pipeline
- [Backend API](./backend-api.md) — PHP API endpoints served by the Web container
- [Frontend](./frontend.md) — Vue 3 frontend built and served by the Web container
- [README](../README.md) — Project overview and deployment quick start
