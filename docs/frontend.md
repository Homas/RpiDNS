# Frontend Documentation

## Overview

The RpiDNS frontend is a single-page application built with Vue 3 and Bootstrap Vue Next. It provides a tab-based interface for monitoring DNS queries, managing RPZ feeds, configuring block/allow lists, and administering the system. The application communicates with the PHP backend API (see [backend-api.md](./backend-api.md)) via Axios HTTP requests.

**Key technologies:**
- Vue 3 (Composition API)
- Bootstrap Vue Next (`bootstrap-vue-next`)
- Vite (build tooling)
- Axios (HTTP client)
- ApexCharts via `vue3-apexcharts` (dashboard charts)
- FontAwesome (icons)

**Source location:** `rpidns-frontend/`

## Component Hierarchy

```
App.vue (root)
├── LoginPage                          — Authentication gate
├── Dashboard                          — DNS stats overview
│   ├── CustomPeriodPicker             — Date/time range selector
│   └── ResearchLinks                  — External threat intel links
├── QueryLog                           — DNS query log viewer
│   ├── CustomPeriodPicker
│   └── ResearchLinks
├── RpzHits                            — Blocked query log viewer
│   ├── CustomPeriodPicker
│   └── ResearchLinks
├── AdminTabs                          — Admin panel container
│   ├── Assets                         — Network device management
│   ├── RpzFeeds                       — RPZ feed configuration
│   │   ├── AddIoc2rpzFeed             — Add ioc2rpz.net feed modal
│   │   ├── AddLocalFeed               — Add local feed modal
│   │   ├── AddThirdPartyFeed          — Add third-party feed modal
│   │   └── EditFeed                   — Edit feed modal
│   ├── BlockList                      — Custom block list
│   ├── AllowList                      — Custom allow list
│   ├── Settings                       — App settings & retention
│   │   └── PasswordChange             — Password change modal
│   ├── Tools                          — Downloads & DB import
│   └── UserManager (admin only)       — User administration
│       └── AddUser                    — Create user modal
├── DonateContent                      — Donation/support info
├── HelpContent                        — Built-in help documentation
└── Modals (rendered at root level)
    ├── PasswordChange                 — User password change
    ├── AddAsset                       — Add/edit network asset
    ├── AddIOC                         — Add/edit block/allow entry
    └── ImportDB                       — Database import with progress
```

App.vue manages authentication state, tab navigation (via `BTabs` with URL hash routing `#i2r/{tab}`), and provides `currentUser`, `isAdmin`, and `isAuthenticated` to child components via Vue's `provide`/`inject`. The navigation sidebar is collapsible and persists its state in `localStorage`.

## Page Components

### Dashboard (`src/components/Dashboard.vue`)

The main landing page after login. Displays eight statistical widget cards in two rows and a Queries per Minute area chart.

| Props | Type | Description |
|-------|------|-------------|
| `isActive` | `Boolean` | Whether this tab is currently visible |
| `customStart` | `Number` | Custom period start (Unix timestamp) |
| `customEnd` | `Number` | Custom period end (Unix timestamp) |

| Events | Payload | Description |
|--------|---------|-------------|
| `navigate` | `{ tab, filter, period, type, customStart?, customEnd? }` | Navigate to Query Log or RPZ Hits with filter |
| `add-ioc` | `{ ioc, type }` | Open Add IOC modal to block/allow a domain |
| `custom-period-change` | `{ start_dt, end_dt }` | Propagate custom period to parent |

**Widgets (top row — allowed traffic):** TopX Allowed Requests, TopX Allowed Clients, TopX Allowed Request Types, RpiDNS Stats (server metrics).

**Widgets (bottom row — blocked traffic):** TopX Blocked Requests, TopX Blocked Clients, TopX Feeds, TopX Servers.

**Period options:** 30m, 1h, 1d, 1w, 30d, custom. Auto-refresh via `useAutoRefresh` composable (60s interval, stored in `localStorage` key `rpidns_autorefresh_dashboard`).

**API endpoints used:** `dash_topX_req`, `dash_topX_client`, `dash_topX_req_type`, `server_stats`, `dash_topX_breq`, `dash_topX_bclient`, `dash_topX_feeds`, `dash_topX_server`, `qps_chart`.

### QueryLog (`src/components/QueryLog.vue`)

Paginated, filterable table of DNS query records. Supports Logs (individual records with timestamps) and Stats (aggregated by selected fields) view modes.

| Props | Type | Description |
|-------|------|-------------|
| `filter` | `String` | Pre-applied filter string (e.g., `fqdn=example.com`) |
| `period` | `String` | Time period (`30m`, `1h`, `1d`, `1w`, `30d`, `custom`) |
| `logs_height` | `Number` | Table container height in pixels |
| `isActive` | `Boolean` | Whether this tab is currently visible |
| `customStart` | `Number` | Custom period start (Unix timestamp) |
| `customEnd` | `Number` | Custom period end (Unix timestamp) |

| Events | Payload | Description |
|--------|---------|-------------|
| `add-ioc` | `{ ioc, type }` | Block or allow a domain |
| `custom-period-change` | `{ start_dt, end_dt }` | Propagate custom period |

**Table columns:** Local Time (logs mode), Client, Server, Request (FQDN), Type, Class, Options, Count, Action. In Stats mode, column checkboxes control grouping fields.

**API endpoint:** `queries_raw` with parameters for period, pagination (`cp`, `pp`), filter, log type (`ltype`), selected fields, and sort.

### RpzHits (`src/components/RpzHits.vue`)

Paginated, filterable table of RPZ-blocked DNS queries. Structurally similar to QueryLog with Logs/Stats toggle.

| Props | Type | Description |
|-------|------|-------------|
| `filter` | `String` | Pre-applied filter string |
| `period` | `String` | Time period |
| `logs_height` | `Number` | Table container height |
| `isActive` | `Boolean` | Whether this tab is currently visible |
| `customStart` | `Number` | Custom period start (Unix timestamp) |
| `customEnd` | `Number` | Custom period end (Unix timestamp) |

| Events | Payload | Description |
|--------|---------|-------------|
| `add-ioc` | `{ ioc, type }` | Allow a blocked domain |
| `custom-period-change` | `{ start_dt, end_dt }` | Propagate custom period |

**Table columns:** Local Time, Client, Request, Action, Rule, Type, Count.

**API endpoint:** `hits_raw`.

### LoginPage (`src/components/LoginPage.vue`)

Session-based authentication form. Submits credentials to `/rpi_admin/auth.php?action=login` via `fetch`. Handles rate limiting (HTTP 429) and displays appropriate error messages.

| Events | Payload | Description |
|--------|---------|-------------|
| `login-success` | `user` object | Emitted on successful authentication |

### HelpContent (`src/components/HelpContent.vue`)

Built-in help documentation with a collapsible sidebar navigation. Covers Getting Started, Dashboard, Query Log, RPZ Hits, Admin Panel, and Common Actions sections. Content is static HTML rendered within a Vue component.

### DonateContent (`src/components/DonateContent.vue`)

Static page with donation options for the ioc2rpz project: GitHub Sponsors, PayPal, Zelle, and cryptocurrency addresses (BTC, ETH, USDT, USDC). Includes links to the project website and GitHub repository.

### CustomPeriodPicker (`src/components/CustomPeriodPicker.vue`)

Modal dialog for selecting a custom date/time range. Used by Dashboard, QueryLog, and RpzHits.

| Props | Type | Description |
|-------|------|-------------|
| `show` | `Boolean` | Controls modal visibility (v-model) |
| `initialStart` | `Date` | Pre-populated start date |
| `initialEnd` | `Date` | Pre-populated end date |

| Events | Payload | Description |
|--------|---------|-------------|
| `update:show` | `Boolean` | Two-way binding for visibility |
| `apply` | `{ start_dt, end_dt }` | Unix timestamps for selected range |
| `cancel` | — | User cancelled selection |

Validates that start is before end and both fields are filled. Defaults to the last hour if no initial values are provided.

### ResearchLinks (`src/components/ResearchLinks.vue`)

Renders external threat intelligence lookup links for a given domain. Used in Dashboard widget popovers.

| Props | Type | Description |
|-------|------|-------------|
| `domain` | `String` (required) | Domain name to research |

**Integrated tools:**
- DuckDuckGo search
- Google search
- VirusTotal domain lookup
- DomainTools Whois
- Robtex DNS lookup
- ThreatMiner domain analysis


## Admin Sub-Components

All admin components are located in `src/components/Admin/` and rendered within the `AdminTabs` container.

### AdminTabs (`Admin/AdminTabs.vue`)

Tab container for all admin sub-components. Uses `BTabs` with card layout. The Users tab is conditionally rendered based on `isAdmin` (injected from App.vue). Automatically refreshes the active sub-component's data when switching tabs.

| Props | Type | Description |
|-------|------|-------------|
| `logs_height` | `Number` | Table container height passed to child components |

| Events | Payload | Description |
|--------|---------|-------------|
| `navigate` | navigation data | Forwarded from Assets |
| `add-asset` | asset data | Open AddAsset modal |
| `delete-asset` | asset data | Confirm asset deletion |
| `add-ioc` | IOC data | Open AddIOC modal |
| `delete-ioc` | IOC data | Confirm IOC deletion |
| `show-info` | `{ msg, time }` | Display info toast |
| `open-import-modal` | `{ db_import_type }` | Open ImportDB modal |

### Assets (`Admin/Assets.vue`)

Manages network devices (assets) tracked by MAC or IP address. Provides a filterable table with add, edit, and delete operations. Uses the `useApi` composable for API calls.

**API endpoint:** `assets` (GET, POST, PUT, DELETE). Listens for `refresh-table` custom DOM events to sync after external changes.

**Table columns:** Address, Name, Vendor, Added date, Comment.

### RpzFeeds (`Admin/RpzFeeds.vue`)

Manages RPZ feed configuration. Supports three feed source types: ioc2rpz.net, local, and third-party. Features drag-and-drop row reordering to control feed priority (first match wins in BIND's response-policy).

**Key features:**
- Add feeds via three separate modals (ioc2rpz.net, local, third-party)
- Edit feed settings (policy action, CNAME target, primary server, TSIG)
- Enable/disable feeds
- Delete feeds (predefined local RPZ zones cannot be deleted)
- Retransfer zone (available for non-local feeds only)
- Drag-and-drop reordering with backend persistence

**Predefined feeds (non-deletable):** `allow.ioc2rpz.rpidns`, `block.ioc2rpz.rpidns`, `allow-ip.ioc2rpz.rpidns`, `block-ip.ioc2rpz.rpidns`.

**API endpoints:** `rpz_feeds` (GET), `rpz_feed` (POST, PUT, DELETE), `rpz_feeds_order` (PUT), `rpz_feed_status` (PUT), `retransfer_feed` (PUT).

### BlockList (`Admin/BlockList.vue`)

Manages custom domain/IP block rules. Each entry has an IOC (domain or IP), active toggle, subdomain wildcard toggle (`*.`), and optional comment. Inline toggles for active/subdomain status update immediately via PUT.

**API endpoint:** `blacklist` (GET, POST, PUT, DELETE).

### AllowList (`Admin/AllowList.vue`)

Manages custom domain/IP allow rules. Structurally identical to BlockList. Allow list entries override RPZ feed blocks.

**API endpoint:** `whitelist` (GET, POST, PUT, DELETE).

### Settings (`Admin/Settings.vue`)

Displays data retention statistics per table (size, row count, date range) and allows configuring retention periods (in days). Also provides miscellaneous settings:
- Auto-create assets toggle
- Track assets by MAC or IP
- Dashboard TopX count
- Password change (via embedded PasswordChange modal)

**API endpoint:** `RPIsettings` (GET, PUT).

### Tools (`Admin/Tools.vue`)

Provides download links and database import functionality:
- **CA Root Certificate** — Download the SSL CA certificate for browser installation
- **Database** — Download SQLite DB backup or import a DB file
- **ISC BIND Logs** — Download `bind.log`, `bind_queries.log`, `bind_rpz.log`

**API endpoint:** `download` (GET with `file` parameter). Import triggers the `ImportDB` modal with all table types pre-selected.

### UserManager (`Admin/UserManager.vue`)

Admin-only user management. Lists all users with username, admin status, and creation date. Supports adding users, resetting passwords (generates random password shown once), and deleting users (last admin cannot be deleted).

**API endpoints (via auth.php):** `users` (GET), `create_user` (POST), `reset_password` (POST), `delete_user` (POST).

## Modal Components

All modals are in `src/components/modals/` and expose `show()` / `hide()` methods via Vue's `expose`.

### AddAsset (`modals/AddAsset.vue`)

Add or edit a network asset. Fields: address (MAC or IP depending on `assetsBy` setting), name, vendor, comment. Creates via POST or updates via PUT based on `rowid` prop.

### AddIOC (`modals/AddIOC.vue`)

Add or edit a block/allow list entry. Fields: IOC (domain/IP), include subdomains toggle, comment, active toggle. The `iocType` prop (`bl` or `wl`) determines which list is targeted.

### AddIoc2rpzFeed (`modals/AddIoc2rpzFeed.vue`)

Fetches available feeds from ioc2rpz.net (via `ioc2rpz_available` API endpoint), displays them in a selectable table with feed name, type (community/premium), rule count, and description. Supports bulk selection with a policy action selector. Requires a TSIG key configured in BIND.

**Policy actions:** given, nxdomain, nodata, passthru, drop, cname.

### AddLocalFeed (`modals/AddLocalFeed.vue`)

Creates a local RPZ zone. Fields: feed name (DNS name validated), policy action, CNAME target (if applicable), description. Validates DNS naming conventions (alphanumeric, hyphens, dots; max 253 chars; labels max 63 chars).

### AddThirdPartyFeed (`modals/AddThirdPartyFeed.vue`)

Adds a third-party RPZ feed via zone transfer. Fields: feed name, primary server IP, optional TSIG authentication (key name, algorithm, secret), policy action, CNAME target, description.

**TSIG algorithms:** HMAC-SHA256 (recommended), HMAC-SHA512, HMAC-SHA384, HMAC-SHA224, HMAC-SHA1 (legacy), HMAC-MD5 (deprecated).

### AddUser (`modals/AddUser.vue`)

Creates a new user account. Fields: username, password, confirm password, admin privileges checkbox. Password validation: 8+ characters with uppercase, lowercase, number, and symbol — or 18+ character passphrase.

### EditFeed (`modals/EditFeed.vue`)

Edits an existing RPZ feed's settings. Feed name is read-only. Available fields depend on source type:
- **ioc2rpz.net:** Policy action and CNAME target only
- **Local:** Policy action, CNAME target, description
- **Third-party:** Primary server, TSIG settings, policy action, CNAME target, description

Predefined allow feeds are restricted to `passthru` action. Predefined block feeds cannot use `passthru` or `given`.

### ImportDB (`modals/ImportDB.vue`)

Uploads a SQLite database file (`.sqlite`, `.gzip`, `.zip`) with selectable import targets: assets, block list, allow list, and query/hits logs at all aggregation tiers (raw, 5m, 1h, 1d). Shows upload progress bar and validation spinner.

### PasswordChange (`modals/PasswordChange.vue`)

Changes the current user's password. Fields: current password, new password, confirm new password. Same password complexity rules as AddUser. Submits to `/rpi_admin/auth.php?action=change_password`.

## Composables

Located in `src/composables/`.

### useApi (`composables/useApi.js`)

Centralized API client wrapping Axios for all backend communication.

**Parameters:** None (uses global `API_BASE = '/rpi_admin/rpidata.php'`).

**Return values:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `get` | `(params: Object) → Promise<any>` | GET request with query parameters |
| `post` | `(params: Object, data: Object) → Promise<any>` | POST with query params and body |
| `put` | `(params: Object, data: Object) → Promise<any>` | PUT with query params and body |
| `del` | `(params: Object) → Promise<any>` | DELETE with query parameters |
| `getTableData` | `(ctx: Object) → Promise<{items, records}>` | Table data provider with sort support |
| `uploadFile` | `(params, formData, onProgress, cancelToken) → Promise<any>` | File upload with progress tracking |
| `createCancelToken` | `() → CancelTokenSource` | Create Axios cancel token |
| `isCancel` | `(error) → boolean` | Check if error is a cancellation |

Includes a global Axios response interceptor that dispatches a `session-expired` custom event on HTTP 401 responses, triggering automatic logout in App.vue.

### useAutoRefresh (`composables/useAutoRefresh.js`)

Manages periodic data refresh with localStorage persistence.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `storageKey` | `String` | localStorage key for persisting enabled state |
| `refreshFn` | `Function` | Callback invoked on each refresh tick |
| `isActiveFn` | `Function` | Returns `true` if the owning tab is active (default: `() => true`) |

**Return values:**

| Property | Type | Description |
|----------|------|-------------|
| `autoRefreshEnabled` | `Ref<Boolean>` | Reactive toggle for auto-refresh |

Refresh interval is 60 seconds. Toggling `autoRefreshEnabled` on triggers an immediate refresh. The interval runs continuously but only calls `refreshFn` when both enabled and active.

### useWindowSize (`composables/useWindowSize.js`)

Tracks window dimensions and calculates responsive layout values. Note: this composable uses the Vue 2 `Vue.observable` API and is a legacy holdover — the active responsive logic in App.vue uses inline `window.addEventListener('resize', ...)` instead.

**Return values:**

| Property | Type | Description |
|----------|------|-------------|
| `state.windowInnerWidth` | `Number` | Current window width |
| `state.logs_height` | `Number` | Calculated log table height (`innerHeight > 400 ? innerHeight - 240 : 150`) |
| `state.logs_pp` | `Number` | Logs per page based on viewport |
| `initWindowSize()` | `Function` | Start tracking (call in `mounted`) |
| `destroyWindowSize()` | `Function` | Stop tracking (call in `beforeUnmount`) |

Also exports `useSharedWindowSize()` for singleton access across components.

## Build System

### Vite Configuration (`vite.config.js`)

```js
base: '/rpi_admin/dist/'    // Deployed under the PHP admin path
```

**Manual chunks** for optimized loading:
- `vendor-vue` — Vue core
- `vendor-bootstrap` — Bootstrap Vue Next + Bootstrap CSS
- `vendor-charts` — ApexCharts + vue3-apexcharts
- `vendor-utils` — Axios

**Path alias:** `@` → `src/`

**Dev server proxy:** `/rpi_admin/rpidata.php` → `http://localhost:8080` for local development against a running backend.

### Vitest Configuration (`vitest.config.js`)

```js
test: {
  include: ['src/__tests__/**/*.test.js'],
  environment: 'node',
  globals: true
}
```

Tests run in Node environment with global test functions (`describe`, `it`, `expect`). Property-based tests use `fast-check` (v4.x).

### Build Scripts (`package.json`)

| Script | Command | Description |
|--------|---------|-------------|
| `dev` | `vite` | Start Vite dev server with HMR |
| `build` | `vite build` | Production build to `dist/` |
| `build:dev` | `vite build --mode development --sourcemap` | Development build with source maps |
| `preview` | `vite preview` | Preview production build locally |
| `copy:dist` | `rm -rf ../www/rpi_admin/dist && cp -r dist ../www/rpi_admin/dist` | Copy build output to PHP serving directory |
| `build:deploy` | `npm run build && npm run copy:dist` | Build and deploy in one step |
| `build:deploy:dev` | `npm run build:dev && npm run copy:dist` | Dev build and deploy |
| `test` | `vitest` | Run tests in watch mode |

The `build:deploy` workflow is the standard deployment path: Vite builds the frontend into `rpidns-frontend/dist/`, then `copy:dist` moves it to `www/rpi_admin/dist/` where Nginx serves it.

## Entry Point (`src/main.js`)

Creates the Vue 3 app instance and configures:
- Bootstrap Vue Next plugin and all component/directive registrations (globally registered)
- vue3-apexcharts plugin for chart components
- Global color palette (`$gColors`) with 50 colors for chart series
- Global properties: `$rpiver`, `$assetsBy`, `$addressType` (from `window.RPIDNS_CONFIG`)
- Mounts to `#app`

## Related Documentation

- [Architecture Overview](./architecture.md) — System-level component interactions and data flow
- [Backend API Reference](./backend-api.md) — PHP API endpoints consumed by the frontend
- [Database Schema](./database.md) — SQLite tables and aggregation tiers queried by the frontend
- [BIND Configuration](./bind-configuration.md) — RPZ feeds and policy actions managed through the Admin UI
- [Configuration Files](./configuration-files.md) — `rpisettings.php` settings displayed in the Settings component
- [Docker Deployment](./docker-deployment.md) — Container setup for serving the frontend via OpenResty
- [README](../README.md) — Project overview and getting started
