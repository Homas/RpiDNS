#  RpiDNS
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

## Overview

RpiDNS on the [ioc2rpz community](https://ioc2rpz.net) web-site provides simplified configuration interface and an installation script to provision DNS security at your home, office or home office.  

The RpiDNS project on github is a web-interface for RpiDNS. It can be used with a standalone ISC Bind instance. In that case some configuration changes may be required on ISC Bind side and/or RpiDNS.

## User interface

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_onprem.png"></p>

### Reporting periods
Reporting period is defined by a selector located in top right corner.
You can select:
- 30m - 30 minutes;
- 1h - 1 hour;
- 1d - 1 day;
- 1w - 1 week;
- 30d - 30 days;
- custom (not implemented).
Depending on the period different tables will be used. 30m and 1d based on raw log messages, other reports on a mix of raw data and aggregated data (per 5 minutes, 1 hour, 1 day). 
### Tools
Widgets on the dashboard and reports offers various tools depending on the context. From the dashboard you can drill-down to the reports by using "show queries" or "show hits" tools. A relevant report will be opened with a defined filter based on data type in a widget.  
For threat hunting, investigation of false positives RpiDNS offers multiple research tools. When you click on a tool a new browser window is opened and an indicator is passed to a 3rd party web-site.  
The research tools include:
- DuckDuckGo - search engine concerned about privacy;
- Google - generic search engine;
- VirusTotal - service which allows to validate if a domain, IP, URL, file, file hash is malicious or not.
- RiskIQ Community - service which provides access to passive DNS data and other digital footprint (PassiveTotal and Digital Footprint community editions).
- DomainTools Whois - domain and IP registration information.
- Robtex - provides public information about IP numbers, domain names, host names, Autonomous systems, routes etc. 
- Apility.io - threat intelligence SaaS for developers and product companies that want to know in realtime if their existing or potential users have been classified as 'abusers'​ by one or more of these lists.
- ThreatMiner - ThreatMiner is a threat intelligence portal that provides information on indicators of compromise (IOC) such as domains, IP address, malware samples etc.

### Dashboard
#### TopX Allowed Requests
The widget shows topX DNS requests which were not blocked.
#### TopX Allowed Clients
The widget shows topX clients which requests were not blocked.
#### TopX Allowed Request Types
The widget shows topX request types (e.g. A, AAAA, MX).
#### RpiDNS
The widget shows information about RpiDNS: CPU utilization, Memory utilization, Disk utilization, Uptime, GPU temperature
#### TopX Blocked Requests
The widget shows topX DNS requests which were blocked.
#### TopX Blocked Clients
The widget shows topX clients which requests were blocked.
#### TopX Feeds
The widget shows topX feeds which were used to block requests.
#### TopX Servers
The widget shows information about total number requests received by a DNS server (per IP) per reporting period.
### Query log
Query log report provide information about all DNS queries received by RpiDNSs in your network.  

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_qlog.png"></p>

You can switch between raw logs ("Logs" switch) and statistics ("Stats" switch). On the statistics table there is no time field and you can check/uncheck fields used for aggeregation. The report has the following fields:  
- Local time - time in your timezone. RpiDNS should be condfigured with UTC timezone. Requests are aggregated by 5 minutes, 1 hour, 1 day;
- Client - client name or IP if the client was not registered;
- Server - server IP;
- Request - requested fqdn;
- Type - request type;
- Class - request class;
- Options - request options;
- Count - number of requests aggregated in a single record;
- Action - action taken: Allow or Block.

### RPZ Hits
RPZ hits report 

<p align="center"><img src="https://ioc2rpz.net/img/RpiDNS_hits.png"></p>

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
