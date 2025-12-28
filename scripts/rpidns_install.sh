#!/bin/sh
###
### 2025-12-28
### Updated for Vite-based frontend builds
### Most stuff should be moved to Dockerfile or entrypoint.sh (web). The entrypoint.sh should validate if DB exists
### This script builds the frontend with Vite and sets up the local environment
###

SYSUSER=`who am i | awk '{print $1}'`
SUDO_USER=${SUDO_USER:-$SYSUSER}

# Function to check if Node.js is installed
check_nodejs() {
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node -v)
        echo "Node.js ${NODE_VERSION} is installed"
        return 0
    else
        return 1
    fi
}

# Function to install Node.js
install_nodejs() {
    echo "Installing Node.js..."
    # Install Node.js via NodeSource repository (LTS version)
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get -q -y install nodejs
    
    if check_nodejs; then
        echo "Node.js installed successfully"
    else
        echo "ERROR: Failed to install Node.js"
        exit 1
    fi
}

# Function to build frontend with Vite
build_frontend() {
    echo "Building frontend with Vite..."
    FRONTEND_DIR="/opt/rpidns/rpidns-frontend"
    
    if [ ! -d "$FRONTEND_DIR" ]; then
        echo "ERROR: Frontend directory not found at $FRONTEND_DIR"
        exit 1
    fi
    
    cd "$FRONTEND_DIR"
    
    # Install npm dependencies
    echo "Installing npm dependencies..."
    npm install
    
    if [ $? -ne 0 ]; then
        echo "ERROR: npm install failed"
        exit 1
    fi
    
    # Build production assets
    echo "Building production assets..."
    npm run build
    
    if [ $? -ne 0 ]; then
        echo "ERROR: npm run build failed"
        exit 1
    fi
    
    # Copy built assets to www directory
    echo "Copying built assets to /opt/rpidns/www/rpi_admin/dist..."
    rm -rf /opt/rpidns/www/rpi_admin/dist
    cp -r dist /opt/rpidns/www/rpi_admin/dist
    
    # Set correct ownership
    chown -R $SUDO_USER:www-data /opt/rpidns/www/rpi_admin/dist
    
    echo "Frontend build completed successfully"
}

if [ -z "$RPIDNS_INSTALL_TYPE" ]; then
    # Non-container installation
    apt-get -q -y install php-fpm sqlite3 php-sqlite3 curl
    
    # Install Node.js if not present
    if ! check_nodejs; then
        install_nodejs
    fi
    
    # Init DB
    mkdir -p /opt/rpidns/www/db
    chown $SUDO_USER:www-data /opt/rpidns/www/db
    chmod 775 /opt/rpidns/www/db

    touch /opt/rpidns/www/db/rpidns.sqlite
    chown $SUDO_USER:www-data /opt/rpidns/www/db/rpidns.sqlite
    chmod 660 /opt/rpidns/www/db/rpidns.sqlite
    /usr/bin/php /opt/rpidns/scripts/init_db.php

    # Install crontabs
    ### 2025-12-27 Crontabs are moved to the docker
    crontab -l > /tmp/$SUDO_USER
    cat >> /tmp/$SUDO_USER  << EOF
##Non-root cron scripts
* * * * * 	/usr/bin/php /opt/rpidns/scripts/parse_bind_logs.php
42 2 * * *	sleep 25;/usr/bin/php /opt/rpidns/scripts/clean_db.php
42 3 * * *	sleep 25;/usr/bin/sqlite3 /opt/rpidns/www/db/rpidns.sqlite 'VACUUM;'
EOF
    cat /tmp/$SUDO_USER | crontab -u $SUDO_USER -
    rm -rf /tmp/$SUDO_USER
    chmod 664 /opt/rpidns/www/rpisettings.php
    chown $SUDO_USER:www-data /opt/rpidns/www/rpisettings.php
    ### 2020-07-07 Manage bind from apache
    adduser www-data bind
    adduser $SUDO_USER bind
    adduser $SUDO_USER www-data
    sed -i 's/\$bind_host="bind"/\$bind_host="127.0.0.1"/' /opt/rpidns/www/rpidns_vars.php

    # Build frontend with Vite (replaces individual library downloads)
    # All frontend dependencies (Vue, Bootstrap-Vue, Axios, ApexCharts, FontAwesome)
    # are now bundled via npm and Vite
    # Note: For container deployments, frontend is built in Dockerfile
    build_frontend
else
    echo "Skipping local DB and cron setup in container's env"
    echo "Frontend assets are pre-built in Docker image"
    # Pull named root hints
    curl https://www.internic.net/domain/named.root -o /opt/rpidns/config/bind/named.root
fi

# Install MAC DB (needed for both container and non-container)
curl https://gitlab.com/wireshark/wireshark/raw/master/manuf -o /opt/rpidns/scripts/mac.db

#/etc/php/7.3/fpm/php.ini
#disable_functions
#service php7.3-fpm restart

#pi@pi-dev:/opt/rpidns/www $ cat /etc/php/7.3/fpm/pool.d/www.conf |grep limit_extension
#;security.limit_extensions = .php .php3 .php4 .php5 .php7
#pi@pi-dev:/opt/rpidns/www $

