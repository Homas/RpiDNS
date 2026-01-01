# Migration to VUE3
- [ ] Root hints provision for bind
- [ ] RPZ 1d,1w shows more than 1h by one (4 vs 3)
- [ ] me-3 in between the tab pills and content
- [ ] "Given" not available for 3rd party feeds

- [x] Import DB for ZIP
TypeError: Cannot read properties of undefined (reading 'length')
- [x] unzipped, tried to import but Import DB modal doesn't close and generate the same error as above
- [x] Remove old password
- [x] The reset password generate a new password for a user
- [x] Remove blocked.php from the install script on the community
- [x] Can't download CA cert with docker
PHP message: PHP Warning:  fopen(/opt/rpidns/www/ioc2rpzCA.crt): Failed to open stream: No such file or directory in /opt/rpidns/www/rpi_admin/rpidata.php on line 402; PHP message: PHP Fatal error:  Uncaught TypeError: fpassthru(): Argument #1 ($stream) must be of type resource, false given in /opt/rpidns/www/rpi_admin/rpidata.php:430
- [x] list of RPZ zones not available
- [x] RPZ feed retransfer doesn't work
{feed: "allow.ioc2rpz.rpidns"}
PHP message: PHP Warning:  Undefined variable $feeds in /opt/rpidns/www/rpi_admin/rpidata.php on line 542; PHP message: PHP Fatal error:  Uncaught TypeError: array_key_exists(): Argument #2 ($array) must be of type array, null given in /opt/rpidns/www/rpi_admin/rpidata.php:542

## Bugs
 - [x] Drill-down from TopX Servers - wrong filter.
 - [x] Disabled (log only) feeds are not parsed.
 - [ ] Wait until DB will be unlocked

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
 - [ ] Add RPZ Request Type/Class fields support (from Bind 9.16)

## Tools
 - [ ] Upgrade script (download RpiDNS from github and execute upgrade script)
 - [ ] Export/Import configuration

## Logs
 - [ ] Add server or file to RPZ logs

## Documentation
 - [ ] Update Readme.md
 - [ ] Add comments
