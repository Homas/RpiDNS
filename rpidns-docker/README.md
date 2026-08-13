# RpiDNS Container Deployment

RpiDNS is a DNS firewall solution using ISC Bind9 for DNS resolution and RPZ-based blocking. This container deployment provides a lightweight, portable way to run RpiDNS using Docker.

## Architecture

The deployment consists of two containers:

```
┌─────────────────────────────────────────────────────────────────┐
│                        Host System                               │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              Docker Network: rpidns-net                  │    │
│  │  ┌─────────────────────┐  ┌─────────────────────────┐   │    │
│  │  │   Bind Container    │  │     Web Container       │   │    │
│  │  │   ───────────────   │  │   ─────────────────     │   │    │
│  │  │   ISC Bind9 + RPZ   │  │   OpenResty + PHP-FPM   │   │    │
│  │  │   Port 53 TCP/UDP   │  │   + RSyslog             │   │    │
│  │  │                     │  │   Ports 80, 443, 10514  │   │    │
│  │  └─────────────────────┘  └─────────────────────────┘   │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                  Persistent Volumes                      │    │
│  │  ./config/bind  ./config/nginx  ./www  ./logs  ./scripts│    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

### Bind Container
- **Base Image**: Alpine Linux 3.24
- **Purpose**: DNS resolution with RPZ (Response Policy Zone) blocking
- **Packages**: bind, bind-tools, rsyslog, bash

### Web Container
- **Base Image**: Alpine Linux 3.24
- **Purpose**: Web UI for RpiDNS management, log collection
- **Packages**: openresty, php84-fpm, php84-sqlite3, rsyslog, dcron, git, openssl, bind-tools, docker-cli, sqlite, apache2-utils
- **Research tool packages**: traceroute, whois, chromium (+ chromium-swiftshader, nss, freetype, harfbuzz, font-freefont, font-noto). These are the bulk of the image size and can be dropped from the Dockerfile if the Research tools are not needed
- **Capabilities**: requires `cap_add: [NET_ADMIN, NET_RAW]`. `NET_RAW` is what allows the file capability on `/usr/bin/traceroute` to take effect — a file capability cannot exceed the container's bounding set. Note that a bounding-set entry alone does nothing for an unprivileged process, so traceroute can work in a root shell and still fail in the UI if the image was not rebuilt

## Quick Start

### 1. Create Directory Structure

```bash
mkdir -p rpidns
cd rpidns
mkdir -p config/bind config/nginx www www/db logs scripts bind-cache
```

### 2. Download docker-compose.yml

Copy the `docker-compose.yml` from this repository to your deployment directory.

### 3. Configure Environment Variables

Create a `.env` file:

```bash
RPIDNS_HOSTNAME=rpidns.local
RPIDNS_DNS_TYPE=primary
RPIDNS_DNS_IPNET=192.168.0.0/16
RPIDNS_LOGGING=local
RPIDNS_LOGGING_HOST=
```

### 4. Start Containers

```bash
docker-compose up -d
```

### 5. Verify Deployment

```bash
# Check container status
docker-compose ps

# Test DNS resolution
dig @localhost example.com

# Access web UI
open http://localhost
```

## Environment Variables

### Bind Container

| Variable | Default | Description |
|----------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Hostname for the RpiDNS instance |
| `RPIDNS_DNS_TYPE` | `primary` | DNS server type: `primary` or `secondary` |
| `RPIDNS_DNS_IPNET` | `192.168.0.0/16` | IP network for DNS ACL (allowed query sources) |
| `RPIDNS_LOGGING` | `local` | Logging mode: `local` or `forward` |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Remote syslog host (when `RPIDNS_LOGGING=forward`) |

### Web Container

| Variable | Default | Description |
|----------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Hostname for the RpiDNS instance |
| `RPIDNS_LOGGING` | `local` | Logging mode: `local` or `forward` |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Remote syslog host (when `RPIDNS_LOGGING=forward`) |
| `PHP_FPM_VERSION` | `84` | PHP-FPM version (default: PHP 8.4) |
| `RPIDNS_SYNC_SCRIPTS` | `true` | Refresh the bind-mounted scripts directory from the image on startup; set to `false` to keep customized host scripts |

The initial administrator password is generated on first start and written to `/opt/rpidns/conf/default_credentials.txt` (`./config/nginx/default_credentials.txt` on the host). Change it after the first login.


## Volume Mounts

### Bind Container

| Container Path | Host Path | Description |
|----------------|-----------|-------------|
| `/etc/bind` | `./config/bind` | Bind configuration files (named.conf, zone files) |
| `/var/cache/bind` | `./bind-cache` | Zone data cache and dynamic updates |
| `/opt/rpidns/logs` | `./logs` | DNS query logs |

### Web Container

| Container Path | Host Path | Description |
|----------------|-----------|-------------|
| `/opt/rpidns/conf` | `./config/nginx` | Nginx/OpenResty configuration, SSL certificates |
| `/opt/rpidns/www/rpisettings.php` | `./www/rpisettings.php` | Application settings, persisted across upgrades |
| `/opt/rpidns/www/db` | `./www/db` | SQLite database (persistent) |
| `/opt/rpidns/logs` | `./logs` | Application and DNS logs |
| `/opt/rpidns/scripts` | `./scripts` | Maintenance scripts |
| `/var/cache/bind` | `./bind-cache` (read-only) | TSIG keys for `nsupdate` |
| `/etc/bind` | `./config/bind` | BIND config, read and written by BindConfigManager |
| `/var/run/docker.sock` | `/var/run/docker.sock` | Lets the web container run `rndc reload` in the bind container |

Only the settings file and the database directory are mounted from `./www`, not the whole application tree — the PHP and frontend assets come from the image, so an image upgrade cannot be shadowed by stale host files.

## Exposed Ports

| Port | Protocol | Container | Description |
|------|----------|-----------|-------------|
| 53 | TCP/UDP | Bind | DNS queries |
| 80 | TCP | Web | HTTP web interface |
| 443 | TCP | Web | HTTPS web interface |
| 10514 | TCP | Web | Syslog receiver (for remote RpiDNS instances) |

## Logging Modes

### Local Mode (`RPIDNS_LOGGING=local`)

In local mode, the web container's RSyslog listens on port 10514 to receive logs from remote RpiDNS instances. Logs are written to:
- `/opt/rpidns/logs/bind_<source-ip>_queries.log`

This is useful when running a central RpiDNS server that collects logs from multiple remote instances.

### Forward Mode (`RPIDNS_LOGGING=forward`)

In forward mode, the bind container forwards its DNS query logs to an external syslog server specified by `RPIDNS_LOGGING_HOST`. This is useful when:
- Running RpiDNS on edge devices
- Centralizing logs to a SIEM or log management system

Example configuration:
```bash
RPIDNS_LOGGING=forward
RPIDNS_LOGGING_HOST=192.168.1.100
```

## SSL Certificates

The web container automatically generates SSL certificates on first startup:

1. **Server Certificate**: Self-signed certificate for HTTPS
2. **CA Certificate**: Used for dynamic SSL certificate generation
3. **Intermediate Certificate**: For certificate chain
4. **Fallback Certificate**: Used when dynamic generation fails

Certificates are stored in `/opt/rpidns/conf/ssl` and `/opt/rpidns/conf/ssl_sign`.

To use your own certificates, mount them to:
- `/opt/rpidns/conf/ssl/server.key`
- `/opt/rpidns/conf/ssl/server.crt`

## Health Checks

Both containers include health checks:

### Bind Container
```bash
dig @127.0.0.1 localhost +short +time=2 +tries=1
```
- Interval: 30s
- Timeout: 10s
- Retries: 3
- Start period: 10s

### Web Container
```bash
wget -q --spider --timeout=5 http://127.0.0.1/blocked.php
```
- Interval: 60s
- Timeout: 15s
- Retries: 5
- Start period: 30s (allows for SSL certificate generation on first start)

## Maintenance

### Cron Jobs

The web container runs scheduled maintenance tasks:

| Schedule | Task |
|----------|------|
| Every minute | Parse BIND logs into the database and aggregate (`parse_bind_logs.php`) |
| Every minute | Retire expired time-limited block/allow entries (`expire_iocs.php`) |
| Daily 2:42 AM | Retention-based database cleanup (`clean_db.php`) |
| Daily 3:42 AM | SQLite `VACUUM` |
| Daily 2:00 AM | Clean SSL certificate cache (remove certs older than 30 days) |
| Daily 3:00 AM | Clean unused SSL certificates (remove after 7 days of inactivity) |
| Daily 4:00 AM | Compress logs older than 1 day |
| Daily 5:00 AM | Remove compressed logs older than 30 days |

### Log Rotation

Logs in `/opt/rpidns/logs` should be rotated using host-level logrotate or a similar tool.

## Troubleshooting

### Check Container Logs

```bash
# Bind container logs
docker logs rpidns-bind

# Web container logs
docker logs rpidns-web
```

### Verify DNS Resolution

```bash
# Query the local DNS server
dig @localhost example.com

# Check if RPZ blocking is working
dig @localhost known-malicious-domain.com
```

### Check Container Health

```bash
docker inspect --format='{{.State.Health.Status}}' rpidns-bind
docker inspect --format='{{.State.Health.Status}}' rpidns-web
```

### Common Issues

1. **Port 53 already in use**: Stop any existing DNS services (systemd-resolved, dnsmasq)
   ```bash
   sudo systemctl stop systemd-resolved
   ```

2. **Permission denied on volumes**: Ensure host directories have correct permissions
   ```bash
   sudo chown -R 82:82 ./www ./logs
   sudo chown -R 100:101 ./bind-cache
   ```

3. **Web container fails health check**: Check PHP-FPM is running
   ```bash
   docker exec rpidns-web ps aux | grep php-fpm
   ```

## Building Images Locally

If you need to build the images locally instead of using pre-built images:

Run these from the **repository root**. The web image is a multi-stage build that copies `rpidns-frontend/`, `www/` and `scripts/`, so its build context must be the repository root, not `rpidns-docker/web` — this is the same context the CI workflow uses:

```bash
# Build Bind container (self-contained context)
docker build -t rpidns-bind:local ./rpidns-docker/bind

# Build Web container (context = repo root, Dockerfile addressed with -f)
docker build -t rpidns-web:local -f ./rpidns-docker/web/Dockerfile .
```

Then update `docker-compose.yml` to use local images:
```yaml
services:
  bind:
    image: rpidns-bind:local
  web:
    image: rpidns-web:local
```

## License

RpiDNS is open source software. See the main repository for license details.
