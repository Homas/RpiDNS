# BIND DNS Configuration

## Overview

RpiDNS uses ISC BIND as its DNS resolver with Response Policy Zone (RPZ) support for domain-based ad and malware blocking. The BIND configuration lives in `rpidns-docker/bind/named.conf` and is managed at runtime by the `BindConfigManager` PHP class (`www/rpi_admin/BindConfigManager.php`).

The Bind container's entrypoint script (`rpidns-docker/bind/entrypoint.sh`) handles initial setup — generating `rndc` keys, patching `named.conf`, initializing zone files, and starting `named` in the foreground.

## named.conf Structure

The configuration file is organized into the following sections:

### rndc Key and Controls

The entrypoint script auto-generates an `rndc` key if one doesn't exist and injects the `include` and `controls` directives into `named.conf`:

```
// Include rndc key for remote control (auto-added by entrypoint)
include "/etc/bind/rndc.key";

// Controls for rndc (auto-added by entrypoint)
controls {
    inet 127.0.0.1 port 953 allow { 127.0.0.1; } keys { "rndc-key"; };
};
```

The `rndc` key is generated using `rndc-confgen -a` and stored at `/etc/bind/rndc.key`. A companion `rndc.conf` is also created for the `rndc` client. These enable the web application to reload BIND configuration and trigger zone retransfers without restarting the container.

### ACLs

The default configuration uses inline ACL definitions within the `options` block:

```
allow-query { any; };
```

In production deployments, you can define named ACLs to restrict which clients can query the resolver:

```
acl "trusted" {
    192.168.0.0/16;
    10.0.0.0/8;
    localhost;
};
```

### Options

```
options {
    directory "/var/cache/bind";

    listen-on { any; };
    listen-on-v6 { any; };

    allow-query { any; };
    recursion yes;
    dnssec-validation auto;
    minimal-responses yes;

    pid-file "/var/run/named/named.pid";
};
```

Key settings:

| Option | Value | Purpose |
|---|---|---|
| `directory` | `/var/cache/bind` | Working directory for zone files and cache |
| `listen-on` | `any` | Accept queries on all IPv4 interfaces |
| `listen-on-v6` | `any` | Accept queries on all IPv6 interfaces |
| `allow-query` | `any` | Allow queries from all sources (restrict in production) |
| `recursion` | `yes` | Enable recursive resolution for clients |
| `dnssec-validation` | `auto` | Automatic DNSSEC validation using built-in trust anchors |
| `minimal-responses` | `yes` | Reduce response size by omitting unnecessary sections |

### Logging Channels

BIND logging is configured with two channels that write to the shared `/opt/rpidns/logs/` volume:

```
logging {
    channel default_log {
        file "/opt/rpidns/logs/bind.log" versions 3 size 5m;
        severity info;
        print-time yes;
        print-severity yes;
        print-category yes;
    };

    channel query_log {
        file "/opt/rpidns/logs/bind_queries.log" versions 3 size 10m;
        severity info;
        print-time yes;
    };

    category default { default_log; };
    category queries { query_log; };
};
```

| Channel | File | Rotation | Purpose |
|---|---|---|---|
| `default_log` | `bind.log` | 3 versions, 5 MB each | General BIND operational messages |
| `query_log` | `bind_queries.log` | 3 versions, 10 MB each | DNS query log consumed by `parse_bind_logs.php` |

The query log is the primary data source for the RpiDNS data pipeline. The Web container's `parse_bind_logs.php` cron job reads this file every minute to extract query data into SQLite.

### Response-Policy Statement

The `response-policy` block defines which RPZ zones are active and what policy action each applies. This is the core of RpiDNS's blocking behavior:

```
response-policy {
    zone "allow.ioc2rpz.rpidns" policy passthru;
    zone "allow-ip.ioc2rpz.rpidns" policy passthru;
    zone "block.ioc2rpz.rpidns" policy nxdomain;
    zone "block-ip.ioc2rpz.rpidns" policy nxdomain;
    zone "malware.ioc2rpz" policy given;        # ioc2rpz.net feed
    zone "custom-blocklist.example" policy drop; # third-party feed
};
```

Each line follows the format:

```
zone "<zone-name>" policy <action> [<cname-target>]; [# description]
```

Disabled feeds are commented out with `//` and preserved in the configuration so they can be re-enabled without losing their settings.

See [Feed Ordering](#feed-ordering) for how the order of zones in this statement affects DNS resolution.

### Zone Definitions

Each RPZ feed referenced in the `response-policy` statement requires a corresponding `zone` block. The zone type depends on the feed source:

```
// Local zone (primary)
zone "block.ioc2rpz.rpidns" {
    type primary;
    file "/var/cache/bind/block.ioc2rpz.rpidns";
    allow-update { localhost; };
};

// ioc2rpz.net zone (secondary with TSIG)
zone "malware.ioc2rpz" {
    type secondary;
    file "/var/cache/bind/malware.ioc2rpz";
    primaries { 94.130.30.123 key "my-tsig-key"; };
};

// Third-party zone (secondary)
zone "third-party-blocklist.example" {
    type secondary;
    file "/var/cache/bind/third-party-blocklist.example";
    primaries { 203.0.113.10 key "vendor-key"; };
};
```

The default configuration also includes standard infrastructure zones:

```
// Root hints
zone "." {
    type hint;
    file "/var/bind/root.cache";
};

// Localhost zones
zone "localhost" {
    type master;
    file "/etc/bind/db.empty.pi";
};

zone "127.in-addr.arpa" {
    type master;
    file "/etc/bind/db.empty.pi";
};
```

### TSIG Keys

TSIG (Transaction Signature) keys authenticate zone transfers between BIND and upstream RPZ providers. Keys are defined at the top level of `named.conf`:

```
key "my-tsig-key" {
    algorithm hmac-sha256;
    secret "base64-encoded-secret==";
};
```

Key details:

- Keys are referenced in zone `primaries` blocks: `primaries { <ip> key "<key-name>"; };`
- The `BindConfigManager` auto-detects existing TSIG keys and reuses them for new ioc2rpz.net feeds
- New keys can be added when configuring third-party feeds via the API
- Supported algorithms include `hmac-sha256` (default), `hmac-sha512`, and others supported by BIND

## Predefined Local RPZ Zones

RpiDNS ships with four predefined local RPZ zones that cannot be deleted. These zones are managed via `nsupdate` from the web interface and provide the user's custom allow/block lists.

| Zone Name | Type | Purpose | Default Action |
|---|---|---|---|
| `allow.ioc2rpz.rpidns` | Allow (domain) | Whitelist specific domain names to bypass blocking | `passthru` |
| `allow-ip.ioc2rpz.rpidns` | Allow (IP) | Whitelist specific IP addresses to bypass blocking | `passthru` |
| `block.ioc2rpz.rpidns` | Block (domain) | Block specific domain names | `nxdomain` |
| `block-ip.ioc2rpz.rpidns` | Block (IP) | Block specific IP addresses | `nxdomain` |

All four zones are configured as `type primary` with `allow-update { localhost; }` so the web application can add and remove entries dynamically using `nsupdate`.

### Zone File Template

Local zones are initialized from `rpidns-docker/bind/db.empty.pi`:

```
$TTL    86400
@       IN      SOA     localhost. root.localhost. (
                              1         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                          86400 )       ; Negative Cache TTL
@       IN      NS      localhost.
@       IN      A       127.0.0.1
@       IN      AAAA    ::1
```

### Allow vs. Block Zones

The allow zones (`allow.ioc2rpz.rpidns`, `allow-ip.ioc2rpz.rpidns`) are restricted to the `passthru` policy action only. This ensures entries in these zones always permit resolution, overriding any blocking from other feeds.

The block zones (`block.ioc2rpz.rpidns`, `block-ip.ioc2rpz.rpidns`) support `nxdomain`, `nodata`, `drop`, and `cname` actions — but not `passthru` or `given`.

The allow zones should be placed before block zones in the response-policy statement so that whitelisted domains take precedence.

## RPZ Policy Actions

BIND RPZ supports six policy actions that determine how matching DNS queries are handled:

| Action | Behavior | Use Case |
|---|---|---|
| `NXDOMAIN` | Returns "domain does not exist" response | Standard blocking — clients see the domain as non-existent |
| `NODATA` | Returns empty response (no answer records) | Soft blocking — domain exists but has no records |
| `PASSTHRU` | Allows the query through without modification | Whitelisting — exempts domains from other RPZ rules |
| `DROP` | Silently drops the query (no response sent) | Aggressive blocking — client times out |
| `CNAME` | Redirects the query to a specified target domain | Redirect blocking — sends clients to a walled garden or info page |
| `GIVEN` | Uses the policy defined within the RPZ zone data itself | Delegated policy — the upstream RPZ provider controls the action |

### Valid Actions by Feed Type

Not all actions are valid for all feed types. The `BindConfigManager` enforces these constraints:

| Feed Type | Valid Actions | Notes |
|---|---|---|
| Predefined allow feeds | `passthru` only | Allow feeds must always pass through |
| Predefined block feeds | `nxdomain`, `nodata`, `drop`, `cname` | Cannot use `passthru` or `given` |
| ioc2rpz.net feeds | All six actions | `given` is the typical default (defers to upstream policy) |
| Local feeds (user-added) | All six actions | `nxdomain` is the typical default |
| Third-party feeds | All six actions | `nxdomain` is the typical default |

### CNAME Action

When using the `cname` action, a target domain must be specified:

```
zone "block.ioc2rpz.rpidns" policy cname walled-garden.example.com;
```

This redirects all blocked queries to the specified domain, which can serve an informational page explaining why the domain was blocked.

## Feed Source Types

RpiDNS supports three feed source types, each with different BIND configuration:

### 1. ioc2rpz.net Feeds

Feeds from the [ioc2rpz.net](https://ioc2rpz.net) threat intelligence service. These are secondary zones that pull RPZ data from the ioc2rpz.net server via authenticated zone transfers (AXFR/IXFR).

```
zone "malware.ioc2rpz" {
    type secondary;
    file "/var/cache/bind/malware.ioc2rpz";
    primaries { 94.130.30.123 key "my-tsig-key"; };
};
```

Characteristics:
- Zone type: `secondary`
- Primary server: `94.130.30.123` (ioc2rpz.net)
- TSIG authentication required — the key is shared across all ioc2rpz.net feeds
- Feed names contain `.ioc2rpz` but not `.rpidns`
- Default policy action: `given` (uses the action defined in the zone data by the provider)
- Only the policy action can be modified; the primary server and TSIG key are fixed
- Available feeds can be discovered via the `GET ioc2rpz_available` API endpoint

### 2. Local Feeds

User-managed RPZ zones stored locally on the BIND server. Entries are added and removed via `nsupdate` through the web interface.

```
zone "custom-block.rpidns" {
    type primary;
    file "/var/cache/bind/custom-block.rpidns";
    allow-update { localhost; };
};
```

Characteristics:
- Zone type: `primary`
- No upstream server — data is managed locally
- `allow-update { localhost; }` enables dynamic updates via `nsupdate`
- Feed names typically contain `.rpidns`
- Default policy action: `nxdomain`
- The four predefined zones (allow/block domain and IP) are local feeds
- Zone files are initialized from the `db.empty.pi` template

### 3. Third-Party Feeds

RPZ feeds from external providers other than ioc2rpz.net. These are secondary zones that pull data from a user-specified primary server.

```
zone "vendor-blocklist.example" {
    type secondary;
    file "/var/cache/bind/vendor-blocklist.example";
    primaries { 203.0.113.10 key "vendor-key"; };
};
```

Characteristics:
- Zone type: `secondary`
- Primary server: user-specified IP address (required)
- TSIG authentication: optional, with user-provided key name, algorithm, and secret
- Default policy action: `nxdomain`
- All configuration fields (primary server, TSIG key, policy action) can be modified
- A new TSIG key definition is added to `named.conf` if a key secret is provided

### Configuration Comparison

| Property | ioc2rpz.net | Local | Third-Party |
|---|---|---|---|
| Zone type | `secondary` | `primary` | `secondary` |
| Primary server | `94.130.30.123` (fixed) | N/A | User-specified (required) |
| TSIG key | Shared, auto-detected | N/A | Optional, user-provided |
| `allow-update` | No | `{ localhost; }` | No |
| Default action | `given` | `nxdomain` | `nxdomain` |
| Modifiable fields | Action only | Action, entries via nsupdate | Action, primary server, TSIG key |
| Can be deleted | Yes | Predefined: No; User-added: Yes | Yes |
| Zone retransfer | Yes (via `rndc retransfer`) | N/A | Yes (via `rndc retransfer`) |

## Feed Ordering

The order of zones in the `response-policy` statement determines evaluation priority. BIND uses a **first match wins** strategy — when a DNS query matches entries in multiple RPZ zones, the action from the first matching zone in the list is applied.

### How It Works

```
response-policy {
    zone "allow.ioc2rpz.rpidns" policy passthru;       # 1st - checked first
    zone "allow-ip.ioc2rpz.rpidns" policy passthru;     # 2nd
    zone "block.ioc2rpz.rpidns" policy nxdomain;         # 3rd
    zone "block-ip.ioc2rpz.rpidns" policy nxdomain;      # 4th
    zone "malware.ioc2rpz" policy given;                  # 5th - checked last
};
```

In this example, if `example.com` appears in both `allow.ioc2rpz.rpidns` and `block.ioc2rpz.rpidns`, the `passthru` action from the allow zone takes effect because it appears first.

### Recommended Ordering

1. **Allow zones first** — ensures whitelisted domains are never blocked
2. **User block zones** — custom block lists take priority over external feeds
3. **External feeds last** — ioc2rpz.net and third-party feeds serve as the baseline

### Managing Feed Order

Feed order is managed through the `PUT rpz_feeds_order` API endpoint, which accepts an array of feed names in the desired order. The `BindConfigManager.updateFeedOrder()` method:

1. Validates that all existing feeds are present in the new order array
2. Creates a backup of the current configuration
3. Rebuilds the `response-policy` statement with zones in the new order
4. Validates the updated configuration using `named-checkconf`
5. Rolls back to the backup if validation fails

Disabled feeds (commented out with `//`) retain their position in the ordering and are preserved during reordering.

## Entrypoint Initialization

The Bind container entrypoint (`rpidns-docker/bind/entrypoint.sh`) performs the following setup before starting `named`:

1. **rndc key generation** — creates `/etc/bind/rndc.key` and `/etc/bind/rndc.conf` if they don't exist
2. **named.conf patching** — injects `include "/etc/bind/rndc.key"` and `controls` block if not already present
3. **Zone file initialization** — copies `db.empty.pi` to `/var/cache/bind/db.local` if missing
4. **Permission setup** — ensures `/var/cache/bind` and `/opt/rpidns/logs` are writable by the `named` user
5. **Syslog forwarding** (optional) — if `RPIDNS_LOGGING=forward` and `RPIDNS_LOGGING_HOST` is set, configures rsyslog to forward BIND logs to the Web container on port 10514
6. **IPv6 detection** — checks for a public IPv6 address; if none found, starts `named` with `-4` (IPv4-only mode)
7. **Start named** — runs `named -f -u named [-4] -c /etc/bind/named.conf` in the foreground

## Configuration Management

The `BindConfigManager` class (`www/rpi_admin/BindConfigManager.php`) provides programmatic management of the BIND configuration:

| Operation | Method | Description |
|---|---|---|
| List feeds | `getFeeds()` | Parses response-policy and zone definitions to return all configured feeds |
| Add feeds | `addFeeds()` | Adds zone config and response-policy entry for new feeds |
| Update feed | `updateFeed()` | Modifies policy action, description, or zone config |
| Remove feed | `removeFeed()` | Removes from response-policy and zone config (predefined feeds protected) |
| Reorder feeds | `updateFeedOrder()` | Rebuilds response-policy with new zone ordering |
| Enable/disable | `setFeedEnabled()` | Comments/uncomments the feed in response-policy |
| Retransfer | `retransferZone()` | Triggers `rndc retransfer` for secondary zones |
| Validate | `validate()` | Runs `named-checkconf` against the configuration |
| Backup | `backup()` | Creates timestamped backup (keeps last 10) |
| Restore | `restore()` | Restores from a backup after validation |
| Reload | `reloadBind()` | Validates config then runs `rndc reload` |

All write operations (add, update, remove, reorder) follow a safe pattern:
1. Create a timestamped backup
2. Apply changes to the configuration
3. Validate with `named-checkconf`
4. Roll back to backup if validation fails

Backups are stored in `/opt/rpidns/backups/bind/` with automatic cleanup keeping the 10 most recent.

## Related Documentation

- [Docker Deployment](./docker-deployment.md) — Bind container Dockerfile, entrypoint, volumes, and health checks
- [Backend API](./backend-api.md) — RPZ feed management API endpoints (`rpz_feeds`, `rpz_feed`, `rpz_feeds_order`, etc.)
- [Scripts](./scripts.md) — `parse_bind_logs.php` (reads BIND query logs), `import_db.php` (RPZ provisioning via nsupdate)
- [Architecture](./architecture.md) — System data flow from DNS queries through BIND to the frontend
- [Configuration Files](./configuration-files.md) — Environment variables and related config files
- [Database](./database.md) — SQLite tables storing parsed BIND query and RPZ hit data
- [Frontend](./frontend.md) — RPZ Feeds admin component for managing feeds through the UI
- [README](../README.md) — Project overview and ISC BIND configuration summary
