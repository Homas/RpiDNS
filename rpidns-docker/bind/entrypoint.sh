#!/bin/bash
# RpiDNS Bind Container Entrypoint
# Initializes zone files and starts named in foreground

set -e

echo "Starting RpiDNS Bind container..."
echo "Hostname: ${RPIDNS_HOSTNAME}"
echo "DNS Type: ${RPIDNS_DNS_TYPE}"
echo "Logging Mode: ${RPIDNS_LOGGING:-local}"

# Initialize zone files if not present
if [ ! -f /var/cache/bind/db.local ]; then
    echo "Initializing local zone file..."
    cp /etc/bind/db.empty.pi /var/cache/bind/db.local 2>/dev/null || true
fi

# Check if named.conf exists, create minimal config if not
if [ ! -f /etc/bind/named.conf ]; then
    echo "Warning: /etc/bind/named.conf not found. Using default configuration."
fi

# Ensure proper ownership on mounted volumes
# These directories need to be writable by the named user
chown -R named:named /var/cache/bind 2>/dev/null || true
chown -R named:named /opt/rpidns/logs 2>/dev/null || true

# Verify permissions on cache directory
if [ -w /var/cache/bind ]; then
    echo "Cache directory is writable"
else
    echo "Warning: Cache directory may not be writable"
fi

# Verify permissions on logs directory
if [ -d /opt/rpidns/logs ]; then
    echo "Logs directory exists"
else
    mkdir -p /opt/rpidns/logs
    chown named:named /opt/rpidns/logs
fi

# Configure syslog forwarding if in forward mode (Requirements: 10.4, 11.6)
if [ "${RPIDNS_LOGGING}" = "forward" ] && [ -n "${RPIDNS_LOGGING_HOST}" ]; then
    echo "Configuring syslog forwarding to ${RPIDNS_LOGGING_HOST}..."
    
    # Create rsyslog configuration for forwarding bind logs
    cat > /etc/rsyslog.conf << EOF
# RpiDNS Bind Syslog Forwarding Configuration
# Forwards bind logs to: ${RPIDNS_LOGGING_HOST}

module(load="imuxsock")

# Forward local4 (bind) logs to remote syslog host
local4.* @@${RPIDNS_LOGGING_HOST}:10514

# Also log locally for debugging
local4.* /opt/rpidns/logs/bind_syslog.log

# Default local logging
*.info;mail.none;authpriv.none;cron.none;local4.none /var/log/messages
EOF

    # Start rsyslog daemon for log forwarding
    echo "Starting rsyslog for log forwarding..."
    rsyslogd
    
    echo "Syslog forwarding configured to ${RPIDNS_LOGGING_HOST}:10514"
fi

echo "Starting named in foreground..."

# Start named in foreground with logging to stdout
# -g runs in foreground and logs to stderr
# -u named ensures we run as named user
exec /usr/sbin/named -g -u named -c /etc/bind/named.conf
