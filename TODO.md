# Migration to VUE3
Critical
- [x] (Not tested) Navigation between tabs - do refresh (including drill down)
- [x] (Not tested) Query/RPZ Stats enabling/disabling fields doesn't refresh the page

Non critical
- [v] block-ip.ioc2rpz.rpidns is not included into RPZ list on the community website
- [x] replace masters to primaries in the community website

- [ ] Root hints provision for bind
- [ ] RPZ 1d,1w shows more than 1h by one (4 vs 3)
- [ ] me-3 in between the tab pills and content
- [ ] To force the zone transfer requests to be sent over TLS, use tls keyword, e.g. primaries { 192.0.2.1 tls tls-configuration-name; }; https://bind9.readthedocs.io/en/v9.18.14/reference.html#namedconf-statement-primaries

## Bugs
 - [ ] Wait until DB will be unlocked
 - [ ] Get feeds # of rules

## Widgets
 - [ ] CHR (cache hit rate)
 - [ ] RpiDNS (servers) total statistics

## UX/UI
 - [ ] IoC input validation
 - [ ] IP rules auto generation
 - [ ] Blocking clients
 - [ ] Bypass list
 - [ ] New block page for blocked clients
 - [ ] List of RPZ feeds (from Bind's config)
   - [ ] reload zone ```sudo rndc retransfer phishtank.ioc2rpz```
 - [ ] Custom local RPZ rules

 - [ ] Add # records per RPZ feed
 26-Aug-2020 07:42:50.580 xfer-in: info: transfer of 'notracking.ioc2rpz/IN' from 94.130.30.123#53: Transfer completed: 585 messages, 443255 records, 9487
 194 bytes, 88.642 secs (107028 bytes/sec)

## Backend
 - [ ] Optimize pagination (replace LIMIT) and SQL
 - [ ] Advanced filtering
 - [-] Add RPZ Request Type/Class fields support (from Bind 9.16)

## Tools
 - [ ] Upgrade script (download RpiDNS from github and execute upgrade script)
 - [ ] Export/Import configuration

## Logs
 - [ ] Add server or file to RPZ logs

## Documentation
 - [ ] Update Readme.md
 - [ ] Add comments

## Security (review 10-Oct-2026)
Critical
 - [ ] C1. SQLite DB downloadable without auth: `GET /db/rpidns.sqlite` (nginx only denies `\.db$`; port 80 has no deny rules). Add `location ^~ /db/ { deny all; }` + deny `*.sqlite|-wal|-shm|.pos|.log` on 80 and 443, or move DB out of docroot. Same for OpenWrt (`/www/db` symlink)
 - [ ] C1a. Port 80 serves raw PHP source (`/rpi_admin/*.php`, `/rpisettings.php`). Route only `/blocked.php` to PHP, deny other `.php`
 - [ ] C1b. Session tokens stored in plaintext: store `hash('sha256', $token)` in `sessions.token`
 - [ ] C2. Docker socket mounted into web container + www-data in docker group (host root on web compromise). Replace with `rndc` over internal network (key + ACL, don't publish 953)

High
 - [ ] H1. No `is_admin` check in rpidata.php: non-admins can read `sessions` via `research_sql`, download DB, import, change settings/feeds. Add `requireAdmin()`; deny `users`/`sessions`/`login_attempts` in research_sql via `SQLite3::setAuthorizer()`
 - [ ] H2. named.conf injection via rpz_feed (`primaryServer`, `cnameTarget`, `tsigKeyName`, `tsigAlgorithm`, `tsigKeySecret` unvalidated in BindConfigManager). Validate IP / DNS name / algorithm allowlist / base64 secret in `validateFeedConfig` and `updateFeed`; use `preg_replace_callback` for replacements
 - [ ] H3. SQL injection from DNS query names in parse_bind_logs.php (`'` in qname breaks batch insert, logs lost). Use prepared statements in a transaction; advance `.pos` only after commit; escape `$vendors[$mac]`
 - [ ] H4. Shipped bind/named.conf is an open resolver (`allow-query any`, `recursion yes`, port 53 on 0.0.0.0). Generate `trusted` ACL from `RPIDNS_DNS_IPNET`; `allow-recursion`/`allow-query-cache { trusted; }`, `allow-transfer { none; }`
 - [ ] H5. CA/intermediate private keys `chmod 644`, ssl_cache `chmod 777` (web/entrypoint.sh:118-119). Use 600/700; keep root CA key offline
 - [ ] H6. Root cron runs `/opt/rpidns/scripts/*.php` owned by www-data (web/Dockerfile:216). Make scripts root:root 755

Medium
 - [ ] M1. `/tmp/rpidns/rpidns_import_ready` trusted by root cron (unlink/create any file, SQL injection in `ATTACH '$path'`). Private dir, strict path regex, re-sanitize objects, `ATTACH ?`; open import DB read-only with `trusted_schema=OFF`
 - [ ] M2. Syslog 10514/tcp published on 0.0.0.0 without auth/allowlist (forged query logs, disk fill). Don't publish by default, add `AllowedSender`/TLS
 - [ ] M3. SSRF via research tools: user-chosen DNS server + website preview (Chromium `--no-sandbox`) can reach internal hosts; ping/tls_cert accept private IPs. Block private/loopback/link-local ranges, isolate Chromium, admin-only
 - [ ] M4. Settings save writes `$assets_autocreate=;` when unchecked (PHP parse error, UI down) - rpidata.php:1383. Output `'true'`/`'false'` strings; consider JSON settings
 - [ ] M5. SNI cert cache grows unbounded; cleanup cron uses wrong path (`ssl/cache` vs `ssl_cache`)
 - [ ] M6. Web container has unused `NET_ADMIN` and RW mount of `/etc/bind`. Drop NET_ADMIN, `cap_drop: ALL` + NET_RAW, `no-new-privileges` on bind
 - [ ] M7. CSV formula injection (useCsvExport.js `encodeField`): prefix cells starting with `= + - @ \t \r` with `'`
 - [ ] M8. Installers: `curl | bash` NodeSource fallback, unverified `named.root`/`manuf`, OpenWrt pulls JS `@latest` from CDN. Pin versions + checksums

Low
 - [ ] L1. Login: per-IP only rate limit, non-atomic check, fails open on DB error; add per-username limit and `BEGIN IMMEDIATE`
 - [ ] L2. Username enumeration by timing: run `password_verify` against a dummy hash for unknown users
 - [ ] L3. Legacy hash comparisons not constant-time (`hash_equals`); force reset for DES/SHA1 hashes
 - [ ] L4. rpidata.php responses are `text/html` and echo `req` unescaped (line ~2097). Set `Content-Type: application/json`, `nosniff`, build all responses with `json_encode`
 - [ ] L5. Import response leaks `$_FILES`/temp paths (`file_data`, `debug`); no size cap on gzip/zip decompression
 - [ ] L6. Generated admin password written to logs (auth.php, db_migrate.php, init_db.php); `default_credentials.txt` never deleted after password change
 - [ ] L7. research_sql recursive CTE can tie up PHP workers ~30s each
 - [ ] L8. No security headers in nginx (X-Frame-Options/CSP frame-ancestors, nosniff, Referrer-Policy, `server_tokens off`); `client_max_body_size 512M` global
 - [ ] L9. Research links: `encodeURIComponent(domain)`, DomainTools link to `https://`, add `rel="noopener noreferrer"` to `target="_blank"`
 - [ ] L10. Pin Docker images by digest, `npm ci` instead of `npm install`, keep package-lock; pin GitHub Actions by SHA
 - [ ] L11. www-data owns whole docroot (entrypoint.sh:185); only `db/` and `rpisettings.php` need to be writable
 - [ ] L12. Add `sync_git_changes.sh`, `upload_local_msk.sh`, `containers/` to .gitignore; rotate credential in `local_rpz.txt` if still valid
 - [ ] L13. `npm audit`: brace-expansion (dev only, via @vue/test-utils)
 - [ ] L14. Add Origin/`Sec-Fetch-Site` check on non-GET API requests (CSRF defense in depth beyond SameSite=Strict)
 - [ ] L15. Log injection in NsUpdate `logSkip` (escape ioc value)
