## Bugs
 - [ ] Drill-down from TopX Servers - wrong filter.
 - [x] Disabled (log only) feeds are not parsed.
 - [ ] Wait until DB will be unlocked

## Widgets
 - [ ] CHR (cache hit rate)
 - [ ] RpiDNS (servers) total statistics

## UX/UI
 - [ ] IoC input validation
 - [ ] IP rules auto generation
 - [ ] Blocking clients
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
 - [ ] Add RPZ Request Type/Class fields support (from Bind 9.16)

## Tools
 - [ ] Upgrade script (download RpiDNS from github and execute upgrade script)
 - [ ] Import DB tool

## Logs
 - [ ] Add server or file to RPZ logs

## Documentation
 - [ ] Update readme.md
 - [ ] Add comments
