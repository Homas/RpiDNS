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
| `PUT` | `RPIsettings` | `assets_by`, `assets_autocreate`, `hits_raw`, `hits_5m`, `hits_1h`, `hits_1d`, `queries_raw`, `queries_5m`, `queries_1h`, `queries_1d`, `dash_topx`, `research_dns_server` | Update settings |

GET response includes per-table record counts, date ranges, sizes, and retention settings:

```json
{
  "status": "success",
  "retention": [
    ["queries_raw", 1048576, 50000, "2024-01-01T00:00:00Z", "2024-01-15T23:59:00Z", 14]
  ],
  "assets_by": "mac",
  "assets_autocreate": true,
  "dashboard_topx": 100,
  "research_dns_server": "1.1.1.1"
}
```

PUT writes the settings to `www/rpisettings.php` as a PHP file. Retention values are integers (days). See [configuration-files.md](./configuration-files.md) for details on the settings file format.

`research_dns_server` is the appliance-wide DNS resolver the Research tools inherit; an empty string means the appliance resolver. Because the settings file is generated PHP source, this value — the only free-text setting written to it — is validated as an IP address or hostname and then reduced to the characters those may contain. An invalid value is rejected with `{"status":"error","reason":"invalid DNS resolver: must be a valid IP address or hostname"}` and nothing is written.

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

## Research

The Research API serves the Research page (see [frontend.md](./frontend.md)). It provides unique allowed-query retrieval, direct read-only SQL execution, table-name discovery, and network research tool execution. Every Research endpoint is **read-only** with respect to database and system state and is gated behind session authentication.

**Common behavior across all Research endpoints:**

- **Authentication (Req 1.7, 9.1):** Each endpoint calls `requireResearchSession()` (from `www/rpi_admin/ResearchAuth.php`) as its first action, before any query is built, any input is validated, or any command is executed. A request without a valid `rpidns_session` is denied with an authentication-required response (HTTP 401) and no protected data is returned.
- **Validation before side effects (Req 9.4, 9.5):** Input and SQL validation always completes before any query or command runs. On a validation failure the request is rejected without execution, the database and system state are left unchanged, and a descriptive `reason` is returned.
- **Rejection auditing (Req 9.6):** Rejected write attempts and malformed inputs are recorded (via `RejectionAudit::record`) with the session identifier, the rejection category, and the originating endpoint.
- **No partial-as-complete (Req 2.10, 4.7, 4.10, 5.7, 8.11):** On error or timeout, partial results are never presented as a complete result set.

| Method | Request Name | Description |
|--------|-------------|-------------|
| `GET` | `research_unique` | First-seen allowed (non-blocked) FQDNs over a time range |
| `GET` | `research_config` | Appliance-wide tool defaults the Tools panel inherits |
| `GET` | `research_tables` | List available database table names |
| `POST` | `research_sql` | Execute an administrator-supplied read-only SELECT statement |
| `POST` | `research_tool` | Execute a network research tool against a validated target |

### GET research_unique

Returns the **newly seen** allowed FQDNs for the selected period, each with its total in-range query count and most-recent query time.

"Unique" here means *first seen*, not merely de-duplicated: an FQDN is returned only if it was requested for the first time inside the selected range and has **no recorded request before the range start**. A domain queried both inside and before the range is excluded, because it is not newly observed.

**Accepted inputs:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `period` | string | Time period (`30m`, `1h`, `1d`, `1w`, `30d`, `custom`) |
| `start_dt` | int | Start timestamp (Unix epoch) — used when `period=custom`, inclusive |
| `end_dt` | int | End timestamp (Unix epoch) — used when `period=custom`, inclusive |
| `filter` | string | Case-insensitive substring match on `fqdn` |
| `sortBy` | string | Sort column, one of `fqdn`, `cnt`, `last_seen` (default `cnt`; unknown values fall back to `cnt`) |
| `sortDesc` | string | `true` for descending order |
| `pp` | int | Per-page limit (1–500, default 100) |
| `cp` | int | Current page number |

**Validation behavior:** Only queries with `action='allowed'` are included in the in-range aggregation. The sort column is constrained to an allowlist; the filter value is escaped before use. The query runs `SELECT`-only and never modifies database state.

**First-seen (prior history) evaluation:**

- Prior activity is checked with `NOT EXISTS` against **all four query tiers** (`queries_raw`, `queries_5m`, `queries_1h`, `queries_1d`), so the lookback spans the full retained history (7 / 14 / 60 / 180 days by default) rather than the raw tier alone.
- Prior activity is **not** restricted to `action='allowed'`. A domain previously requested and blocked has still been requested before, so it is not newly observed.
- The range start used as the cut-off mirrors the lower bound of the matching aggregation branch: `start_dt` for custom ranges, `now - period` for periods up to one day, and the day-aligned `now - now % 86400 - period` for longer periods.
- Because the aggregated tiers store interval-floored timestamps, a bucket whose start falls before the range start counts as prior activity even if part of that interval overlaps the range. This deliberately errs toward excluding a domain rather than reporting a previously-seen domain as new.

**Success response:**

```json
{
  "status": "ok",
  "records": "42",
  "data": [
    { "fqdn": "example.com", "cnt": 17, "last_seen": "2024-01-15T10:30:00Z" }
  ]
}
```

**Error responses:**

- `401` — authentication required (no valid session).
- `{"status":"error","reason":"failed to retrieve unique allowed queries"}` — retrieval failed; no partial data is returned.

### GET research_config

Returns the appliance-wide Research tool defaults, so the Tools panel can prefill (and offer a reset to) the configured resolver rather than hardcoding its own default. Requires an authenticated session like every other research endpoint; touches neither the database nor system state.

```json
{
  "status": "ok",
  "data": { "dns_server": "1.1.1.1" }
}
```

`dns_server` is `$research_dns_server` from `rpisettings.php`, or `""` when unset (meaning the appliance resolver). A value that fails IP/hostname validation is reported as `""` rather than handed to the client, so a hand-edited settings file cannot inject one. The setting is read with an `isset()` guard because installs upgraded from an earlier release keep their existing settings file.

### GET research_tables

Lists the available table names so the SQL tool can build queries against the schema (Req 4.9).

**Accepted inputs:** none.

**Validation behavior:** Opens a **separate read-only** SQLite connection (`SQLITE3_OPEN_READONLY`) and queries `sqlite_master`, excluding internal `sqlite_*` objects. It never modifies database state.

**Success response:**

```json
{ "status": "ok", "data": ["assets", "queries_raw", "hits_raw", "..."] }
```

**Error responses:**

- `401` — authentication required.
- `{"status":"error","reason":"failed to retrieve table names"}` — the table list could not be read.

### POST research_sql

Executes a single administrator-supplied read-only `SELECT` (or `WITH ... SELECT`) statement against the database and returns the resulting rows.

**Accepted inputs:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `sql` | string | The SQL statement to execute (merged from the JSON body) |
| `cp` | int | Current page number (1-based, default 1) |
| `pp` | int | Rows per page (1–10,000, default 100) |
| `count` | bool | When `1`/true, also compute the bounded total row count. Sent on a new query submission; omitted for page navigation |

**Validation behavior:** The statement is validated by `SqlQueryValidator::validate()` **before any execution** (Req 4.1). The validator enforces:

- **Single statement only (Req 4.4):** multi-statement submissions are rejected without executing any statement.
- **Read-only entry point (Req 4.1, 4.2):** the first significant keyword must be `SELECT` or `WITH`.
- **No write operations (Req 4.3):** statements containing data-definition, data-manipulation, or side-effecting keywords are rejected.
- **Length bound (Req 4.11):** statements longer than 10,000 characters are rejected.

Valid queries execute against a **separate connection opened in read-only mode** (`SQLITE3_OPEN_READONLY`) as defense-in-depth (Req 4.5, 9.2). Execution is bounded to **30 seconds** (best-effort, via `set_time_limit`, `busyTimeout`, and a wall-clock guard in the row-fetch loop; Req 4.8, 4.10).

**Server-side pagination:** results are paginated rather than returned all at once. The validated statement is wrapped in a subquery (`SELECT * FROM (<sql>) LIMIT <pp> OFFSET <(cp-1)*pp>`) using server-controlled integers, so a single page is fetched per request. When `count=1`, a bounded count query (`SELECT COUNT(*) FROM (SELECT 1 FROM (<sql>) LIMIT 10001)`) computes the total, capped at **10,000 rows** with a `truncated` flag when the underlying result exceeds the cap (Req 4.6). Rows beyond the cap are not navigable. The client requests `count=1` once per new query and reuses the returned `totalRows` for page navigation; the "Copy CSV" control issues a single request with a large `pp` to export the full capped dataset.

**Success response** (paginated — columns are returned in query order, available even for a zero-row page; `totalRows`/`truncated` are present only when `count=1` was requested):

```json
{
  "status": "ok",
  "data": {
    "columns": ["fqdn", "cnt"],
    "rows": [["example.com", 17]],
    "rowCount": 1,
    "page": 1,
    "perPage": 100,
    "totalRows": 1,
    "truncated": false
  }
}
```

**Error responses:**

- `401` — authentication required.
- `{"status":"error","reason":"..."}` — validation rejection (write operation, multi-statement, over-length, or non-SELECT), returned **without executing** anything and with the audit recorded. The `reason` identifies that only read-only single-statement SELECT queries are permitted.
- `{"status":"error","reason":"query exceeded the 30-second execution limit"}` — the query was terminated by the execution bound (Req 4.10).
- `{"status":"error","reason":"<message>"}` — a syntactically invalid or runtime-failing query; the database is unchanged and no partial data is returned (Req 4.7).

### POST research_tool

Executes a network research tool against a validated target and returns its `ToolResult`. Covers the core tools (RDAP/WHOIS, `dig`, `ping`, `traceroute`) and the additional threat-hunting tools (`reverse_dns`, `nsmx`, `geoip`, `asn`, `tls_cert`, `reputation`, `website_preview`).

**Accepted inputs:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `tool` | string | Tool name — must be in the allowlist |
| `target` | string | Domain or IP target (at most 253 characters) |
| `dns_server` | string | DNS resolver for the resolver-aware tools (IP or hostname). Omit to inherit the configured default; send `""` to force the appliance resolver |

**Validation behavior (Req 6.5, 6.6, 8.10, 8.12):**

- **Tool allowlist:** an unknown or unsupported `tool` is rejected before any command is built.
- **Per-tool input class:** IP-only tools (`reverse_dns`, `geoip`, `asn`) require a valid IP address; domain-only tools (`nsmx`, `tls_cert`, `reputation`, `website_preview`) require a valid domain; core tools (`rdap`, `dig`, `ping`, `traceroute`) accept a valid domain or IP. Validation uses `InputValidator`. The frontend classifies the target the same way and only submits applicable tools.
- **DNS server:** when supplied, `dns_server` is validated as an IP address or hostname (Req 6.4). Resolver-aware tools are `dig`, `nsmx`, `reverse_dns`, and `website_preview`; all others ignore the parameter.
- **Resolver selection:** a request that omits `dns_server` inherits `$research_dns_server` from `rpisettings.php`; a request that sends it wins, including when it sends `""`, which selects the appliance resolver explicitly. An empty effective value means no `@server` argument (Req 6.3). A configured default that fails validation is discarded rather than used.
- **Preview resolution:** chromium has no "use this DNS server" switch, so when a resolver is in effect `website_preview` first resolves the target with it (via `dig`) and pins the browser to the answer with `--host-resolver-rules`. This is what lets a domain blocked by this appliance's RPZ render as the real site instead of the block page; SNI and the `Host` header still carry the original hostname. If the name does not resolve to an IPv4 address through that resolver, the response reports it rather than silently falling back to the appliance resolver.
- **Command injection prevention (Req 6.6):** `CommandBuilder` passes every input as a discrete argv slot (no shell), so inputs cannot alter command structure.
- **Bounded execution (Req 6.7, 6.8):** `ToolRunner` enforces a 30-second wall-clock bound and terminates the child process group on timeout; `ping`/`traceroute` are constrained to a fixed maximum number of probes. Output is captured and truncated to a maximum size (1 MiB), with a `truncated` flag.

**Success response** (`ToolResult` — `reason` is `null`, `"timeout"`, or `"tool_start_failed"`):

```json
{
  "status": "ok",
  "data": {
    "tool": "dig",
    "target": "example.com",
    "output": "...",
    "raw": null,
    "truncated": false,
    "exitError": false,
    "reason": null
  }
}
```

For the tools whose upstream is a JSON API (`geoip`, `asn`, `rdap`, `reputation`), `ResearchFormatter` replaces `output` with a human-readable summary and returns the pretty-printed JSON it was derived from in `raw`, so a client can offer a summary/JSON switch without a second request. `raw` is `null` whenever there is no distinct JSON view to show — for text tools such as `dig`, for output that is not valid JSON (a curl error is passed through untouched), and for JSON that has no summary renderer and is therefore already displayed as pretty JSON. It is capped at 256 KiB, since a pretty-printed OTX report with hundreds of pulses travels in the same response.

`nsmx` returns a combined `ToolResult`. `website_preview` returns `{ "image": <base64 PNG|null>, "reason": <string|null> }`; it is gated behind the `RESEARCH_WEBSITE_PREVIEW` feature flag, which defaults to whether a headless browser (`chromium`/`chrome`) is present on the host, so previews work out of the box on the RpiDNS web image and report `"website preview is disabled: no headless browser installed"` elsewhere. Define the constant in `rpisettings.php` to force it on or off. The screenshot file is the source of truth for success, since chromium writes unrelated diagnostics to stderr and may exit non-zero after producing a usable image.

**Error responses:**

- `401` — authentication required.
- `{"status":"error","reason":"unknown or unsupported tool"}` — tool not in the allowlist.
- `{"status":"error","reason":"invalid target: must be an IP address"}` / `"...must be a domain name"` / `"...must be a domain name or IP address"` — target failed its per-tool input validation (Req 6.5, 8.10).
- `{"status":"error","reason":"invalid dns_server: must be a valid IP address or hostname"}` — the `dig` DNS server failed validation.
- `{"status":"error","reason":"tool_start_failed"}` — the utility could not be started; system state is unchanged (Req 6.9). Within a `ToolResult`, this surfaces as `reason: "tool_start_failed"`.
- Within a `ToolResult`: `reason: "timeout"` when the 30-second bound is exceeded (Req 6.7); `exitError: true` when the utility exits non-zero (Req 6.12); `truncated: true` when output exceeded the maximum size (Req 6.2). Additional tools with external data sources surface `upstream_unavailable` when the source is unavailable or times out (Req 8.11).

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
