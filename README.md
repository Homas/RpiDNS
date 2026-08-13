# RpiDNS
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

## Overview

RpiDNS is a comprehensive DNS-based ad-blocking and security monitoring application powered by [ioc2rpz.net](https://ioc2rpz.net). It provides a central management interface for monitoring and controlling DNS traffic across your entire network using Response Policy Zones (RPZ) to block malicious domains, advertisements, trackers, and other unwanted content at the DNS level.

The system integrates with ISC BIND DNS server and provides real-time visibility into all DNS queries originating from devices on your network.

**Key capabilities include:**
- **DNS Query Monitoring** - Comprehensive tracking of all DNS queries from every device on your network
- **Ad & Malware Blocking** - Automatic blocking using RPZ feeds from ioc2rpz.net
- **Custom Block/Allow Lists** - Full control over domain blocking with your own custom rules
- **Network Asset Tracking** - Automatic discovery and tracking of devices by MAC or IP address
- **Security Analytics** - Detailed statistics and visualizations for threat analysis
- **Research & Threat Hunting** - Newly seen domains, read-only SQL over the query history, and built-in network research tools (RDAP/WHOIS, DNS records, reputation, GeoIP/ASN, TLS, website preview)

## Prerequisites

| Software | Version | Purpose |
|----------|---------|---------|
| Docker | Latest | Container deployment |
| Node.js | 24 | Frontend development and build |
| PHP | 8.4 | Backend API and maintenance scripts |

## Deployment Options

RpiDNS supports two deployment models:

1. **On-Premises Installation** - Direct installation on Raspberry Pi or Linux server
2. **Container Deployment** - Docker-based deployment (recommended for portability)

## Container Deployment

The container deployment provides a lightweight, portable way to run RpiDNS using Docker.

### Architecture

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

### Container Components

| Container | Base Image | Purpose | Key Packages |
|-----------|------------|---------|--------------|
| Bind | Alpine 3.24 | DNS resolution with RPZ blocking | bind, bind-tools, rsyslog |
| Web | Alpine 3.24 | Web UI, log collection | openresty, php84-fpm, php84-sqlite3, rsyslog, dcron, bind-tools, docker-cli |
| Web (Research tools) | — | Optional tooling for the Research page | traceroute, whois, chromium (+ swiftshader, nss, freetype, harfbuzz, fonts) |

The Research tool packages are the heaviest part of the web image. Dropping that Dockerfile layer keeps everything else working — the website preview then reports itself disabled, and RDAP still works over HTTPS without the port-43 `whois` fallback.

**Required capabilities:** the web container requests `NET_ADMIN` and `NET_RAW` (see `cap_add` in the compose file). `NET_RAW` is needed because the image grants `cap_net_raw+ep` to `/usr/bin/traceroute`, and a file capability cannot exceed the container's bounding set. Removing `cap_add` leaves traceroute's ICMP and TCP modes unavailable.

### Quick Start
https://ioc2rpz.net community generate install scripts for container and non-container based deployments.
For manual deployment follow the instruction below.

```bash
# 1. Create directory structure
mkdir -p rpidns && cd rpidns
mkdir -p config/bind config/nginx www www/db logs scripts bind-cache

# 2. Copy docker-compose.yml from rpidns-docker directory

# 3. Create .env file
cat > .env << EOF
RPIDNS_HOSTNAME=rpidns.local
RPIDNS_DNS_TYPE=primary
RPIDNS_DNS_IPNET=192.168.0.0/16
RPIDNS_LOGGING=local
RPIDNS_LOGGING_HOST=
EOF

# 4. Start containers
docker-compose up -d

# 5. Verify deployment
docker-compose ps
dig @localhost example.com
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `RPIDNS_HOSTNAME` | `rpidns.local` | Hostname for the RpiDNS instance |
| `RPIDNS_DNS_TYPE` | `primary` | DNS server type: `primary` or `secondary` |
| `RPIDNS_DNS_IPNET` | `192.168.0.0/16` | IP network for DNS ACL |
| `RPIDNS_LOGGING` | `local` | Logging mode: `local` or `forward` |
| `RPIDNS_LOGGING_HOST` | *(empty)* | Remote syslog host (when `forward` mode) |
| `PHP_FPM_VERSION` | `84` | PHP-FPM version (default: PHP 8.4) |
| `RPIDNS_SYNC_SCRIPTS` | `true` | Refresh the bind-mounted scripts directory from the image on startup. Set to `false` to keep customized host scripts |

### Exposed Ports

| Port | Protocol | Container | Description |
|------|----------|-----------|-------------|
| 53 | TCP/UDP | Bind | DNS queries |
| 80 | TCP | Web | HTTP web interface |
| 443 | TCP | Web | HTTPS web interface |
| 10514 | TCP | Web | Syslog receiver |

For detailed container deployment documentation, see [rpidns-docker/README.md](rpidns-docker/README.md).

## User Interface

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_onprem.png"></p>

### Getting Started

#### Logging In

RpiDNS uses session-based authentication with bcrypt password hashing, rate limiting on login attempts, and multi-user support. To access RpiDNS, navigate to the URL where RpiDNS is hosted on your network. Enter your credentials:
- **Username** - Your assigned username (case-sensitive)
- **Password** - Your account password (case-sensitive)

Your session remains active until you explicitly log out or close your browser.

#### Navigation

RpiDNS features a tab-based navigation system with a vertical sidebar on desktop and horizontal layout on mobile:

| Tab | Description |
|-----|-------------|
| Dashboard | Real-time overview of DNS activity with statistical widgets and traffic charts |
| Query log | Searchable log of all DNS queries with filtering and aggregation |
| RPZ log | Dedicated view of blocked DNS queries by RPZ rules |
| Research | Threat hunting workspace: newly seen queries, SQL query tool, and network research tools |
| Admin | Configuration and management (Assets, RPZ Feeds, Block, Allow, Settings, Tools, Users) |
| Donate | Ways to support the project |
| Help | Comprehensive documentation and guidance |

The navigation menu can be collapsed to show only icons for more screen space. The active tab is reflected in the URL as `#i2r/{tab}` — and on the Research page as `#i2r/3/{unique|sql|tools}` — so a specific view can be bookmarked or shared.

#### User Menu

Located in the top-right corner, the user menu provides:
- **Change Password** - Update your account password
- **Logout** - Securely sign out and terminate your session

---

### Dashboard

The Dashboard is your central command center for monitoring DNS activity. It displays statistical widget cards and a queries-per-minute chart.

#### Time Period Selection

| Period | Description |
|--------|-------------|
| 30m | Last 30 minutes - ideal for real-time monitoring |
| 1h | Last hour |
| 1d | Last 24 hours - understand daily patterns |
| 1w | Last 7 days - analyze weekly trends |
| 30d | Last 30 days - long-term trend analysis |
| custom | Select specific start and end date/time |

**Auto-Refresh:** Toggle the "Auto" switch to enable automatic refresh every 60 seconds.

#### Dashboard Widgets

**Allowed Traffic (Top Row):**

| Widget | Description |
|--------|-------------|
| TopX Allowed Requests | Most frequently requested domains that were allowed. Helps understand commonly accessed services. |
| TopX Allowed Clients | Devices generating the most allowed queries. Identifies most active devices. |
| TopX Allowed Request Types | DNS record types (A, AAAA, CNAME, MX, TXT, etc.). Unusual distribution might indicate DNS tunneling. |
| RpiDNS Stats | Appliance metrics: CPU load (1/5/15 min), memory usage, disk usage, uptime, and CPU temperature. |

**Blocked Traffic (Bottom Row):**

| Widget | Description |
|--------|-------------|
| TopX Blocked Requests | Domains most frequently blocked by RPZ. High-frequency blocks often include ads and malware. |
| TopX Blocked Clients | Devices triggering the most blocks. High counts may indicate malware or aggressive advertising. |
| TopX Feeds | RPZ feeds responsible for most blocks. Understand which threat categories affect your network. |
| TopX Servers | DNS servers handling the most queries. Useful for load distribution analysis. |

#### Interactive Actions

Hover over items in widgets to reveal action buttons:
- **Show queries** - Navigate to Query Log filtered by the selected item
- **Show hits** - Navigate to RPZ Hits filtered by the selected item
- **Analyze** - Open the [Research](#research) tools for the domain and run every applicable tool (request widgets)
- **Block** - Add domain to your custom Block List. If the domain is currently on the Allow List it is removed from there instead, so the two lists never end up contradicting each other
- **Allow** - Add domain to Allow List (overrides RPZ blocks). Likewise removes an existing Block List entry rather than creating a conflicting rule
- **Research** - Open [external research links](#external-research-links)

#### Queries per Minute Chart

Visual representation of DNS query volume over time:
- **Blue area** - Allowed (successful) DNS queries per minute
- **Red area** - Blocked queries (RPZ hits) per minute

Hover over any point to see exact timestamp and query counts.

---

### Query Log

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_qlog.png"></p>

The Query Log provides a comprehensive, searchable record of all DNS queries processed by your DNS server.

#### View Controls

The same time-period buttons as the Dashboard (30m, 1h, 1d, 1w, 30d, custom), an **Auto** switch for 60-second refresh, a manual Refresh button, and pagination. The Local Time header sorts the log. Auto-refresh is remembered per view.

#### Right-Click Actions

Right-clicking a cell opens a context menu whose contents depend on the column:

| Cell | Menu |
|------|------|
| Request (FQDN) | [External research links](#external-research-links), **Analyze** (opens the [Research](#research) tools for that domain), **Block**, **Allow** |
| Client, Server, Type, Class, Options, Action | **Filter by \<value\>** — applies that value as a filter without typing it |

#### Logs vs Stats View

| Mode | Description |
|------|-------------|
| Logs | Individual query records with timestamps. Ideal for investigating specific incidents. |
| Stats | Aggregated statistics grouped by selected fields. Use checkboxes to configure grouping. |

**Tip:** Start with Stats view to identify patterns, then switch to Logs view to investigate specific items.

#### Table Columns

| Column | Description |
|--------|-------------|
| Local Time | Timestamp when query was received (Logs view only) |
| Client | Device that made the query (friendly name or IP/MAC) |
| Server | DNS server that processed the query |
| Request | Fully qualified domain name (FQDN) queried |
| Type | DNS record type (A, AAAA, CNAME, MX, TXT, PTR, SRV, NS) |
| Class | DNS query class (typically "IN" for Internet) |
| Options | Additional DNS query options and flags |
| Count | Number of queries (Stats view) or 1 (Logs view) |
| Action | Allow or Block status |

#### Filtering

**Simple Text Search:** Type any text to search across all columns (case-insensitive).

**Field-Specific Filters:**
- `fqdn=example.com` - Filter by domain name
- `client_ip=192.168.1.100` - Filter by client IP address
- `mac=AA:BB:CC:DD:EE:FF` - Filter by MAC address
- `type=A` - Filter by DNS record type
- `server=dns1` - Filter by DNS server

---

### RPZ Hits

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_hits.png"></p>

The RPZ Hits section shows all DNS queries blocked by Response Policy Zone rules. Blocks originate from RPZ feeds (curated threat lists) and your custom Block List.

It offers the same view controls as the Query Log (period buttons, Auto refresh, pagination, sortable Local Time) and the same right-click behaviour: research links, **Analyze** and **Allow** on the Request cell, and **Filter by \<value\>** on the Client, Action, Rule and Feed cells.

#### Table Columns

| Column | Description |
|--------|-------------|
| Local Time | Timestamp when blocked query occurred (Logs view only) |
| Client | Device that attempted to access the blocked domain |
| Request | Domain name that was blocked |
| Action | RPZ action applied (NXDOMAIN, NODATA, etc.) |
| Rule | Specific RPZ rule that triggered the block (includes feed name) |
| Type | Rule type (QNAME, RPZ-IP, RPZ-NSDNAME, RPZ-NSIP, RPZ-CLIENT-IP) |
| Count | Number of times this block occurred (Stats view) |

**Investigation Workflow:** Check RPZ Hits for suspected devices. Multiple blocks to similar domains might indicate malware trying to contact command and control servers.

**False Positives:** If a legitimate domain is blocked, add it to your Allow List. Allow List entries take precedence over all RPZ feed rules.

---

### Research

A workspace for threat hunting and false-positive investigation, presented as three sub-tabs. Every view is read-only: nothing here changes DNS policy, and each request requires an authenticated session.

#### Newly Seen Queries

Allowed (non-blocked) FQDNs that were requested for the **first time** within the selected period and never before it. New names appearing on a network are worth a look: a device suddenly resolving a domain nobody has ever queried is the shape of both new software and freshly registered malicious infrastructure.

Filterable, sortable and paginated, with CSV export. Right-clicking an FQDN opens the shared context menu with research links, **Analyze**, and block/allow actions.

#### SQL Query

Runs your own read-only `SELECT` statements against the RpiDNS database when the built-in views do not answer the question — arbitrary joins across queries, hits, and assets, or aggregations over long periods.

Statements are validated as read-only and executed on a separate read-only connection, bounded to 30 seconds, and capped at 10,000 rows with a truncation indicator. Results are paginated with CSV export. The Available Tables list is one click away: clicking a table inserts `select * from <table>` into an empty editor, or appends the name to a statement you are already writing. See [docs/database.md](docs/database.md) for the schema.

#### Tools

One target, one **Analyze** button. Every tool that applies to the target runs and its result appears as a card, so a domain or address can be characterized in a single pass instead of one lookup at a time.

| Tool | Target | What it answers |
|------|--------|-----------------|
| RDAP / WHOIS | domain or IP | Registration data — registrar, abuse contact, creation/expiry dates, nameservers. Queries the registrable domain, so `test.example.com` is looked up as `example.com` and `www.bbc.co.uk` as `bbc.co.uk` |
| DNS records | domain | `A`, `AAAA`, `HTTPS` and `TXT` records |
| NS / MX records | domain | Delegation and mail routing |
| Reverse DNS (PTR) | IP | What the address calls itself |
| GeoIP | IP | Country, city, coordinates, network operator |
| ASN | IP | Autonomous system and announced prefix |
| ping | domain or IP | Reachability and round-trip time |
| traceroute | domain or IP | Network path |
| TLS certificate | domain | Certificate presented on port 443, including issuer and validity |
| Reputation / threat intel | domain | AlienVault OTX pulses referencing the indicator |
| Website preview | domain | Screenshot of the page, rendered headlessly |

Only the applicable tools are shown — a domain target omits the IP-only ones and vice versa. Results with a structured source (RDAP, reputation, GeoIP, ASN, and the DNS tools) are rendered readably with a per-card switch to the original JSON or raw `dig` output.

**Analyze from anywhere:** the same panel opens as a modal from the Query log, RPZ log, Newly seen queries context menus, and from the Dashboard TopX request widgets, with the target prefilled.

**Investigating blocked domains:** by default the tools resolve through the appliance itself, which answers blocked domains with the RPZ response — so a blocked domain's preview shows the block page and its DNS records show the block answer. Set **Research tools DNS resolver** in Admin > Settings (for example `1.1.1.1`) to look blocked domains up as they appear from outside. Every tool honors it, and a single run can override it from the Tools panel and reset back to the configured default.

---

### Administration

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_settings.png"></p>

#### Assets

Manage network devices tracked by RpiDNS. Assets can be automatically discovered or manually added.

| Column | Description |
|--------|-------------|
| Address | MAC or IP address (depending on tracking mode) |
| Name | Friendly name for easy identification |
| Vendor | Hardware manufacturer (auto-detected from MAC) |
| Added | Date/time when asset was first seen |
| Comment | Optional notes about the device |

**Actions:** Add, Edit, Delete, Refresh

**Tracking Mode:** MAC address tracking is recommended (consistent even when IPs change). Configure in Settings.

#### RPZ Feeds

Manage RPZ feeds that control DNS-level blocking. Feed order is critical - BIND evaluates feeds top to bottom, first match wins.

**Feed Source Types:**

| Type | Description |
|------|-------------|
| ioc2rpz.net | Open source threat intelligence feeds, auto-updated via zone transfers |
| Local | Custom feeds you create and manage directly |
| Third-Party | External RPZ feeds from other providers |

**Policy Actions:**

| Action | Description |
|--------|-------------|
| NXDOMAIN | Returns "domain does not exist" - most common blocking action |
| NODATA | Domain exists but has no records of requested type |
| PASSTHRU | Allow query to proceed (for whitelist feeds) |
| DROP | Silently drop query without response |
| CNAME | Redirect to different domain (e.g., block page) |
| GIVEN | Use action defined within feed rules |

**Actions:** add (per source type), edit, enable/disable, retransfer the zone on demand, and delete — optionally removing the zone file with it. Feeds are reordered by dragging a row, which matters because BIND stops at the first match. ioc2rpz.net feeds require a TSIG key (name, algorithm, secret) for the zone transfer.

Every change is applied by writing `named.conf`, validating it with `named-checkconf` and reloading BIND via `rndc`; if validation fails the previous configuration is restored automatically.

#### Block List

Custom collection of domains/IPs to block on your network.

| Column | Description |
|--------|-------------|
| Domain/IP | Domain name or IP address to block |
| Added | Date/time when entry was created |
| Active | Toggle to enable/disable the rule |
| *. (Subdomains) | Toggle to also block all subdomains |
| Expires | When the entry stops applying, or `Permanent` |
| Comment | Optional notes about why domain was blocked |

**Time-limited entries:** when adding or editing an entry, the expiration can be left at *Permanent*, set as a number of seconds from now, or set to an absolute date and time. Expired entries are disabled automatically and removed from the BIND RPZ zone by `expire_iocs.php`, which runs every minute. This is useful for a temporary unblock while troubleshooting, or for allowing a domain just long enough to complete a download.

#### Allow List

Domains that should never be blocked, regardless of RPZ feed rules. Allow List entries take precedence over all blocking rules.

Same interface as Block List, including time-limited entries. Use for handling false positives where legitimate domains are incorrectly blocked.

**Security Warning:** Verify domains are safe before adding to Allow List. Use the [Research](#research) tools to investigate.

#### Settings

| Setting | Description |
|---------|-------------|
| Data Retention | Days to keep data before automatic deletion (per table). Each row also shows the table's current size, row count and date range |
| Automatically create assets | Auto-add new devices when they make DNS queries |
| Track assets by | MAC Address (recommended) or IP Address |
| Dashboard show Top | Number of items in Dashboard widgets |
| Research tools DNS resolver | Resolver the [Research](#research) tools use, e.g. `1.1.1.1`. Blank resolves through the appliance, which answers blocked domains with the RPZ response. A single tool run can override it |

**Account Security:** a Change Password button is available here as well as in the user menu.

#### Tools

| Tool | Description |
|------|-------------|
| CA Root Certificate | Download root CA for SSL certificates (eliminates browser warnings on block pages) |
| Database Download | Backup SQLite database with all RpiDNS data |
| Database Import | Restore from backup (overwrites current data) |
| bind.log | General DNS server operational logs |
| bind_queries.log | Raw DNS query logs in BIND format |
| bind_rpz.log | RPZ-specific logs for troubleshooting |

#### User Management (Admin Only)

| Column | Description |
|--------|-------------|
| Username | Login name (case-sensitive, unique) |
| Admin | Administrator privileges status |
| Created | Account creation date |

**Actions:** Add User, Reset Password, Delete

**Note:** Cannot delete the last administrator account to prevent lockout.

---

### External Research Links

Alongside the built-in [Research](#research) tools, RpiDNS links out to third-party services for a second opinion:

| Service | Description |
|---------|-------------|
| DuckDuckGo | Privacy-focused search engine (exact-phrase search) |
| Google | General search engine (exact-phrase search) |
| VirusTotal | Domain/IP/URL malware validation with multi-engine scanning |
| DomainTools Whois | Domain and IP registration information |
| Robtex | IP, domain, AS, routes information |
| ThreatMiner | IOC threat intelligence portal |

These open in a new tab, and are reached by right-clicking a domain in the Query log, RPZ log or Newly seen queries, or by hovering a domain in the Dashboard widgets.

## Scripts

| Script | Description |
|--------|-------------|
| `rpidns_install.sh` | Installation script for Raspbian |
| `rpidns_install_openwrt.sh` | Installation script for OpenWrt |
| `init_db.php` | Database initialization |
| `clean_db.php` | Crontab script for DB cleanup |
| `expire_iocs.php` | Crontab script that auto-disables expired local indicators |
| `parse_bind_logs.php` | Parse bind logs, save to DB, aggregate data |
| `import_db.php` | Database import with schema upgrade and RPZ provisioning |
| `update_psl.php` | Refresh the bundled Public Suffix List used by the Research RDAP tool |

## ISC Bind Configuration

RpiDNS requires ISC Bind configured with:
- DNS query and RPZ hit logging enabled
- Local RPZs: `allow.ioc2rpz.rpidns`, `allow-ip.ioc2rpz.rpidns`, `block.ioc2rpz.rpidns`, `block-ip.ioc2rpz.rpidns`

## Detailed Documentation

For in-depth documentation on specific topics, see the `docs/` directory:

| Document | Description |
|----------|-------------|
| [Architecture](docs/architecture.md) | System architecture, data flow, and container deployment model |
| [Backend API](docs/backend-api.md) | PHP API endpoints, authentication, and BindConfigManager |
| [Frontend](docs/frontend.md) | Vue 3 components, composables, and build system |
| [Database](docs/database.md) | SQLite schema, aggregation tiers, and migrations |
| [Scripts](docs/scripts.md) | Shell and PHP maintenance scripts |
| [Docker Deployment](docs/docker-deployment.md) | Container configuration and deployment guide |
| [BIND Configuration](docs/bind-configuration.md) | ISC BIND DNS and RPZ setup |
| [Configuration Files](docs/configuration-files.md) | PHP config files and environment variables |

## Built With

- [Vue 3](https://vuejs.org/)
- [Bootstrap Vue Next](https://bootstrap-vue-next.github.io/bootstrap-vue-next/)
- [Vite](https://vitejs.dev/)
- [Axios](https://github.com/axios/axios)
- [ApexCharts](https://apexcharts.com/)
- [FontAwesome](https://fontawesome.com/)

## Support the Project

- [GitHub Sponsors](https://github.com/sponsors/Homas) (one-time, recurring)
- [PayPal](https://paypal.me/ioc2rpz) (one-time)

## Contact

- Email: feedback(at)ioc2rpz[.]net
- [Telegram](https://t.me/ioc2rpz)

## License

Copyright 2020-2026 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.

You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
