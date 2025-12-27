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
- **Base Image**: Alpine Linux 3.21
- **Purpose**: DNS resolution with RPZ (Response Policy Zone) blocking
- **Packages**: bind, bind-tools, rsyslog, bash

### Web Container
- **Base Image**: Alpine Linux 3.21
- **Purpose**: Web UI for RpiDNS management, log collection
- **Packages**: openresty, php83-fpm, php83-sqlite3, rsyslog, dcron, git, openssl

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
| `PHP_FPM_VERSION` | `83` | PHP-FPM version (default: PHP 8.3) |
| `RPIDNS_ADMIN_PASSWORD` | *(auto-generated)* | Admin password for web UI |


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
| `/opt/rpidns/www` | `./www` | Web application files |
| `/opt/rpidns/www/db` | `./www/db` | SQLite database (persistent) |
| `/opt/rpidns/logs` | `./logs` | Application and DNS logs |
| `/opt/rpidns/scripts` | `./scripts` | Maintenance scripts |

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

### Web Container
```bash
wget -q --spider http://127.0.0.1/blocked.php
```
- Interval: 30s
- Timeout: 10s
- Retries: 3

## Maintenance

### Cron Jobs

The web container runs scheduled maintenance tasks:

| Schedule | Task |
|----------|------|
| Daily | Clean SSL certificate cache (remove certs older than 30 days) |
| Daily | Clean unused SSL certificates (remove after 7 days of inactivity) |

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

```bash
# Build Bind container
docker build -t rpidns-bind:local ./bind

# Build Web container
docker build -t rpidns-web:local ./web
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
