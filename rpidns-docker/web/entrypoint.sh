#!/bin/bash
# RpiDNS Web Container Entrypoint
# Initializes SSL certificates, starts rsyslog, cron, php-fpm, and openresty
# Note: Frontend assets are pre-built during Docker image creation (multi-stage build)
# and served directly as static files - no runtime build required

set -e

echo "Starting RpiDNS Web container..."
echo "Hostname: ${RPIDNS_HOSTNAME}"
echo "Logging Mode: ${RPIDNS_LOGGING}"

# Verify frontend assets exist (built during Docker image creation)
FRONTEND_DIST="/opt/rpidns/www/rpi_admin/dist"
if [ ! -d "${FRONTEND_DIST}" ] || [ -z "$(ls -A ${FRONTEND_DIST} 2>/dev/null)" ]; then
    echo "WARNING: Frontend assets not found at ${FRONTEND_DIST}"
    echo "The frontend should be pre-built during Docker image creation."
    echo "Please rebuild the Docker image to include frontend assets."
else
    echo "Frontend assets found at ${FRONTEND_DIST}"
fi

# Initialize SSL certificates if not present
SSL_DIR="/opt/rpidns/conf/ssl"
if [ ! -f "${SSL_DIR}/server.key" ] || [ ! -f "${SSL_DIR}/server.crt" ]; then
    echo "Generating self-signed SSL certificate..."
    mkdir -p "${SSL_DIR}"
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "${SSL_DIR}/server.key" \
        -out "${SSL_DIR}/server.crt" \
        -subj "/CN=${RPIDNS_HOSTNAME}/O=RpiDNS/C=US" \
        2>/dev/null
    chown www-data:www-data "${SSL_DIR}/server.key" "${SSL_DIR}/server.crt"
    chmod 600 "${SSL_DIR}/server.key"
    chmod 644 "${SSL_DIR}/server.crt"
    echo "SSL certificate generated successfully"
fi

# Generate CA certificate for dynamic SSL if not present
if [ ! -f "${SSL_DIR}/ca.key" ] || [ ! -f "${SSL_DIR}/ca.crt" ]; then
    echo "Generating CA certificate for dynamic SSL..."
    openssl genrsa -out "${SSL_DIR}/ca.key" 2048 2>/dev/null
    openssl req -x509 -new -nodes -key "${SSL_DIR}/ca.key" \
        -sha256 -days 3650 -out "${SSL_DIR}/ca.crt" \
        -subj "/CN=RpiDNS CA/O=RpiDNS/C=US" \
        2>/dev/null
    chown www-data:www-data "${SSL_DIR}/ca.key" "${SSL_DIR}/ca.crt"
    chmod 600 "${SSL_DIR}/ca.key"
    chmod 644 "${SSL_DIR}/ca.crt"
    echo "CA certificate generated successfully"
fi

# Set up SSL signing certificates for dynamic certificate generation
SSL_SIGN_DIR="/opt/rpidns/conf/ssl_sign"
SSL_CACHE_DIR="/opt/rpidns/conf/ssl_cache"
mkdir -p "${SSL_SIGN_DIR}" "${SSL_CACHE_DIR}"

# Generate CA certificate if not present
if [ ! -f "${SSL_SIGN_DIR}/ioc2rpzCA.crt" ]; then
    echo "Generating CA certificate for SSL signing..."
    openssl genrsa -out "${SSL_SIGN_DIR}/ioc2rpzCA.pkey" 4096 2>/dev/null
    openssl req -x509 -new -nodes -key "${SSL_SIGN_DIR}/ioc2rpzCA.pkey" \
        -sha256 -days 3650 -out "${SSL_SIGN_DIR}/ioc2rpzCA.crt" \
        -subj "/CN=ioc2rpz CA/O=ioc2rpz Community/C=US" \
        2>/dev/null
    echo "CA certificate generated"
fi

# Generate Intermediate certificate if not present
if [ ! -f "${SSL_SIGN_DIR}/ioc2rpzInt.crt" ]; then
    echo "Generating Intermediate certificate for SSL signing..."
    openssl genrsa -out "${SSL_SIGN_DIR}/ioc2rpzInt.pkey" 4096 2>/dev/null
    openssl req -new -key "${SSL_SIGN_DIR}/ioc2rpzInt.pkey" \
        -out "${SSL_SIGN_DIR}/ioc2rpzInt.csr" \
        -subj "/CN=ioc2rpz Intermediate/O=ioc2rpz Community/C=US" \
        2>/dev/null
    openssl x509 -req -in "${SSL_SIGN_DIR}/ioc2rpzInt.csr" \
        -CA "${SSL_SIGN_DIR}/ioc2rpzCA.crt" \
        -CAkey "${SSL_SIGN_DIR}/ioc2rpzCA.pkey" \
        -CAcreateserial -out "${SSL_SIGN_DIR}/ioc2rpzInt.crt" \
        -days 1825 -sha256 \
        2>/dev/null
    rm -f "${SSL_SIGN_DIR}/ioc2rpzInt.csr"
    echo "Intermediate certificate generated"
fi

# Generate fallback certificate if not present
if [ ! -f "${SSL_SIGN_DIR}/ioc2rpz.fallback.crt" ]; then
    echo "Generating fallback certificate..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "${SSL_SIGN_DIR}/ioc2rpz.fallback.pkey" \
        -out "${SSL_SIGN_DIR}/ioc2rpz.fallback.crt" \
        -subj "/CN=rpidns.fallback/O=ioc2rpz Community/C=US" \
        2>/dev/null
    echo "Fallback certificate generated"
fi

# Set permissions on SSL directories - nginx user needs read access
chown -R www-data:www-data "${SSL_SIGN_DIR}" "${SSL_CACHE_DIR}"
chmod 755 "${SSL_SIGN_DIR}"
chmod 777 "${SSL_CACHE_DIR}"
chmod 644 "${SSL_SIGN_DIR}"/*.pkey 2>/dev/null || true
chmod 644 "${SSL_SIGN_DIR}"/*.crt 2>/dev/null || true

# Generate admin password if not set via environment
HTPASSWD_FILE="/opt/rpidns/conf/.htpasswd"
if [ ! -f "${HTPASSWD_FILE}" ]; then
    if [ -n "${RPIDNS_ADMIN_PASSWORD}" ]; then
        echo "Setting admin password from environment..."
        htpasswd -bc "${HTPASSWD_FILE}" admin "${RPIDNS_ADMIN_PASSWORD}" 2>/dev/null
    else
        # Generate random password
        ADMIN_PASS=$(openssl rand -base64 12)
        echo "Generated admin password: ${ADMIN_PASS}"
        echo "Please change this password after first login!"
        htpasswd -bc "${HTPASSWD_FILE}" admin "${ADMIN_PASS}" 2>/dev/null
    fi
    chown www-data:www-data "${HTPASSWD_FILE}"
    chmod 644 "${HTPASSWD_FILE}"
fi

# Ensure directories exist
mkdir -p /run/php
mkdir -p /run/openresty
mkdir -p /opt/rpidns/logs/nginx
mkdir -p /opt/rpidns/www/db

### Init DB
DB_VERSION=0
if [ -f /opt/rpidns/www/db/rpidns.sqlite ]; then
    DB_VERSION=$(sqlite3 /opt/rpidns/www/db/rpidns.sqlite "PRAGMA user_version;" 2>/dev/null || echo "0")
fi

if [ "$DB_VERSION" -gt 0 ]; then
    echo "/opt/rpidns/www/db/rpidns.sqlite exists and initialized (version: $DB_VERSION), skipping DB init."
else
    echo "Init DB"
    chmod 775 /opt/rpidns/www/db
    touch /opt/rpidns/www/db/rpidns.sqlite
    chown www-data:www-data /opt/rpidns/www/db/rpidns.sqlite
    chmod 660 /opt/rpidns/www/db/rpidns.sqlite
    /usr/bin/php /opt/rpidns/scripts/init_db.php
    chmod 664 /opt/rpidns/www/rpisettings.php
    chown www-data:www-data /opt/rpidns/www/rpisettings.php
fi
### End Init DB

# Ensure directories have correct permissions
chown -R www-data:www-data /run/php
chown -R www-data:www-data /run/openresty
chown -R www-data:www-data /opt/rpidns/www
chown -R www-data:www-data /opt/rpidns/logs/nginx

# Configure rsyslog based on logging mode (Requirements: 11.1, 11.2, 11.5, 11.6)
RSYSLOG_CONF="/etc/rsyslog.conf"
if [ "${RPIDNS_LOGGING}" = "forward" ] && [ -n "${RPIDNS_LOGGING_HOST}" ]; then
    echo "Configuring rsyslog to forward logs to ${RPIDNS_LOGGING_HOST}..."
    # Forward mode: send local logs to remote syslog host
    cat > "${RSYSLOG_CONF}" << EOF
# RpiDNS RSyslog Configuration - Forward Mode
# Forwards logs to: ${RPIDNS_LOGGING_HOST}

module(load="imuxsock")

# Forward local4 (bind) logs to remote syslog host
local4.* @@${RPIDNS_LOGGING_HOST}:10514

# Default local logging
*.info;mail.none;authpriv.none;cron.none;local4.none /var/log/messages
authpriv.* /var/log/secure
mail.* -/var/log/maillog
cron.* /var/log/cron
*.emerg :omusrmsg:*
EOF
    echo "Rsyslog configured for forward mode to ${RPIDNS_LOGGING_HOST}:10514"
else
    echo "Configuring rsyslog for local mode (receiving remote logs)..."
    # Local mode: receive logs from remote RpiDNS instances
    cat > "${RSYSLOG_CONF}" << 'EOF'
# RpiDNS RSyslog Configuration - Local Mode
# Receives syslog messages from remote RpiDNS instances

#################
#### MODULES ####
#################

# Provides support for local system logging
module(load="imuxsock")

# Provides TCP syslog reception on port 10514 (Requirement 11.1, 11.2)
module(load="imtcp")
input(type="imtcp" port="10514")

###########################
#### GLOBAL DIRECTIVES ####
###########################

# Use RFC3339 timestamp format
$ActionFileDefaultTemplate RSYSLOG_FileFormat

# Set default permissions for log files
$FileOwner root
$FileGroup adm
$FileCreateMode 0640
$DirCreateMode 0755
$Umask 0022

# Work directory for rsyslog
$WorkDirectory /var/lib/rsyslog

###############
#### RULES ####
###############

# Template for RpiDNS bind query logs with source IP in filename (Requirement 11.3, 11.4)
# RFC3339 timestamp format: 2024-01-15T10:30:45.123456+00:00
template(name="RpiDNSBindLog" type="string"
    string="/opt/rpidns/logs/bind_%fromhost-ip%_queries.log")

template(name="RFC3339Format" type="string"
    string="%timegenerated:::date-rfc3339% %fromhost-ip% %syslogtag%%msg%\n")

# Route bind/named logs to per-source-IP files
if $programname == 'named' or $programname == 'bind' or $syslogfacility-text == 'local4' then {
    action(type="omfile" dynaFile="RpiDNSBindLog" template="RFC3339Format")
    stop
}

# Default rules for local logging
*.info;mail.none;authpriv.none;cron.none    /var/log/messages
authpriv.*                                   /var/log/secure
mail.*                                       -/var/log/maillog
cron.*                                       /var/log/cron
*.emerg                                      :omusrmsg:*
local7.*                                     /var/log/boot.log
EOF
    echo "Rsyslog configured for local mode (listening on port 10514)"
fi

# Start rsyslog
echo "Starting rsyslog..."
rsyslogd

# Start cron daemon
echo "Starting cron daemon..."
crond -b -l 8

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm83 -D

# Wait for PHP-FPM socket to be ready
sleep 1

# Start OpenResty (nginx) in foreground
echo "Starting OpenResty..."
exec /usr/sbin/nginx -g "daemon off;"
