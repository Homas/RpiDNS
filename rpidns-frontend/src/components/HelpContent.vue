<template>
  <div class="help-wrapper">
    <!-- Left Sidebar Navigation -->
    <nav 
      class="help-sidebar" 
      :class="{ collapsed: sidebarCollapsed }"
      @mouseenter="sidebarCollapsed = false"
      @mouseleave="collapseSidebar"
    >
      <div class="sidebar-header">
        <i class="fas fa-list"></i>
        <span class="sidebar-title">Contents</span>
      </div>
      <ul class="sidebar-nav">
        <li>
          <a href="#getting-started" @click.prevent="navigateTo('getting-started')">
            <i class="fas fa-play-circle"></i>
            <span>1. Getting Started</span>
          </a>
        </li>
        <li>
          <a href="#dashboard" @click.prevent="navigateTo('dashboard')">
            <i class="fas fa-tachometer-alt"></i>
            <span>2. Dashboard</span>
          </a>
        </li>
        <li>
          <a href="#query-log" @click.prevent="navigateTo('query-log')">
            <i class="fas fa-shoe-prints"></i>
            <span>3. Query Log</span>
          </a>
        </li>
        <li>
          <a href="#rpz-hits" @click.prevent="navigateTo('rpz-hits')">
            <i class="fas fa-shield-alt"></i>
            <span>4. RPZ Hits</span>
          </a>
        </li>
        <li>
          <a href="#admin-panel" @click.prevent="navigateTo('admin-panel')">
            <i class="fas fa-screwdriver"></i>
            <span>5. Admin Panel</span>
          </a>
        </li>
        <li>
          <a href="#common-actions" @click.prevent="navigateTo('common-actions')">
            <i class="fas fa-mouse-pointer"></i>
            <span>6. Common Actions</span>
          </a>
        </li>
      </ul>
    </nav>

    <!-- Main Content Area -->
    <div class="help-content" ref="contentArea">
      <!-- Back to Top Button -->
      <button 
        class="back-to-top" 
        :class="{ visible: showBackToTop }"
        @click="scrollToTop"
        title="Back to Top"
      >
        <i class="fas fa-arrow-up"></i>
      </button>

      <BCard>
        <template #header>
          <span class="bold"><i class="fas fa-hands-helping"></i>&nbsp;&nbsp;Help & Documentation</span>
        </template>

        <!-- Content Sections -->
        <div class="help-sections">

          <!-- 1. Getting Started -->
          <section id="getting-started" class="help-section mb-5">
            <h2><i class="fas fa-play-circle"></i> 1. Getting Started</h2>
            <hr>
            
            <h3 id="overview">1.1 Overview</h3>
            <p>
              <strong>RpiDNS</strong> is a comprehensive DNS-based ad-blocking and security monitoring application powered by 
              <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a>. The application serves as a central management 
              interface for monitoring and controlling DNS traffic across your entire network. By leveraging Response Policy 
              Zones (RPZ), RpiDNS can effectively block malicious domains, advertisements, trackers, and other unwanted 
              content at the DNS level before connections are even established.
            </p>
            <p>
              The system integrates with ISC BIND DNS server and provides real-time visibility into all DNS queries 
              originating from devices on your network. This allows network administrators and security-conscious users 
              to identify potentially harmful traffic, track device behavior, and maintain control over what domains 
              can be accessed from their network.
            </p>
            <p><strong>Key capabilities include:</strong></p>
            <ul>
              <li><strong>DNS Query Monitoring</strong> - Comprehensive tracking of all DNS queries from every device on your network. 
                Each query is logged with timestamp, source device, requested domain, query type, and response status. This provides 
                complete visibility into network activity and helps identify unusual patterns or potential security threats.</li>
              <li><strong>Ad & Malware Blocking</strong> - Automatic blocking of unwanted domains using RPZ (Response Policy Zone) feeds 
                from ioc2rpz.net. These feeds are continuously updated with known malicious domains, advertising networks, tracking 
                services, and other undesirable content. When a device attempts to resolve a blocked domain, the DNS server returns 
                a blocking response (NXDOMAIN) preventing the connection.</li>
              <li><strong>Custom Block/Allow Lists</strong> - Full control over domain blocking with your own custom rules. You can 
                add specific domains to your Block List to prevent access, or add domains to your Allow List to override RPZ feed 
                blocks. This flexibility allows you to fine-tune blocking behavior for your specific needs.</li>
              <li><strong>Network Asset Tracking</strong> - Automatic discovery and tracking of devices on your network. Assets can 
                be identified by MAC address or IP address, and you can assign friendly names to make it easier to identify which 
                device is generating specific traffic. The system also automatically detects hardware vendors from MAC addresses.</li>
              <li><strong>Security Analytics</strong> - Detailed statistics and visualizations showing blocked threats, query patterns, 
                top requested domains, most active clients, and more. These analytics help you understand your network's DNS traffic 
                patterns and identify potential security issues or policy violations.</li>
            </ul>

            <h3 id="logging-in">1.2 Logging In</h3>
            <p>
              To access RpiDNS, open your web browser and navigate to the URL where RpiDNS is hosted on your network. 
              You will be presented with a secure login screen that requires authentication before accessing any features.
            </p>
            <p>Enter your credentials in the login form:</p>
            <ul>
              <li><strong>Username</strong> - Your assigned username. Usernames are case-sensitive, so ensure you enter it exactly 
                as it was provided to you by your administrator. The default administrator account is typically created during 
                initial setup.</li>
              <li><strong>Password</strong> - Your account password. Passwords are also case-sensitive. For security reasons, 
                passwords should be strong and unique. You can change your password after logging in through the user menu.</li>
            </ul>
            <p>
              After entering your credentials, click the Login button or press Enter to authenticate. If your credentials are 
              correct, you will be redirected to the Dashboard. If authentication fails, an error message will be displayed 
              indicating invalid credentials.
            </p>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Forgot your password?</strong> If you've forgotten your password, 
              you will need to contact your system administrator. Administrators can reset user passwords through the User Management 
              section in the Admin panel. There is no self-service password reset feature for security reasons.
            </p>
            <p>
              <i class="fas fa-shield-alt text-success"></i> <strong>Security Note:</strong> Your session will remain active until 
              you explicitly log out or close your browser. For shared computers, always log out when finished to prevent 
              unauthorized access to your RpiDNS account.
            </p>

            <h3 id="navigation">1.3 Navigation</h3>
            <p>
              RpiDNS features an intuitive tab-based navigation system designed for efficient access to all features. On desktop 
              and larger screens, the navigation menu appears as a vertical sidebar on the left side of the screen. On mobile 
              devices and smaller screens, the navigation adapts to a horizontal layout at the top of the screen for better 
              usability on touch devices.
            </p>
            <p>The main navigation tabs provide access to the following sections:</p>
            <ul>
              <li><i class="fas fa-tachometer-alt"></i> <strong>Dashboard</strong> - Your primary landing page after login. The Dashboard 
                provides a comprehensive real-time overview of DNS activity on your network, including statistical widgets showing 
                top domains, clients, and feeds, as well as a queries-per-minute chart for visualizing traffic patterns over time.</li>
              <li><i class="fas fa-shoe-prints"></i> <strong>Query Log</strong> - A detailed, searchable log of all DNS queries processed 
                by your DNS server. This section allows you to investigate specific queries, filter by various criteria, and analyze 
                DNS traffic patterns. You can view individual query records or aggregated statistics.</li>
              <li><i class="fas fa-shield-alt"></i> <strong>RPZ Hits</strong> - A dedicated view showing all DNS queries that were blocked 
                by Response Policy Zone rules. This includes blocks from RPZ feeds as well as your custom Block List. Use this section 
                to monitor blocked threats and identify domains that may need to be added to your Allow List.</li>
              <li><i class="fas fa-screwdriver"></i> <strong>Admin</strong> - The administration panel containing all configuration and 
                management tools. This section is organized into sub-tabs for Assets, RPZ Feeds, Block List, Allow List, Settings, 
                Tools, and User Management (admin only). Access to certain features may be restricted based on your user privileges.</li>
              <li><i class="fas fa-hands-helping"></i> <strong>Help</strong> - This documentation section providing comprehensive guidance 
                on using all features of RpiDNS. The Help section includes detailed explanations, tips, and best practices for 
                effectively managing your DNS security.</li>
            </ul>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Space-Saving Tip:</strong> The navigation menu can be collapsed to 
              show only icons, giving you more screen space for content. Click the <i class="fas fa-angle-double-left"></i> icon at 
              the bottom of the menu to collapse it, or click <i class="fas fa-angle-double-right"></i> to expand it again. Your 
              preference is automatically saved and will persist across browser sessions.
            </p>

            <h3 id="user-menu">1.4 User Menu</h3>
            <p>
              The user menu is located in the top-right corner of the screen and displays your current username. Clicking on your 
              username reveals a dropdown menu with account-related options. This menu is always accessible regardless of which 
              section of the application you are currently viewing.
            </p>
            <p>The user menu provides the following options:</p>
            <ul>
              <li><i class="fas fa-key"></i> <strong>Change Password</strong> - Opens a dialog allowing you to update your account 
                password. You will need to enter your current password for verification, then enter and confirm your new password. 
                Strong passwords should include a mix of uppercase and lowercase letters, numbers, and special characters. After 
                changing your password, you will remain logged in with your new credentials.</li>
              <li><i class="fas fa-sign-out-alt"></i> <strong>Logout</strong> - Securely signs you out of the RpiDNS application and 
                terminates your current session. After logging out, you will be redirected to the login screen. Always use this 
                option when finished working with RpiDNS, especially on shared or public computers, to ensure your account remains 
                secure.</li>
            </ul>
            <p>
              <i class="fas fa-user-shield text-info"></i> <strong>Account Security Best Practices:</strong> Regularly change your 
              password, especially if you suspect it may have been compromised. Never share your login credentials with others. 
              If you need to provide access to another person, ask your administrator to create a separate account for them.
            </p>
          </section>

          <!-- 2. Dashboard -->
          <section id="dashboard" class="help-section mb-5">
            <h2><i class="fas fa-tachometer-alt"></i> 2. Dashboard</h2>
            <hr>

            <h3 id="dashboard-overview">2.1 Overview</h3>
            <p>
              The Dashboard is your central command center for monitoring DNS activity across your network. It provides 
              a comprehensive real-time overview of all DNS queries, blocked requests, and system performance metrics. 
              The Dashboard is designed to give you immediate visibility into what's happening on your network at a glance, 
              while also providing quick access to detailed information and common actions.
            </p>
            <p>
              The Dashboard layout consists of two main areas: a set of statistical widget cards arranged in two rows, 
              and a queries-per-minute chart at the bottom. The top row focuses on allowed traffic statistics, while 
              the bottom row displays information about blocked traffic and system performance. All data displayed on 
              the Dashboard is dynamically updated based on your selected time period.
            </p>
            <p>
              Each widget on the Dashboard is interactive, allowing you to hover over items to reveal additional actions 
              such as viewing detailed queries, blocking or allowing domains, and accessing external research tools. This 
              makes the Dashboard not just a monitoring tool, but also a quick action center for common DNS management tasks.
            </p>

            <h3 id="time-period">2.2 Time Period Selection</h3>
            <p>
              The time period selector in the Dashboard header allows you to control the time range for all displayed data. 
              Selecting a different time period will refresh all widgets and the chart to show data from that specific 
              timeframe. This is useful for analyzing recent activity, investigating historical patterns, or comparing 
              traffic across different time periods.
            </p>
            <p>Available preset time periods include:</p>
            <ul>
              <li><strong>30m</strong> - Shows data from the last 30 minutes. Ideal for monitoring current, real-time activity 
                and identifying immediate issues or unusual spikes in traffic.</li>
              <li><strong>1h</strong> - Shows data from the last hour. Useful for getting a slightly broader view of recent 
                activity while still focusing on current trends.</li>
              <li><strong>1d</strong> - Shows data from the last 24 hours. Provides a full day's worth of data, helpful for 
                understanding daily patterns and identifying recurring issues.</li>
              <li><strong>1w</strong> - Shows data from the last 7 days. Allows you to analyze weekly trends and compare 
                activity across different days of the week.</li>
              <li><strong>30d</strong> - Shows data from the last 30 days. Provides a monthly overview, useful for long-term 
                trend analysis and capacity planning.</li>
              <li><strong>custom</strong> - Opens a date/time picker allowing you to select a specific start and end date/time. 
                This is useful for investigating specific incidents or analyzing activity during particular events.</li>
            </ul>
            <p>
              <strong>Custom Period Selection:</strong> When you click the "custom" button, a date picker dialog appears 
              where you can select your desired start and end dates and times. The picker supports both date selection via 
              calendar and direct time input. After selecting your range, click Apply to update the Dashboard with data 
              from your specified period.
            </p>
            <p>
              <strong>Auto-Refresh Feature:</strong> The Dashboard includes an automatic refresh capability that keeps your 
              data current without manual intervention. Toggle the "Auto" switch to enable automatic refresh, which updates 
              all Dashboard data every 60 seconds. When auto-refresh is enabled, you'll see a visual indicator showing the 
              countdown to the next refresh. You can also manually refresh at any time by clicking the <i class="fas fa-sync"></i> 
              refresh button, regardless of whether auto-refresh is enabled.
            </p>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Performance Note:</strong> Selecting longer time periods 
              (especially 30 days) may take slightly longer to load as more data needs to be processed. For real-time 
              monitoring, shorter periods like 30 minutes or 1 hour provide the best performance.
            </p>

            <h3 id="dashboard-widgets">2.3 Dashboard Widgets</h3>
            <p>
              The Dashboard displays eight statistical widgets organized into two rows. Each widget shows a ranked list of 
              items (domains, clients, etc.) sorted by query count, with the most active items at the top. The number of 
              items shown in each widget is configurable in the Admin Settings (default is typically 10 items).
            </p>
            
            <h4>Allowed Traffic Widgets (Top Row)</h4>
            <p>The top row of widgets focuses on DNS queries that were successfully resolved (not blocked):</p>
            <ul>
              <li><strong>TopX Allowed Requests</strong> - Displays the most frequently requested domains that were allowed 
                through without blocking. This widget helps you understand what services and websites are most commonly 
                accessed on your network. High-frequency domains typically include search engines, content delivery networks, 
                social media platforms, and commonly used services. Unusual domains appearing here may warrant investigation.</li>
              <li><strong>TopX Allowed Clients</strong> - Shows the devices (clients) generating the most allowed DNS queries. 
                Clients are identified by their assigned name if available, or by IP/MAC address. This helps identify the 
                most active devices on your network. Unusually high query counts from a single device might indicate 
                automated processes, malware activity, or simply heavy usage.</li>
              <li><strong>TopX Allowed Request Types</strong> - Lists the DNS record types most frequently requested. Common 
                types include A (IPv4 address), AAAA (IPv6 address), CNAME (canonical name), MX (mail exchange), TXT (text 
                records), and others. This widget helps you understand the nature of DNS traffic on your network. An unusual 
                distribution of record types might indicate specific applications or potential DNS tunneling attempts.</li>
              <li><strong>RpiDNS Stats</strong> - Displays real-time server performance metrics including CPU load percentage, 
                memory usage, system uptime, and other relevant statistics. This widget helps you monitor the health of your 
                RpiDNS server and identify potential performance issues before they impact DNS resolution.</li>
            </ul>

            <h4>Blocked Traffic Widgets (Bottom Row)</h4>
            <p>The bottom row focuses on DNS queries that were blocked by RPZ rules:</p>
            <ul>
              <li><strong>TopX Blocked Requests</strong> - Shows the domains most frequently blocked by RPZ rules. These are 
                domains that devices on your network attempted to access but were prevented from reaching. High-frequency 
                blocked domains often include advertising networks, tracking services, and known malicious domains. If you 
                see a legitimate domain here that shouldn't be blocked, you can add it to your Allow List.</li>
              <li><strong>TopX Blocked Clients</strong> - Identifies the devices triggering the most RPZ blocks. A device 
                with an unusually high block count might be infected with malware, running software with aggressive advertising, 
                or simply visiting websites with many blocked third-party resources. This widget helps you identify devices 
                that may need attention or investigation.</li>
              <li><strong>TopX Feeds</strong> - Shows which RPZ feeds are responsible for the most blocks. This helps you 
                understand which threat categories are most relevant to your network. Feeds might include categories like 
                advertising, malware, phishing, tracking, and more. If a particular feed is causing too many blocks, you 
                might consider adjusting your feed configuration.</li>
              <li><strong>TopX Servers</strong> - Displays the DNS servers handling the most queries. In environments with 
                multiple DNS servers, this helps you understand load distribution and identify any servers that might be 
                overloaded or underutilized.</li>
            </ul>

            <h3 id="dashboard-actions">2.4 Interactive Actions</h3>
            <p>
              Each item in the Dashboard widgets is interactive. When you hover over a domain, client, or other item, 
              a set of action buttons appears allowing you to perform common tasks without navigating away from the Dashboard. 
              These quick actions significantly speed up your workflow when managing DNS security.
            </p>
            <p>Available actions vary by widget type but typically include:</p>
            <ul>
              <li><strong>Show queries</strong> - Navigates to the Query Log tab with a filter automatically applied to show 
                only queries related to the selected item. For example, clicking this on a domain shows all queries for that 
                domain; clicking on a client shows all queries from that device. The current time period selection is preserved.</li>
              <li><strong>Show hits</strong> - Navigates to the RPZ Hits tab filtered to show only blocked queries related to 
                the selected item. This is useful for investigating why a particular domain is being blocked or which blocked 
                domains a specific client is trying to access.</li>
              <li><strong>Block</strong> - Adds the selected domain to your custom Block List. A confirmation dialog appears 
                allowing you to configure options such as whether to also block subdomains and add an optional comment. Once 
                added, the domain will be blocked for all devices on your network.</li>
              <li><strong>Allow</strong> - Adds the selected domain to your Allow List, which overrides any RPZ feed blocks. 
                This is useful when a legitimate domain is incorrectly blocked by an RPZ feed. The Allow List takes precedence 
                over all other blocking rules.</li>
              <li><strong>Research</strong> - Opens a submenu with links to external security research tools where you can 
                investigate the domain further. These tools provide additional context about domain reputation, malware 
                associations, and other security-relevant information.</li>
            </ul>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Workflow Tip:</strong> The Dashboard actions are designed 
              for quick triage. When you notice a suspicious domain, you can immediately research it, check its query history, 
              and block it if necessary, all without leaving the Dashboard context.
            </p>

            <h3 id="qpm-chart">2.5 Queries per Minute Chart</h3>
            <p>
              The Queries per Minute (QPM) chart at the bottom of the Dashboard provides a visual representation of DNS 
              query volume over time. This time-series chart helps you identify traffic patterns, unusual spikes, and 
              the overall health of your DNS infrastructure.
            </p>
            <p>The chart displays two data series as stacked areas:</p>
            <ul>
              <li><strong>Blue area</strong> - Represents the total number of allowed (successful) DNS queries per minute. 
                This shows the baseline DNS activity on your network. Normal patterns typically show higher activity during 
                business hours or peak usage times, with lower activity during off-hours.</li>
              <li><strong>Red area</strong> - Represents the number of blocked queries (RPZ hits) per minute. This shows 
                how many DNS requests were blocked by RPZ rules. A sudden spike in blocked queries might indicate malware 
                activity, a new threat, or a device attempting to access blocked resources repeatedly.</li>
            </ul>
            <p>
              <strong>Interactive Features:</strong> Hover your mouse over any point on the chart to see a tooltip displaying 
              the exact timestamp and query counts for that moment. This allows you to pinpoint specific times when unusual 
              activity occurred. The chart automatically scales to fit the data range and adjusts its time axis labels based 
              on the selected time period.
            </p>
            <p>
              <i class="fas fa-chart-line text-info"></i> <strong>Analysis Tips:</strong> Look for patterns in the chart such 
              as regular daily cycles, unexpected spikes, or sustained high block rates. Sudden increases in blocked queries 
              might indicate a security incident. Gradual increases in overall traffic might indicate network growth or the 
              addition of new devices.
            </p>
          </section>

          <!-- 3. Query Log -->
          <section id="query-log" class="help-section mb-5">
            <h2><i class="fas fa-shoe-prints"></i> 3. Query Log</h2>
            <hr>

            <h3 id="querylog-overview">3.1 Overview</h3>
            <p>
              The Query Log provides a comprehensive, searchable record of all DNS queries processed by your DNS server. 
              This powerful tool allows you to investigate specific DNS activity, analyze traffic patterns, troubleshoot 
              connectivity issues, and identify potential security threats. Every DNS query from every device on your 
              network is captured and stored, giving you complete visibility into DNS-level network activity.
            </p>
            <p>
              The Query Log is essential for several use cases: investigating why a particular website or service isn't 
              working (by checking if DNS queries are being blocked), identifying devices that may be compromised (by 
              looking for unusual query patterns), understanding application behavior (by seeing what domains applications 
              are contacting), and maintaining compliance records of network activity.
            </p>
            <p>
              Data in the Query Log is retained according to your configured retention policy, which can be adjusted in 
              the Admin Settings. Older data is automatically purged to manage storage space while maintaining sufficient 
              historical records for analysis and troubleshooting.
            </p>

            <h3 id="logs-stats-toggle">3.2 Logs vs Stats View</h3>
            <p>
              The Query Log offers two distinct viewing modes that serve different analytical purposes. You can toggle 
              between these modes using the Logs/Stats switch in the header area.
            </p>
            <ul>
              <li><strong>Logs View</strong> - Displays individual query records as separate rows, each with its own 
                timestamp. This view is ideal for investigating specific incidents, tracing the sequence of DNS queries 
                from a particular device, or examining the exact timing of events. Each row represents a single DNS query 
                with all its associated details. Use this view when you need to see the chronological sequence of events 
                or investigate specific queries.</li>
              <li><strong>Stats View</strong> - Aggregates queries and displays summarized statistics grouped by selected 
                fields. Instead of showing individual queries, this view shows unique combinations of the selected fields 
                along with a count of how many queries matched that combination. This is useful for identifying patterns, 
                finding the most common queries, or getting an overview of activity without the noise of individual records.</li>
            </ul>
            <p>
              <strong>Configuring Stats View:</strong> When in Stats mode, you'll notice checkboxes appearing in the table 
              header next to each column name. Select the checkboxes for the fields you want to group by. For example, 
              selecting only "Request" will show you a list of unique domains with their query counts. Selecting both 
              "Client" and "Request" will show you unique client-domain combinations. The more fields you select, the 
              more granular your grouping becomes.
            </p>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Analysis Tip:</strong> Start with Stats view to identify 
              interesting patterns or anomalies, then switch to Logs view and apply filters to investigate specific items 
              in detail.
            </p>

            <h3 id="querylog-columns">3.3 Table Columns</h3>
            <p>
              The Query Log table displays comprehensive information about each DNS query. Understanding these columns 
              helps you effectively analyze and investigate DNS traffic.
            </p>
            <table class="table table-sm table-bordered">
              <thead><tr><th>Column</th><th>Description</th></tr></thead>
              <tbody>
                <tr>
                  <td><strong>Local Time</strong></td>
                  <td>The timestamp when the DNS query was received by the server, displayed in your local timezone. 
                    This column only appears in Logs view since Stats view aggregates multiple queries. The timestamp 
                    precision allows you to correlate DNS queries with other events or logs from your network.</td>
                </tr>
                <tr>
                  <td><strong>Client</strong></td>
                  <td>Identifies the device that made the DNS query. If the device has been assigned a friendly name 
                    in the Assets section, that name is displayed. Otherwise, the IP address or MAC address is shown 
                    depending on your tracking configuration. Hover over the client name to see a tooltip with additional 
                    details including MAC address, IP address, and hardware vendor information.</td>
                </tr>
                <tr>
                  <td><strong>Server</strong></td>
                  <td>The DNS server that processed this query. In environments with multiple DNS servers, this helps 
                    identify which server handled the request. This is useful for load balancing analysis and 
                    troubleshooting server-specific issues.</td>
                </tr>
                <tr>
                  <td><strong>Request</strong></td>
                  <td>The fully qualified domain name (FQDN) that was queried. This is the domain the client was trying 
                    to resolve. Long domain names may be truncated in the display; hover over them to see the full name. 
                    This is typically the most important column for understanding what resources devices are accessing.</td>
                </tr>
                <tr>
                  <td><strong>Type</strong></td>
                  <td>The DNS record type being requested. Common types include: A (IPv4 address lookup), AAAA (IPv6 
                    address lookup), CNAME (canonical name/alias), MX (mail server), TXT (text records, often used for 
                    verification), PTR (reverse DNS lookup), SRV (service location), and NS (nameserver). The record 
                    type provides context about why the query was made.</td>
                </tr>
                <tr>
                  <td><strong>Class</strong></td>
                  <td>The DNS query class. In virtually all cases, this will be "IN" for Internet. Other classes exist 
                    for historical reasons but are rarely used in modern networks. This column is included for 
                    completeness and protocol compliance.</td>
                </tr>
                <tr>
                  <td><strong>Options</strong></td>
                  <td>Additional DNS query options and flags that were set in the query. This may include EDNS options, 
                    DNSSEC flags, or other protocol-specific information. Most queries will have minimal options, but 
                    this field can be useful for advanced troubleshooting.</td>
                </tr>
                <tr>
                  <td><strong>Count</strong></td>
                  <td>In Stats view, this shows the number of queries that matched the grouped criteria. In Logs view, 
                    this typically shows 1 for each individual query record. High counts in Stats view indicate 
                    frequently accessed domains or active clients.</td>
                </tr>
                <tr>
                  <td><strong>Action</strong></td>
                  <td>Indicates whether the query was allowed (successfully resolved) or blocked (by RPZ rules). 
                    Allowed queries are typically shown with a green indicator, while blocked queries show red. 
                    This helps you quickly identify the outcome of each query.</td>
                </tr>
              </tbody>
            </table>

            <h3 id="querylog-filtering">3.4 Filtering</h3>
            <p>
              The Query Log includes powerful filtering capabilities that allow you to quickly find specific queries 
              among potentially millions of records. The filter input field accepts both simple text searches and 
              structured field-specific queries.
            </p>
            <p><strong>Simple Text Search:</strong> Type any text in the filter field to search across all columns. 
              The search is case-insensitive and will match partial strings. For example, typing "google" will find 
              all queries containing "google" in any field.</p>
            <p><strong>Field-Specific Filters:</strong> For more precise filtering, use the field=value syntax:</p>
            <ul>
              <li><code>fqdn=example.com</code> - Filter by domain name. Shows only queries for the specified domain. 
                You can use partial matches like <code>fqdn=google</code> to find all Google-related domains.</li>
              <li><code>client_ip=192.168.1.100</code> - Filter by the client's IP address. Useful for investigating 
                all DNS activity from a specific device.</li>
              <li><code>mac=AA:BB:CC:DD:EE:FF</code> - Filter by the client's MAC address. MAC addresses should be 
                entered with colons as separators.</li>
              <li><code>type=A</code> - Filter by DNS record type. Common values include A, AAAA, CNAME, MX, TXT, PTR.</li>
              <li><code>server=dns1</code> - Filter by the DNS server that handled the query.</li>
            </ul>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Filter Tips:</strong> Filters are applied in real-time 
              as you type. You can combine the filter with time period selection to narrow down results further. When 
              investigating an incident, start with a broad filter and progressively narrow it down as you identify 
              relevant patterns.
            </p>

            <h3 id="querylog-pagination">3.5 Pagination</h3>
            <p>
              Due to the potentially large volume of DNS queries, results are paginated to ensure responsive performance. 
              The default page size is 100 records, which provides a good balance between seeing enough data and 
              maintaining fast load times.
            </p>
            <p>
              Use the pagination controls at the bottom of the table to navigate through results. The controls show 
              the current page number, total number of pages, and total record count. You can jump to specific pages 
              or use the previous/next buttons to move through results sequentially.
            </p>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Performance Tip:</strong> If you're looking for 
              specific queries, use filters to narrow down results before paginating through large datasets. This is 
              much faster than manually browsing through thousands of pages.
            </p>
          </section>

          <!-- 4. RPZ Hits -->
          <section id="rpz-hits" class="help-section mb-5">
            <h2><i class="fas fa-shield-alt"></i> 4. RPZ Hits</h2>
            <hr>

            <h3 id="rpzhits-overview">4.1 Overview</h3>
            <p>
              The RPZ Hits section provides a dedicated view of all DNS queries that were blocked by Response Policy Zone 
              (RPZ) rules. RPZ is a DNS-based security mechanism that allows the DNS server to return modified responses 
              for specific domains, effectively blocking access to malicious, unwanted, or policy-violating content at 
              the DNS level before any connection is established.
            </p>
            <p>
              When a device on your network attempts to resolve a domain that matches an RPZ rule, the DNS server intercepts 
              the query and returns a blocking response (typically NXDOMAIN, indicating the domain doesn't exist) instead 
              of the actual IP address. This prevents the device from connecting to the blocked domain. The RPZ Hits log 
              records every such blocked query, providing visibility into what threats are being stopped and which devices 
              are attempting to access blocked resources.
            </p>
            <p>
              Blocks can originate from two sources: RPZ feeds (curated lists of malicious or unwanted domains provided by 
              ioc2rpz.net) and your custom Block List (domains you've manually added). The RPZ Hits view shows blocks from 
              both sources, allowing you to monitor the effectiveness of your DNS security and identify patterns that may 
              require attention.
            </p>
            <p>
              <i class="fas fa-shield-alt text-success"></i> <strong>Security Value:</strong> Regularly reviewing RPZ Hits 
              helps you understand the threat landscape affecting your network, identify potentially compromised devices, 
              and fine-tune your blocking policies. A device with unusually high block counts may be infected with malware 
              or running software that aggressively contacts blocked domains.
            </p>

            <h3 id="rpzhits-columns">4.2 Table Columns</h3>
            <p>
              The RPZ Hits table provides detailed information about each blocked DNS query. Understanding these columns 
              helps you investigate blocks and make informed decisions about your blocking policies.
            </p>
            <table class="table table-sm table-bordered">
              <thead><tr><th>Column</th><th>Description</th></tr></thead>
              <tbody>
                <tr>
                  <td><strong>Local Time</strong></td>
                  <td>The timestamp when the blocked query occurred, displayed in your local timezone. This column appears 
                    only in Logs view. The timestamp helps you correlate blocked queries with other events and understand 
                    when blocking activity occurred.</td>
                </tr>
                <tr>
                  <td><strong>Client</strong></td>
                  <td>The device that attempted to access the blocked domain. Displays the friendly name if configured in 
                    Assets, otherwise shows the IP or MAC address. Hover over the client to see additional details. 
                    Identifying which devices are triggering blocks is crucial for security investigation.</td>
                </tr>
                <tr>
                  <td><strong>Request</strong></td>
                  <td>The domain name that was blocked. This is the domain the client attempted to resolve but was prevented 
                    from accessing. Examining blocked domains helps you understand what threats are being stopped and whether 
                    any legitimate domains are being incorrectly blocked (false positives).</td>
                </tr>
                <tr>
                  <td><strong>Action</strong></td>
                  <td>The RPZ action that was applied to the query. Common actions include: NXDOMAIN (domain doesn't exist), 
                    NODATA (domain exists but has no records of the requested type), and others. NXDOMAIN is the most common 
                    blocking action and effectively prevents the connection.</td>
                </tr>
                <tr>
                  <td><strong>Rule</strong></td>
                  <td>The specific RPZ rule that triggered the block. This shows the exact pattern that matched the query. 
                    Rules can match exact domains or use wildcards to match subdomains. Understanding which rule triggered 
                    helps when troubleshooting false positives.</td>
                </tr>
                <tr>
                  <td><strong>Type</strong></td>
                  <td>Indicates type of the blocking rule (QNAME, RPZ-IP, RPZ-NSDNAME, RPZ-NSIP, RPZ-CLIENT-IP)</td>
                </tr>
                <tr>
                  <td><strong>Count</strong></td>
                  <td>In Stats view, shows how many times this particular block occurred. High counts indicate domains that 
                    are being repeatedly requested despite being blocked, which might indicate persistent malware or 
                    aggressive advertising software.</td>
                </tr>
              </tbody>
            </table>

            <h3 id="rpzhits-controls">4.3 Filtering and Navigation</h3>
            <p>
              The RPZ Hits section includes the same powerful controls as the Query Log, allowing you to efficiently 
              analyze and investigate blocked queries.
            </p>
            <ul>
              <li><strong>Logs/Stats toggle</strong> - Switch between viewing individual blocked query records (Logs) and 
                aggregated statistics (Stats). Use Logs view to see the chronological sequence of blocks; use Stats view 
                to identify the most frequently blocked domains or most active blocked clients.</li>
              <li><strong>Time period selection</strong> - Filter blocked queries by time range using the same preset 
                periods (30m, 1h, 1d, 1w, 30d) or custom date ranges available on the Dashboard. This helps you focus 
                on recent activity or investigate historical blocking patterns.</li>
              <li><strong>Filter input</strong> - Search and filter blocked queries by domain, client, feed type, or any 
                other field. Use simple text search or field-specific filters (e.g., <code>fqdn=malware.com</code>) to 
                find specific blocks quickly.</li>
              <li><strong>Pagination</strong> - Navigate through large result sets using the pagination controls. Results 
                are paginated to maintain performance when dealing with large numbers of blocked queries.</li>
              <li><strong>Auto-refresh</strong> - Enable automatic refresh to keep the RPZ Hits view current. This is 
                useful for real-time monitoring of blocking activity, especially during security incidents.</li>
            </ul>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Investigation Workflow:</strong> When investigating 
              a potential security issue, start by checking RPZ Hits for the suspected device. Look for patterns in 
              blocked domains - multiple blocks to similar domains might indicate malware trying to contact command and 
              control servers. If you find a legitimate domain being blocked, you can add it to your Allow List directly 
              from the action menu.
            </p>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>False Positives:</strong> Occasionally, legitimate 
              domains may be blocked by RPZ feeds. If you notice a domain being blocked that you believe should be 
              allowed, you can add it to your Allow List. Allow List entries take precedence over all RPZ feed rules, 
              ensuring the domain will be accessible. Always verify the domain is safe before adding it to the Allow List.
            </p>
          </section>

          <!-- 5. Admin Panel -->
          <section id="admin-panel" class="help-section mb-5">
            <h2><i class="fas fa-screwdriver"></i> 5. Admin Panel</h2>
            <hr>
            <p>
              The Admin Panel is the central configuration hub for RpiDNS, providing access to all management and 
              configuration tools. The panel is organized into multiple sub-tabs, each focusing on a specific aspect 
              of system administration. From here, you can manage network assets, configure RPZ feeds, maintain custom 
              block and allow lists, adjust system settings, access diagnostic tools, and manage user accounts.
            </p>
            <p>
              Access to certain Admin Panel features may be restricted based on your user privileges. Standard users 
              can typically access Assets, RPZ Feeds, Block List, Allow List, and basic Settings. The User Management 
              tab is restricted to administrator accounts only.
            </p>

            <h3 id="admin-assets">5.1 Assets</h3>
            <p>
              The Assets section allows you to manage and organize the network devices (assets) tracked by RpiDNS. 
              Assets represent the devices on your network that generate DNS queries. By maintaining an organized 
              asset list with friendly names, you can more easily identify which devices are responsible for specific 
              DNS activity throughout the application.
            </p>
            <p>
              Assets can be automatically discovered when new devices make DNS queries (if auto-creation is enabled 
              in Settings), or you can manually add them. Each asset can be assigned a friendly name, making it much 
              easier to identify devices in the Query Log, RPZ Hits, and Dashboard widgets instead of seeing raw 
              IP or MAC addresses.
            </p>
            <h4>Table Columns</h4>
            <ul>
              <li><strong>Address</strong> - The unique identifier for the asset, either a MAC address or IP address 
                depending on your tracking mode configuration. MAC addresses are preferred as they remain constant 
                even when IP addresses change (DHCP), but IP tracking may be necessary in some network configurations.</li>
              <li><strong>Name</strong> - The friendly name you've assigned to this device. This name appears throughout 
                RpiDNS wherever the device is referenced. Choose descriptive names like "John's Laptop", "Living Room TV", 
                or "Office Printer" to make identification easy.</li>
              <li><strong>Vendor</strong> - The hardware manufacturer, automatically detected from the MAC address using 
                the IEEE OUI database. This helps identify device types even before you assign a friendly name. Note that 
                vendor detection only works with MAC address tracking.</li>
              <li><strong>Added</strong> - The date and time when this asset was first seen or manually added to the system. 
                This helps you identify new devices on your network and track when devices first appeared.</li>
              <li><strong>Comment</strong> - An optional notes field where you can add additional information about the 
                device, such as its location, owner, purpose, or any other relevant details.</li>
            </ul>
            <h4>Actions</h4>
            <ul>
              <li><i class="fas fa-plus"></i> <strong>Add</strong> - Manually create a new asset entry. You'll need to 
                provide the MAC or IP address (depending on tracking mode) and can optionally set a name and comment. 
                Use this when you want to pre-configure assets before they make their first DNS query.</li>
              <li><i class="fas fa-edit"></i> <strong>Edit</strong> - Modify the selected asset's details. First select 
                an asset by clicking its checkbox, then click Edit to change its name, comment, or other properties. 
                You cannot change the address of an existing asset.</li>
              <li><i class="fas fa-trash-alt"></i> <strong>Delete</strong> - Remove the selected asset from the system. 
                This only removes the asset record; historical DNS queries from this device remain in the logs. If 
                auto-creation is enabled, the asset may be recreated when the device makes another DNS query.</li>
              <li><i class="fas fa-sync"></i> <strong>Refresh</strong> - Reload the asset list from the server to see 
                the latest data, including any newly auto-discovered devices.</li>
            </ul>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Tracking Mode:</strong> Assets can be tracked by MAC 
              address or IP address, configured in the Settings section. MAC address tracking is recommended as it 
              provides consistent device identification even when IP addresses change. However, IP tracking may be 
              necessary if your network configuration doesn't preserve MAC addresses (e.g., through certain routers 
              or VPNs).
            </p>

            <h3 id="admin-rpzfeeds">5.2 RPZ Feeds</h3>
            <p>
              RPZ (Response Policy Zone) Feeds are curated lists of domains that control DNS-level blocking behavior. 
              The RPZ Feeds section provides comprehensive management capabilities for adding, editing, removing, 
              enabling/disabling, and reordering feeds from three different sources. This is the central hub for 
              configuring your DNS protection strategy.
            </p>
            <p>
              Feed order is critical because BIND evaluates feeds in the order they are configured, and the first 
              matching rule wins. This means you can create sophisticated blocking policies by carefully ordering 
              your feeds, with allow feeds typically placed before block feeds to ensure whitelisted domains are 
              never blocked.
            </p>

            <h4>Feed Source Types</h4>
            <p>RpiDNS supports three types of RPZ feeds, each serving different use cases:</p>
            <ul>
              <li><strong>ioc2rpz.net Feeds</strong> - Open source threat intelligence (OSINT) feeds from 
                <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a>. These feeds are automatically updated 
                via zone transfers and require a TSIG key for authentication. They cover various threat categories 
                including malware, phishing, advertising, and tracking. These feeds are maintained by 
                security researchers who share them with the community.</li>
              <li><strong>Local Feeds</strong> - Custom feeds you create and manage directly within RpiDNS. Local 
                feeds are master zones stored on your DNS server, giving you complete control over their content. 
                Use local feeds for organization-specific blocking rules, custom allow lists, or any domains you 
                want to manage independently of external sources.</li>
              <li><strong>Third-Party Feeds</strong> - External RPZ feeds from sources other than ioc2rpz.net. These 
                are configured as secondary zones that transfer from a specified primary server. Third-party feeds 
                can optionally use TSIG authentication for secure zone transfers. Use these when you have access to 
                commercial or community threat feeds from other providers.</li>
            </ul>

            <h4>Table Columns</h4>
            <table class="table table-sm table-bordered">
              <thead><tr><th>Column</th><th>Description</th></tr></thead>
              <tbody>
                <tr>
                  <td><strong>Drag Handle</strong></td>
                  <td>The <i class="fas fa-grip-vertical"></i> icon on the left side of each row. Click and drag 
                    this handle to reorder feeds. Feed order determines evaluation priority - BIND processes feeds 
                    from top to bottom, and the first matching rule wins.</td>
                </tr>
                <tr>
                  <td><strong>Feed</strong></td>
                  <td>The zone name/identifier of the RPZ feed. This name appears in RPZ Hits when a domain from 
                    this feed triggers a policy action, helping you identify which feed was responsible.</td>
                </tr>
                <tr>
                  <td><strong>Action</strong></td>
                  <td>The policy action applied when a query matches a rule in this feed. Displayed as a colored 
                    badge indicating the action type. See the Policy Actions section below for detailed explanations 
                    of each action.</td>
                </tr>
                <tr>
                  <td><strong>Source</strong></td>
                  <td>Indicates the feed's origin: <span class="badge bg-primary">ioc2rpz.net</span> for feeds from 
                    ioc2rpz.net, <span class="badge bg-success">Local</span> for locally managed feeds, or 
                    <span class="badge bg-info">Third-Party</span> for external feeds from other sources.</td>
                </tr>
                <tr>
                  <td><strong>Status</strong></td>
                  <td>Shows whether the feed is currently active. <span class="text-success"><i class="fas fa-check-circle"></i> Enabled</span> 
                    feeds are actively processing DNS queries. <span class="text-muted"><i class="fas fa-times-circle"></i> Disabled</span> 
                    feeds remain configured but don't affect DNS resolution until re-enabled.</td>
                </tr>
                <tr>
                  <td><strong>Description</strong></td>
                  <td>Additional information about the feed's purpose and content. For ioc2rpz.net feeds, this 
                    describes the threat category. For local and third-party feeds, this shows your custom description.</td>
                </tr>
              </tbody>
            </table>

            <h4>Policy Actions</h4>
            <p>
              Policy actions determine how the DNS server responds when a query matches a rule in the feed. 
              Understanding these actions helps you configure appropriate responses for different threat types:
            </p>
            <ul>
              <li><span class="badge bg-danger">nxdomain</span> <strong>NXDOMAIN</strong> - Returns "domain does not exist" 
                response. This is the most common blocking action and effectively prevents any connection to the domain. 
                The client receives a definitive "not found" response, causing applications to fail gracefully. 
                <em>Best for: General blocking of malicious or unwanted domains.</em></li>
              <li><span class="badge bg-warning">nodata</span> <strong>NODATA</strong> - Returns a response indicating 
                the domain exists but has no records of the requested type. This is a softer block that may cause 
                different application behavior than NXDOMAIN.</li>
              <li><span class="badge bg-success">passthru</span> <strong>PASSTHRU</strong> - Allows the query to proceed 
                normally without any blocking. This action is used for allow/whitelist feeds to explicitly permit 
                domains that might otherwise be blocked by subsequent feeds. <em>Best for: Allow feeds and whitelisting 
                specific domains.</em></li>
              <li><span class="badge bg-dark">drop</span> <strong>DROP</strong> - Silently drops the query without 
                sending any response. The client receives no answer and must wait for timeout. This can slow down 
                applications but may be useful for certain security scenarios. <em>Best for: Aggressive blocking 
                where you don't want to reveal that blocking is occurring.</em></li>
              <li><span class="badge bg-info">cname</span> <strong>CNAME</strong> - Redirects the query to a different 
                domain (requires specifying a target). This can redirect blocked requests to a block page or 
                sinkhole server. <em>Best for: Displaying block pages or redirecting to internal resources.</em></li>
              <li><span class="badge bg-primary">given</span> <strong>GIVEN</strong> - Uses the action defined within 
                the feed rules itself.</li>
            </ul>

            <h4>Toolbar Actions</h4>
            <p>The toolbar provides buttons for managing feeds. Some actions require selecting a feed first:</p>
            <ul>
              <li><i class="fas fa-plus"></i> <strong>Add (Dropdown)</strong> - Opens a dropdown menu with three options:
                <ul>
                  <li><i class="fas fa-cloud-download-alt"></i> <strong>ioc2rpz.net Feed</strong> - Opens a modal to 
                    select and add feeds from ioc2rpz.net. Requires a configured TSIG key.</li>
                  <li><i class="fas fa-file-alt"></i> <strong>Local Feed</strong> - Opens a form to create a new 
                    local RPZ feed with custom settings.</li>
                  <li><i class="fas fa-server"></i> <strong>Third-Party Feed</strong> - Opens a form to configure 
                    a feed from an external source.</li>
                </ul>
              </li>
              <li><i class="fas fa-edit"></i> <strong>Edit</strong> - Modify the selected feed's configuration. 
                Select a feed by clicking its row first. For ioc2rpz.net feeds, only the policy action can be 
                changed. For local and third-party feeds, you can modify action, description, and server settings.</li>
              <li><i class="fas fa-trash-alt"></i> <strong>Delete</strong> - Remove the selected feed from your 
                configuration. Predefined system feeds (allow.ioc2rpz.rpidns, block.ioc2rpz.rpidns, etc.) cannot 
                be deleted. A confirmation dialog appears before deletion.</li>
              <li><i class="fas fa-toggle-on"></i> / <i class="fas fa-toggle-off"></i> <strong>Enable/Disable</strong> - 
                Toggle the selected feed's active status. Disabled feeds remain in your configuration but don't 
                process DNS queries. This is useful for temporarily suspending a feed without removing it.</li>
              <li><i class="fas fa-redo"></i> <strong>Retransfer</strong> - Request an immediate zone transfer for 
                the selected feed. Only available for non-local feeds (ioc2rpz.net and third-party). Use this to 
                get the latest feed data immediately rather than waiting for the scheduled transfer.</li>
              <li><i class="fas fa-sync"></i> <strong>Refresh</strong> - Reload the feed list from the server to 
                see the current configuration.</li>
            </ul>

            <h4>Feed Ordering (Drag and Drop)</h4>
            <p>
              Feed order is critical for proper RPZ operation. BIND evaluates feeds from top to bottom, and the 
              first matching rule determines the response. This means:
            </p>
            <ul>
              <li>Allow feeds should typically be placed before block feeds to ensure whitelisted domains are 
                never blocked</li>
              <li>More specific feeds should generally come before more general feeds</li>
              <li>Your custom local feeds can be positioned to override or supplement external feeds</li>
              <li><i class="fas fa-exclamation-triangle text-warning"></i> Security Concideration: IP-based feeds trigger domain resolution, which can be exploited for data exfiltration. Place these feeds after all domain-based feeds in your configuration. If that is not possible, ensure that anti-tunneling rules are evaluated first.</li>
            </ul>
            <p>
              To reorder feeds, click and drag the <i class="fas fa-grip-vertical"></i> handle on the left side 
              of any row. Drop the feed at its new position. The new order is automatically saved to your BIND 
              configuration and takes effect immediately after BIND reloads.
            </p>

            <h4>Predefined Feeds</h4>
            <p>
              RpiDNS includes predefined feeds for common use cases. These feeds cannot be deleted but can be 
              enabled/disabled and reordered:
            </p>
            <ul>
              <li><strong>allow.ioc2rpz.rpidns</strong> - Local allow list for domains. Uses passthru action to 
                ensure listed domains are never blocked. Add domains here via the Allow section.</li>
              <li><strong>allow-ip.ioc2rpz.rpidns</strong> - Local allow list for IP-based rules. Uses passthru 
                action for IP address allowlisting.</li>
              <li><strong>block.ioc2rpz.rpidns</strong> - Local block list for domains. Uses redirect(cname) action to 
                block listed domains. Add domains here via the Block section.</li>
              <li><strong>block-ip.ioc2rpz.rpidns</strong> - Local block list for IP-based rules. Uses redirect(cname) 
                action for IP address blocking.</li>
            </ul>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Action Restrictions:</strong> Allow feeds 
              (allow.ioc2rpz.rpidns, allow-ip.ioc2rpz.rpidns) can only use the passthru action. Block feeds 
              can use nxdomain, nodata, drop, or cname actions.
            </p>

            <h4>BIND Configuration</h4>
            <p>
              RPZ feeds are configured in your BIND configuration file (named.conf or named.conf.options). When 
              you make changes through the RpiDNS interface:
            </p>
            <ul>
              <li>The configuration is validated using <code>named-checkconf</code> before being applied</li>
              <li>If validation fails, changes are automatically rolled back and an error message is displayed</li>
              <li>If validation succeeds, BIND is reloaded using <code>rndc reload</code> to apply changes</li>
              <li>Changes take effect immediately after the reload completes</li>
            </ul>
            <p>
              <i class="fas fa-shield-alt text-success"></i> <strong>Safety:</strong> The automatic validation 
              and rollback mechanism ensures that invalid configurations never break your DNS service. If something 
              goes wrong, your previous working configuration is preserved.
            </p>

            <h4>Adding Feeds Step-by-Step</h4>
            <p><strong>Adding an ioc2rpz.net Feed:</strong></p>
            <ol>
              <li>Click the <i class="fas fa-plus"></i> Add dropdown and select "ioc2rpz.net Feed"</li>
              <li>The system will fetch available feeds using your configured TSIG key</li>
              <li>Select one or more feeds from the list by clicking their checkboxes</li>
              <li>Optionally change the policy action</li>
              <li>Click "Add Selected Feeds" to add them to your configuration</li>
            </ol>
            <p><strong>Adding a Local Feed:</strong></p>
            <ol>
              <li>Click the <i class="fas fa-plus"></i> Add dropdown and select "Local Feed"</li>
              <li>Enter a unique feed name following DNS naming conventions</li>
              <li>Select a policy action</li>
              <li>If using CNAME action, enter the redirect target domain</li>
              <li>Optionally add a description</li>
              <li>Click "Add Feed" to create the feed</li>
            </ol>
            <p><strong>Adding a Third-Party Feed:</strong></p>
            <ol>
              <li>Click the <i class="fas fa-plus"></i> Add dropdown and select "Third-Party Feed"</li>
              <li>Enter the feed name and primary server IP-address</li>
              <li>If the feed requires authentication, enter the TSIG key name and secret</li>
              <li>Select a policy action</li>
              <li>Optionally add a description</li>
              <li>Click "Add Feed" to configure the feed</li>
            </ol>

            <h3 id="admin-blocklist">5.3 Block List</h3>
            <p>
              The Block List is your custom collection of domains and IP addresses that you want to block on your 
              network. Unlike RPZ feeds which are managed externally, the Block List gives you complete control over 
              what gets blocked. Use it to block specific domains that aren't covered by feeds, enforce organizational 
              policies, or block domains you've identified as problematic for your specific environment.
            </p>
            <p>
              Entries in your Block List are processed alongside RPZ feed rules. When a device queries a domain that 
              matches a Block List entry, the DNS server returns a blocking response (NXDOMAIN), preventing the 
              connection. Block List entries appear as "local" type in RPZ Hits.
            </p>
            <h4>Table Columns</h4>
            <ul>
              <li><strong>Domain/IP</strong> - The domain name or IP address to block. Enter the exact domain you want 
                to block (e.g., "example.com"). The entry will block DNS queries for this specific domain.</li>
              <li><strong>Added</strong> - The date and time when this entry was created. Helps you track when blocks 
                were added and maintain an audit trail of your blocking decisions.</li>
              <li><strong>Active</strong> - A toggle indicating whether this block rule is currently active. Inactive 
                rules remain in the list but don't block any traffic. This allows you to temporarily disable a block 
                without deleting it, useful for testing or troubleshooting.</li>
              <li><strong>*. (Subdomains)</strong> - A toggle that, when enabled, also blocks all subdomains of the 
                specified domain. For example, if you block "example.com" with subdomains enabled, it will also block 
                "www.example.com", "mail.example.com", "any.subdomain.example.com", etc. This is useful for blocking 
                entire domain families.</li>
              <li><strong>Comment</strong> - An optional notes field where you can document why this domain was blocked, 
                who requested the block, or any other relevant information. Good documentation helps when reviewing 
                blocks later.</li>
            </ul>
            <h4>Actions</h4>
            <ul>
              <li><i class="fas fa-plus"></i> <strong>Add</strong> - Create a new block entry. Enter the domain or IP 
                address, configure whether to block subdomains, add an optional comment, and set whether the rule 
                should be immediately active.</li>
              <li><i class="fas fa-edit"></i> <strong>Edit</strong> - Modify an existing block entry. Select the entry 
                first, then click Edit to change its properties. You can modify the domain, subdomain setting, active 
                status, and comment.</li>
              <li><i class="fas fa-trash-alt"></i> <strong>Delete</strong> - Remove the selected block entry. The domain 
                will no longer be blocked by your custom rules (though it may still be blocked by RPZ feeds).</li>
              <li><i class="fas fa-sync"></i> <strong>Refresh</strong> - Reload the Block List to see the current entries.</li>
            </ul>
            <p>
              <strong>Quick Toggle Controls:</strong> You can quickly enable/disable entries or toggle subdomain blocking 
              by clicking the toggle icons directly in the table, without opening the edit dialog. This provides a fast 
              way to adjust multiple entries.
            </p>

            <h3 id="admin-allowlist">5.4 Allow List</h3>
            <p>
              The Allow List (also known as a whitelist) contains domains that should never be blocked, regardless of 
              RPZ feed rules. When a domain is on your Allow List, it bypasses all blocking rules and is always resolved 
              normally. This is essential for handling false positives where legitimate domains are incorrectly included 
              in RPZ feeds.
            </p>
            <p>
              Allow List entries take precedence over all other blocking rules, including both RPZ feeds and your custom 
              Block List. Use the Allow List judiciously - only add domains you've verified are safe and necessary for 
              your network operations.
            </p>
            <p>
              The Allow List interface works identically to the Block List, with the same columns (Domain/IP, Added, 
              Active, Subdomains, Comment) and actions (Add, Edit, Delete, Refresh). The subdomain toggle is particularly 
              useful here - if you need to allow a service that uses multiple subdomains, enabling subdomain matching 
              ensures all related domains are allowed.
            </p>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>When to Use the Allow List:</strong> Add domains to 
              the Allow List when you encounter false positives (legitimate domains blocked by RPZ feeds), when specific 
              services require access to domains that would otherwise be blocked, or when troubleshooting to verify 
              whether blocking is causing an issue.
            </p>
            <p>
              <i class="fas fa-exclamation-triangle text-warning"></i> <strong>Security Consideration:</strong> Before 
              adding a domain to the Allow List, verify that it's genuinely safe. Malware sometimes uses domains that 
              appear legitimate. Use the Research links available in the Dashboard and RPZ Hits to investigate domains 
              before allowing them.
            </p>

            <h3 id="admin-settings">5.5 Settings</h3>
            <p>
              The Settings section provides configuration options for RpiDNS behavior, data retention policies, and 
              system preferences. Changes made here affect how the entire system operates.
            </p>
            
            <h4>Data Statistics and Retention</h4>
            <p>
              This section displays current database statistics and allows you to configure data retention policies. 
              Managing retention is important for balancing storage usage with the need for historical data.
            </p>
            <ul>
              <li><strong>Table</strong> - The name of the database table storing specific types of data (queries, 
                RPZ hits, etc.).</li>
              <li><strong>Size</strong> - The current storage space used by this table. Monitor this to ensure you 
                have adequate disk space.</li>
              <li><strong>Rows</strong> - The number of records currently stored in the table. High row counts may 
                impact query performance.</li>
              <li><strong>From/To</strong> - The date range of data currently stored, showing the oldest and newest 
                records. This helps you understand how much historical data is available.</li>
              <li><strong>Retention</strong> - The number of days to keep data before automatic deletion. Adjust this 
                value based on your storage capacity and compliance requirements. Shorter retention saves space; longer 
                retention provides more historical data for analysis.</li>
            </ul>
            <p>
              <i class="fas fa-database text-info"></i> <strong>Retention Tips:</strong> For most environments, 30-90 
              days of retention provides a good balance. High-traffic networks may need shorter retention to manage 
              storage. Compliance requirements may dictate minimum retention periods.
            </p>

            <h4>Miscellaneous Settings</h4>
            <ul>
              <li><strong>Automatically create assets</strong> - When enabled, new devices are automatically added to 
                the Assets list when they first make a DNS query. This ensures all devices are tracked without manual 
                intervention. Disable this if you prefer to manually control which devices appear in your asset list.</li>
              <li><strong>Track assets by</strong> - Choose whether to identify devices by MAC Address or IP Address. 
                MAC address tracking is recommended as it provides consistent identification even when IP addresses 
                change. IP tracking may be necessary in networks where MAC addresses aren't preserved.</li>
              <li><strong>Dashboard show Top</strong> - Configure how many items appear in each Dashboard widget (e.g., 
                Top 10, Top 20). Higher numbers show more data but may make widgets harder to read at a glance.</li>
            </ul>

            <h4>Account Security</h4>
            <p>
              Click the <strong>Change Password</strong> button to update your account password. You'll need to enter 
              your current password for verification, then enter and confirm your new password. Strong passwords should 
              be at least 8 characters and include a mix of letters, numbers, and special characters.
            </p>
            <p>
              <i class="fas fa-exclamation-triangle text-warning"></i> <strong>Important:</strong> Remember to click 
              the <strong>Save</strong> button after making any changes to settings. Changes are not applied until 
              saved. If you navigate away without saving, your changes will be lost.
            </p>

            <h3 id="admin-tools">5.6 Tools</h3>
            <p>
              The Tools section provides access to various utilities for system maintenance, backup, and troubleshooting.
            </p>
            
            <h4>CA Root Certificate</h4>
            <p>
              Download the Certificate Authority (CA) root certificate used by RpiDNS. This certificate is used to sign 
              SSL certificates for the block page that users see when accessing blocked domains over HTTPS. Installing 
              this certificate in your browser or operating system's trust store eliminates security warnings when 
              viewing block pages.
            </p>
            <p>
              <i class="fas fa-certificate text-info"></i> <strong>Installation:</strong> After downloading, install 
              the certificate in your browser's or operating system's certificate store as a trusted root CA. The 
              exact process varies by browser and OS. This is optional but improves the user experience when blocked 
              pages are displayed.
            </p>

            <h4>Database</h4>
            <p>Database management tools for backup and restore operations:</p>
            <ul>
              <li><i class="fas fa-download"></i> <strong>Download</strong> - Creates and downloads a backup of the 
                SQLite database containing all RpiDNS data including queries, RPZ hits, assets, block/allow lists, 
                settings, and user accounts. Regular backups are strongly recommended to prevent data loss.</li>
              <li><i class="fas fa-upload"></i> <strong>Import</strong> - Restore data from a previously downloaded 
                backup file. Use this to recover from data loss or migrate to a new installation. Warning: importing 
                a backup will overwrite current data.</li>
            </ul>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Backup Best Practices:</strong> Create regular 
              backups, especially before making significant configuration changes. Store backups in a secure location 
              separate from the RpiDNS server. Test your backup restoration process periodically to ensure backups 
              are valid.
            </p>

            <h4>ISC Bind Log Files</h4>
            <p>
              Download raw log files from the ISC BIND DNS server for advanced troubleshooting and analysis. These 
              files contain detailed technical information that may be needed for diagnosing complex issues.
            </p>
            <ul>
              <li><strong>bind.log</strong> - General DNS server operational logs including startup messages, 
                configuration loading, zone transfers, and error messages. Useful for diagnosing server-level issues.</li>
              <li><strong>bind_queries.log</strong> - Raw DNS query logs in BIND's native format. Contains detailed 
                information about every query processed, useful for advanced analysis or integration with external 
                log analysis tools.</li>
              <li><strong>bind_rpz.log</strong> - RPZ-specific logs showing detailed information about RPZ rule 
                processing, zone updates, and blocking decisions. Essential for troubleshooting RPZ-related issues.</li>
            </ul>

            <h3 id="admin-users">5.7 User Management</h3>
            <p>
              <i class="fas fa-lock text-danger"></i> <strong>Administrator Only</strong> - This section is only 
              accessible to users with administrator privileges. Standard users will not see this tab.
            </p>
            <p>
              User Management allows administrators to create, modify, and delete user accounts for RpiDNS access. 
              Each user has their own credentials and can be granted either standard or administrator privileges.
            </p>
            
            <h4>User List</h4>
            <p>The user list displays all accounts configured in the system:</p>
            <ul>
              <li><strong>Username</strong> - The user's login name. Usernames are case-sensitive and must be unique.</li>
              <li><strong>Admin</strong> - Indicates administrator status. A <i class="fas fa-check text-success"></i> 
                checkmark indicates the user has administrator privileges, granting access to User Management and all 
                other features. Users without admin status have restricted access to certain features.</li>
              <li><strong>Created</strong> - The date when the account was created. Helps track account age and 
                identify recently added users.</li>
            </ul>
            
            <h4>Actions</h4>
            <ul>
              <li><i class="fas fa-plus"></i> <strong>Add User</strong> - Create a new user account. You'll need to 
                specify a username, initial password, and whether the user should have administrator privileges. 
                Share the credentials securely with the new user and recommend they change their password after 
                first login.</li>
              <li><i class="fas fa-key"></i> <strong>Reset Password</strong> - Generate a new random password for 
                the selected user. The new password will be displayed once - make sure to copy it and share it 
                securely with the user. This is useful when users forget their passwords.</li>
              <li><i class="fas fa-trash"></i> <strong>Delete</strong> - Remove the selected user account. The user 
                will immediately lose access to RpiDNS. This action cannot be undone.</li>
            </ul>
            <p>
              <i class="fas fa-exclamation-triangle text-warning"></i> <strong>Important Restrictions:</strong> You 
              cannot delete the last administrator account in the system. This prevents accidental lockout. If you 
              need to remove the last admin, first create another admin account, then delete the original.
            </p>
            <p>
              <i class="fas fa-user-shield text-info"></i> <strong>Security Best Practices:</strong> Create individual 
              accounts for each user rather than sharing credentials. Grant administrator privileges only to users 
              who need them. Regularly review user accounts and remove those no longer needed. Encourage users to 
              use strong, unique passwords.
            </p>
          </section>

          <!-- 6. Common Actions -->
          <section id="common-actions" class="help-section mb-5">
            <h2><i class="fas fa-mouse-pointer"></i> 6. Common Actions</h2>
            <hr>
            <p>
              This section covers the most frequently performed tasks in RpiDNS and provides step-by-step guidance 
              for accomplishing them efficiently. These common actions are designed to be accessible from multiple 
              places in the interface, allowing you to work in whatever context is most convenient.
            </p>

            <h3 id="blocking-domain">6.1 Blocking a Domain</h3>
            <p>
              Blocking a domain prevents all devices on your network from accessing it. When a blocked domain is 
              queried, the DNS server returns an NXDOMAIN response, effectively making the domain unreachable. 
              This is useful for blocking malicious sites, unwanted content, or enforcing organizational policies.
            </p>
            <p>You can block a domain from multiple locations throughout the application:</p>
            <ol>
              <li><strong>From the Dashboard</strong> - Hover over any domain in the TopX widgets (Allowed Requests, 
                Blocked Requests, etc.). A set of action buttons will appear. Click the "Block" button to add the 
                domain to your Block List. This is the quickest method when you spot a domain you want to block 
                while monitoring the Dashboard.</li>
              <li><strong>From the Query Log</strong> - Find the domain you want to block in the Query Log. Hover 
                over the domain name or right-click to access the context menu. Select the block option to add it 
                to your Block List. This method is useful when investigating specific queries and deciding to block 
                based on your analysis.</li>
              <li><strong>From the Admin Panel</strong> - Navigate to Admin → Block List and click the 
                <i class="fas fa-plus"></i> Add button. Enter the domain name manually in the dialog that appears. 
                This method gives you the most control over the block entry and is useful when you know exactly 
                what domain you want to block without needing to find it in logs first.</li>
            </ol>
            <p>
              When adding a domain to the Block List, you have several configuration options:
            </p>
            <ul>
              <li><strong>Subdomains (*.) toggle</strong> - Enable this option to block not just the specified domain 
                but all of its subdomains as well. For example, blocking "example.com" with subdomains enabled will 
                also block "www.example.com", "api.example.com", "mail.example.com", and any other subdomain. This 
                is useful for blocking entire services that use multiple subdomains.</li>
              <li><strong>Comment field</strong> - Add a note explaining why you blocked this domain. Good documentation 
                helps when reviewing blocks later. Include information like who requested the block, what issue it 
                addresses, or any relevant ticket numbers.</li>
              <li><strong>Active toggle</strong> - Controls whether the block is immediately active. You can create 
                inactive blocks to prepare rules in advance, or temporarily disable a block without deleting it 
                for testing purposes.</li>
            </ul>
            <p>
              <i class="fas fa-clock text-info"></i> <strong>When Blocks Take Effect:</strong> Block List changes take 
              effect immediately. As soon as you add a domain to the Block List, subsequent DNS queries for that 
              domain will be blocked. Existing connections are not affected, but new connection attempts will fail.
            </p>

            <h3 id="allowing-domain">6.2 Allowing a Domain</h3>
            <p>
              Adding a domain to the Allow List ensures it will never be blocked, regardless of RPZ feed rules or 
              Block List entries. The Allow List takes precedence over all other blocking mechanisms. This is 
              essential for handling false positives where legitimate domains are incorrectly blocked.
            </p>
            <p>You can allow a domain from several locations:</p>
            <ol>
              <li><strong>From the Dashboard</strong> - When viewing blocked domains in the TopX Blocked Requests 
                widget, hover over a domain and click the "Allow" button. This immediately adds the domain to your 
                Allow List, overriding any blocks.</li>
              <li><strong>From RPZ Hits</strong> - Find the blocked domain in the RPZ Hits log. Hover over the domain 
                or use the context menu to select the allow option. This is the most common workflow when 
                investigating why a legitimate service isn't working and discovering it's being blocked.</li>
              <li><strong>From the Admin Panel</strong> - Navigate to Admin → Allow List and click the 
                <i class="fas fa-plus"></i> Add button. Enter the domain manually. This method is useful when you 
                know in advance that a domain should be allowed, such as when deploying a new service.</li>
            </ol>
            <p>
              <i class="fas fa-info-circle text-info"></i> <strong>Precedence Rules:</strong> Allow List entries 
              always take precedence over blocks. If a domain appears in both your Block List and Allow List, it 
              will be allowed. Similarly, Allow List entries override RPZ feed blocks. This design ensures you 
              always have the final say over what's accessible on your network.
            </p>
            <p>
              <i class="fas fa-exclamation-triangle text-warning"></i> <strong>Security Warning:</strong> Before 
              allowing a blocked domain, verify that it's genuinely safe. Some malware uses domains that appear 
              legitimate or mimics well-known services. Use the Research links to investigate domains before 
              adding them to your Allow List. When in doubt, investigate further before allowing.
            </p>

            <h3 id="research-links">6.3 Research Links</h3>
            <p>
              RpiDNS provides quick access to external security research tools that help you investigate domains 
              before making blocking decisions. These tools aggregate threat intelligence from multiple sources 
              and can reveal whether a domain is associated with malware, phishing, or other malicious activity.
            </p>
            <p>
              When hovering over domains in Dashboard widgets or other areas, you'll see a "Research" option that 
              opens a submenu with links to various security tools:
            </p>
            <ul>
              <li><strong>VirusTotal</strong> - A comprehensive malware scanning service that aggregates results from 
                dozens of antivirus engines and security tools. VirusTotal shows detection rates, historical data, 
                associated files, and community comments about the domain. This is often the first stop for domain 
                investigation.</li>
              <li><strong>URLhaus</strong> - A project by abuse.ch that tracks malicious URLs used for malware 
                distribution. URLhaus is particularly useful for identifying domains involved in malware campaigns 
                and provides detailed information about associated threats.</li>
              <li><strong>Additional security databases</strong> - Depending on configuration, additional research 
                links may be available to other threat intelligence platforms, WHOIS lookup services, or domain 
                reputation databases.</li>
            </ul>
            <p>
              <i class="fas fa-search text-info"></i> <strong>Investigation Workflow:</strong> When you encounter 
              an unfamiliar domain, especially one that's being blocked, use the Research links to gather more 
              information. Check multiple sources as different tools may have different coverage. Look for patterns 
              like recent registration dates, suspicious hosting, or associations with known threat actors.
            </p>

            <h3 id="menu-controls">6.4 Menu Controls</h3>
            <p>
              The RpiDNS navigation menu can be customized to suit your preferences and screen size. The collapsible 
              menu feature allows you to maximize screen space for content while maintaining quick access to all 
              sections.
            </p>
            <ul>
              <li><i class="fas fa-angle-double-left"></i> <strong>Collapse Menu</strong> - Click this icon at the 
                bottom of the navigation menu to collapse it to a narrow strip showing only icons. This gives you 
                more horizontal space for viewing data tables, charts, and other content. The icons remain visible 
                and clickable for navigation.</li>
              <li><i class="fas fa-angle-double-right"></i> <strong>Expand Menu</strong> - Click this icon to expand 
                the menu back to full width, showing both icons and text labels. The expanded view makes it easier 
                to identify sections, especially for new users still learning the interface.</li>
            </ul>
            <p>
              <i class="fas fa-save text-success"></i> <strong>Persistent Preference:</strong> Your menu collapse/expand 
              preference is automatically saved and persists across browser sessions. When you return to RpiDNS, 
              the menu will be in the same state you left it. This preference is stored locally in your browser.
            </p>

            <h3 id="navigating-from-dashboard">6.5 Navigating from Dashboard</h3>
            <p>
              The Dashboard serves as a navigation hub, allowing you to quickly drill down into detailed views 
              filtered by specific items. This contextual navigation preserves your current time period selection 
              and automatically applies relevant filters, streamlining your investigation workflow.
            </p>
            <p>Click on items in Dashboard widgets to navigate to filtered views:</p>
            <ul>
              <li><strong>Click a domain in Allowed Requests</strong> - Opens the Query Log filtered to show only 
                queries for that specific domain. This helps you see which devices are accessing the domain and 
                how frequently.</li>
              <li><strong>Click a client in Allowed/Blocked Clients</strong> - Opens the Query Log filtered to show 
                all queries from that specific device. This is useful for investigating a device's DNS activity 
                and identifying what services or sites it's accessing.</li>
              <li><strong>Click a domain in Blocked Requests</strong> - Opens the RPZ Hits view filtered to that 
                domain, showing all block events for that domain across all devices. This helps you understand 
                the scope of blocking for a particular domain.</li>
              <li><strong>Click a feed in TopX Feeds</strong> - Opens the RPZ Hits view filtered to show only blocks 
                from that specific RPZ feed. This helps you understand what types of threats a particular feed 
                is catching on your network.</li>
            </ul>
            <p>
              <i class="fas fa-clock text-info"></i> <strong>Time Period Preservation:</strong> When you navigate 
              from the Dashboard to Query Log or RPZ Hits, your selected time period is preserved. If you were 
              viewing the last 24 hours on the Dashboard, the filtered view will also show the last 24 hours. 
              This ensures consistency in your analysis and prevents confusion from mismatched time ranges.
            </p>
            <p>
              <i class="fas fa-lightbulb text-warning"></i> <strong>Workflow Tip:</strong> Use the Dashboard as your 
              starting point for investigations. Identify interesting items in the widgets, click to drill down 
              for details, then use the detailed views to make decisions about blocking or allowing. This top-down 
              approach is efficient for both routine monitoring and incident investigation.
            </p>
          </section>

        </div>
      </BCard>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onBeforeUnmount } from 'vue'

export default {
  name: 'HelpContent',
  setup() {
    const contentArea = ref(null)
    const sidebarCollapsed = ref(false)
    const showBackToTop = ref(false)

    const navigateTo = (sectionId) => {
      const element = document.getElementById(sectionId)
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' })
        // Collapse sidebar after navigation
        sidebarCollapsed.value = true
      }
    }

    const collapseSidebar = () => {
      sidebarCollapsed.value = true
    }

    const scrollToTop = () => {
      if (contentArea.value) {
        contentArea.value.scrollTo({ top: 0, behavior: 'smooth' })
      }
    }

    const handleScroll = () => {
      if (contentArea.value) {
        showBackToTop.value = contentArea.value.scrollTop > 300
      }
    }

    onMounted(() => {
      if (contentArea.value) {
        contentArea.value.addEventListener('scroll', handleScroll)
      }
    })

    onBeforeUnmount(() => {
      if (contentArea.value) {
        contentArea.value.removeEventListener('scroll', handleScroll)
      }
    })

    return {
      contentArea,
      sidebarCollapsed,
      showBackToTop,
      navigateTo,
      collapseSidebar,
      scrollToTop
    }
  }
}
</script>

<style scoped>
.help-wrapper {
  display: flex;
  height: calc(100vh - 80px);
  position: relative;
  overflow: hidden;
}

/* Sidebar Navigation */
.help-sidebar {
  width: 200px;
  min-width: 200px;
  max-width: 200px;
  background-color: #f8f9fa;
  border-right: 1px solid #dee2e6;
  transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
  overflow-y: auto;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.help-sidebar.collapsed {
  width: 50px;
  min-width: 50px;
  max-width: 50px;
}

.sidebar-header {
  padding: 1rem;
  border-bottom: 1px solid #dee2e6;
  font-weight: bold;
  color: #495057;
  white-space: nowrap;
}

.sidebar-header i {
  width: 20px;
  text-align: center;
}

.help-sidebar.collapsed .sidebar-title {
  display: none;
}

.sidebar-nav {
  list-style: none;
  padding: 0;
  margin: 0;
  flex-grow: 1;
}

.sidebar-nav li {
  border-bottom: 1px solid #e9ecef;
}

.sidebar-nav a {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: #495057;
  text-decoration: none;
  white-space: nowrap;
  transition: background-color 0.2s;
}

.sidebar-nav a:hover {
  background-color: #e9ecef;
  color: #007bff;
}

.sidebar-nav a i {
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.sidebar-nav a span {
  margin-left: 0.75rem;
}

.help-sidebar.collapsed .sidebar-nav a span {
  display: none;
}

/* Back to Top Button */
.back-to-top {
  position: fixed;
  bottom: 80px;
  right: 30px;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background-color: #6c757d;
  color: white;
  border: none;
  cursor: pointer;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.back-to-top:hover {
  background-color: #5a6268;
}

.back-to-top.visible {
  opacity: 1;
  visibility: visible;
}

/* Content Area */
.help-content {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  font-size: 0.95rem;
  position: relative;
}

.help-section h2 {
  color: #343a40;
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.help-section h3 {
  color: #495057;
  font-size: 1.2rem;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.help-section h4 {
  color: #6c757d;
  font-size: 1rem;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

.help-section code {
  background-color: #f8f9fa;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
}

.help-section ul, .help-section ol {
  margin-bottom: 1rem;
}

.help-section li {
  margin-bottom: 0.25rem;
}

.help-section p {
  margin-bottom: 0.75rem;
}

.help-section .table {
  margin-top: 0.5rem;
  margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
  .help-sidebar {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  }
  
  .help-sidebar.collapsed {
    width: 40px;
    min-width: 40px;
    max-width: 40px;
  }
  
  .help-content {
    margin-left: 50px;
  }
}
</style>
