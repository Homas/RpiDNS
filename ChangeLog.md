# RpiDNS change log
[CB] - Changed Behaviour
## 2026-08-06 v2.1.0
- New **Research** page (tab between RPZ log and Admin, deep-linkable as `#i2r/3/{unique|sql|tools}`) with three sections:
 - **Newly seen queries** - allowed FQDNs requested for the first time in the selected period and never before it, with filtering, sorting, pagination and CSV export;
 - **SQL Query** - administrator-supplied read-only `SELECT` statements against the RpiDNS database, with table list, row cap, truncation indication, pagination and CSV export;
 - **Tools** - one target and one **Analyze** action that runs every applicable tool and shows the results side by side: RDAP/WHOIS, dig, NS/MX, reverse DNS (PTR), GeoIP, ASN, ping, traceroute, TLS certificate, reputation/threat intel, and a website preview.
- **Analyze** action added to the Query log, RPZ log and Newly seen queries context menus, and to the dashboard TopX Allowed/Blocked Requests widgets. It opens the shared tools panel with the right-clicked target prefilled.
- RDAP/WHOIS, reputation, GeoIP and ASN results are rendered as readable summaries with a per-card switch to the raw JSON.
- RDAP/WHOIS looks up the registrable domain rather than the hostname, using the bundled Public Suffix List - `test.example.com` is queried as `example.com`, and `www.bbc.co.uk` as `bbc.co.uk` because `co.uk` behaves as a TLD. The substitution is stated in the result. The list is refreshed with `scripts/update_psl.php`.
- New setting **Research tools DNS resolver** (Admin > Settings, `$research_dns_server` in `rpisettings.php`). Every tool honors it, so blocked domains can be investigated as they appear from outside: the dig-based tools query it directly, ping/traceroute/TLS/preview have their target resolved through it first, and the API-based tools pin their endpoint host to it. Blank keeps the appliance resolver. A single run can override the value from the Tools panel and reset back to the default.
- Research endpoints are session-guarded, execute no shell (every input is a discrete argv slot), bound each tool to 30s, cap probe counts and output size, and record rejected inputs to an audit log. SQL is restricted to read-only statements on a separate read-only connection.
- Help page gained a Research section covering all three views and the tools.
- [CB] The web container now requests the `NET_RAW` capability (in addition to `NET_ADMIN`) so ping/traceroute work as the unprivileged `www-data` user. **Recreate the web container** after upgrading, otherwise traceroute fails with `Operation not permitted`.
- [CB] The web image adds `chromium` (plus its runtime libraries and fonts) for the website preview and `whois` for the port-43 fallback, which makes the image noticeably larger. Drop that Dockerfile layer if the preview is not needed.
- Existing installs keep their bind-mounted `rpisettings.php`; the new resolver setting defaults to blank until saved from the Settings page.
## 2026-06-17 v2.0.4
- Time-limited (TTL) allow/block indicators - an indicator can be given an expiry, shown as such in the allow/block lists and on the dashboard.
- Dashboard block/allow actions became "smart": blocking a domain that is on the allow list removes it from there instead of creating a conflicting rule, and vice versa.
- Alpine, Node, PHP and BIND base images and the frontend dependencies were bumped; the Vite 8 chunking build was fixed.
- Fixed a web container crash loop on startup caused by a missing `/usr/bin/php`.
- Bundled maintenance scripts are now synced into the mounted scripts directory on startup (`RPIDNS_SYNC_SCRIPTS=false` preserves customized host scripts).
- [CB] Database schema v3 adds the `expires_dt` column to `localzone`. The migration is applied automatically at container startup.
## 2026-04-26 v2.0.3
- Right-click context menu on the Query log and RPZ log: external research links plus per-row actions (block/allow, filter by value).
- Action menus added to the dashboard widgets, with restyled widget popovers.
- Fixed the client, options and request-type filters.
## 2026-04-19 v2.0.2
- Container health check improvements; BIND now starts in IPv4-only mode when the container has no IPv6.
- Documentation set added under `docs/` (architecture, backend API, database, frontend, scripts, BIND and Docker configuration).
- Dependency and vulnerability updates (axios, follow-redirects and others).
## 2026-01-18 v2.0.1
- Fixed adding indicators, and editing/deleting/importing allow and block list entries, including editing the same item several times in a row.
- Fixed the maximum backup upload size.
## 2026-01-02 v2.0.0
- [CB] RpiDNS is now deployed as Docker containers - a BIND container and a web container (OpenResty, PHP-FPM, rsyslog) orchestrated by `docker-compose`, with multi-architecture images (amd64/arm64) published to GHCR by GitHub Actions. The Raspberry Pi install script remains for bare-metal setups.
- [CB] Frontend migrated from Vue 2 to Vue 3 with Vite and bootstrap-vue-next (Bootstrap 5), replacing the previous build.
- [CB] User authentication added: login flow with server-side sessions, a default administrator created on first start, and password change with complexity rules and confirmation. Database schema v2 creates the authentication tables and imports existing htpasswd users.
- RPZ feed management from the UI - add, edit and retransfer feeds, with BIND configuration written, validated with `named-checkconf` and reloaded via `rndc`, rolling back automatically when validation fails. `nsupdate` reworked to operate against the containers.
- Help and Donate pages added; auto-refresh with a visible timer and a custom period picker across the dashboard and logs.
- Restyled block page, fixed certificate download and database import in the container.
## 2023-07-02 v1.2.0
- Debian 11 support and install script updates.
- Fixed drilldown from the TopX servers widget.
- Local JS/CSS libraries and Bootstrap refreshed.
## 2020-09-10 v1.1.0
- Database import
## 2020-09-07 v1.0.0
- List of provisioned RPZ feeds and possibility to retransfer a feed (permission changes are required check the rpidns_install.sh);
## 2020-04-30 v0.9.5
- Multiple features were addded including:
 - System widget
 - UX/UI drilldown
 - Statistics
## 2020-03-25 v0.9.0
- Initial release
