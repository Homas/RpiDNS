#  RpiDNS
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

## Overview

RpiDNS on the [ioc2rpz community](https://ioc2rpz.net) web-site provides simplified configuration interface and an installation script to provision DNS security at your home, office or home office.  

The RpiDNS project on github is a web-interface for RpiDNS. It can be used with a standalone ISC Bind instance. In that case some configuration changes may be required on ISC Bind side and/or RpiDNS.

## User interface
### Common elements
#### Reporting periods
#### Logs
#### Stats
### Dashboard
### Query logs
### RPZ Hits
### Administration
#### Assets
#### Blacklist
#### Whitelist
#### Settings
#### Downloads

## Settings

## Scripts
### rpidns_install.sh
It is an installation script. It pulls required libraries, tools, packages (except a web-server), copy files to directories, init the database and setup crontab tasks.
It was written for Raspbian, other Linux distribution currently are not supported but you can easily update it.

### init_db.php
The DB initialization script.

### clean_db.php
The script is executed by crontab and removes old log messages from the DB. The DB is cleaned up by native sqlite3 "VACUUM" command.

### parce_bind_logs.php
The script parces bind's query and rpz log files, saved logs in the DB and performs data aggregation. The script is executed by cron every minute.

## ISC Bind configuration
To work with RpiDNS ISC Bind should:
- export DNS queries and RPZ hits into a log-file
- local RPZs: wl.ioc2rpz.local, wl-ip.ioc2rpz.local, bl.ioc2rpz.local, bl-ip.ioc2rpz.local

## Database

## Built with
- [VUE.js](https://vuejs.org/)
- [bootstrap-vue](https://bootstrap-vue.js.org/)
- [Axios](https://github.com/axios/axios)

# Do you want to support to the project?
You can support the project via [GitHub Sponsor](https://github.com/sponsors/Homas) (recurring payments) or make one time donation via [PayPal](https://paypal.me/ioc2rpz).

# Contact us
You can contact us by email: feedback(at)ioc2rpz[.]net or in [Telegram](https://t.me/ioc2rpz).

# License
Copyright 2020 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.
You may obtain a copy of the License at  
  
    http://www.apache.org/licenses/LICENSE-2.0  
  
Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
