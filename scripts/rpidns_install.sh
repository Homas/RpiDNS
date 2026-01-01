#!/bin/bash
###
### 2025-12-28
### Updated for Vite-based frontend builds
### Most stuff should be moved to Dockerfile or entrypoint.sh (web). The entrypoint.sh should validate if DB exists
### This script builds the frontend with Vite and sets up the local environment
###

SYSUSER=$(who am i | awk '{print $1}')
SUDO_USER=${SUDO_USER:-$SYSUSER}

# Build mode: "production" (default) or "development"
# Development mode includes source maps and unminified code for debugging
# Set via environment variable: RPIDNS_BUILD_MODE=development
RPIDNS_BUILD_MODE=${RPIDNS_BUILD_MODE:-production}

# Function to find npm in common locations
find_npm() {
    # Check common npm locations
    for NPM_PATH in /usr/bin/npm /usr/local/bin/npm /opt/nodejs/bin/npm; do
        if [ -x "$NPM_PATH" ]; then
            echo "$NPM_PATH"
            return 0
        fi
    done
    
    # Try command -v as fallback
    if command -v npm >/dev/null 2>&1; then
        command -v npm
        return 0
    fi
    
    return 1
}

# Function to check if Node.js is installed
check_nodejs() {
    # Check common node locations
    for NODE_PATH in /usr/bin/node /usr/local/bin/node /opt/nodejs/bin/node; do
        if [ -x "$NODE_PATH" ]; then
            NODE_VERSION=$($NODE_PATH -v)
            echo "Node.js ${NODE_VERSION} is installed at $NODE_PATH"
            return 0
        fi
    done
    
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node -v)
        echo "Node.js ${NODE_VERSION} is installed"
        return 0
    fi
    
    return 1
}

# Function to install Node.js
install_nodejs() {
    echo "Installing Node.js..."
    # Install Node.js via NodeSource repository (LTS version)
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get -q -y install nodejs
    
    # Refresh PATH to include newly installed node/npm
    export PATH="/usr/bin:/usr/local/bin:/opt/nodejs/bin:$PATH"
    hash -r 2>/dev/null || true
    
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
    
    # Ensure npm is in PATH
    export PATH="/usr/bin:/usr/local/bin:/opt/nodejs/bin:$PATH"
    
    # Find npm location using our function
    NPM_CMD=$(find_npm)
    
    # If npm not found, try to install Node.js
    if [ -z "$NPM_CMD" ] || [ ! -x "$NPM_CMD" ]; then
        echo "npm not found, attempting to install Node.js..."
        install_nodejs
        
        # Try finding npm again after installation
        NPM_CMD=$(find_npm)
        if [ -z "$NPM_CMD" ] || [ ! -x "$NPM_CMD" ]; then
            echo "ERROR: npm still not found after Node.js installation."
            echo "Searched locations: /usr/bin/npm, /usr/local/bin/npm, /opt/nodejs/bin/npm"
            echo "Current PATH: $PATH"
            exit 1
        fi
    fi
    
    echo "Using npm at: $NPM_CMD"
    
    # Clean node_modules to ensure fresh install
    echo "Cleaning node_modules..."
    rm -rf node_modules package-lock.json
    
    # Install npm dependencies
    echo "Installing npm dependencies..."
    $NPM_CMD install --no-fund --no-audit
    
    if [ $? -ne 0 ]; then
        echo "ERROR: npm install failed"
        exit 1
    fi
    
    # Build production assets
    echo "Building frontend assets (mode: $RPIDNS_BUILD_MODE)..."
    if [ "$RPIDNS_BUILD_MODE" = "development" ]; then
        # Development build with source maps for debugging
        $NPM_CMD run build -- --mode development --sourcemap
    else
        # Production build (minified, no source maps)
        $NPM_CMD run build
    fi
    
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
    echo "Installing system dependencies..."
    apt-get update
    apt-get -q -y install php-fpm sqlite3 php-sqlite3 curl ca-certificates gnupg file
    
    # Always install Node.js (required for frontend build)
    echo "Installing Node.js..."
    # Add NodeSource repository for LTS version
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --batch --yes --dearmor -o /etc/apt/keyrings/nodesource.gpg
    NODE_MAJOR=20
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list
    apt-get update
    apt-get -q -y install nodejs
    
    # Verify Node.js installation
    export PATH="/usr/bin:/usr/local/bin:$PATH"
    if check_nodejs; then
        echo "Node.js installation verified"
    else
        echo "WARNING: Node.js installation could not be verified"
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

    # Configure rndc for BIND management
    echo "Configuring rndc for BIND management..."
    BIND_CONF_DIR="/etc/bind"
    
    # Generate rndc key if not present
    if [ ! -f "$BIND_CONF_DIR/rndc.key" ]; then
        echo "Generating rndc key..."
        rndc-confgen -a -k rndc-key -c "$BIND_CONF_DIR/rndc.key"
        chown root:bind "$BIND_CONF_DIR/rndc.key"
        chmod 640 "$BIND_CONF_DIR/rndc.key"
    fi
    
    # Create rndc.conf if not present
    if [ ! -f "$BIND_CONF_DIR/rndc.conf" ]; then
        echo "Creating rndc.conf..."
        cat > "$BIND_CONF_DIR/rndc.conf" << EOF
// rndc configuration for RpiDNS
options {
    default-key "rndc-key";
    default-server 127.0.0.1;
    default-port 953;
};

include "$BIND_CONF_DIR/rndc.key";
EOF
        chown root:bind "$BIND_CONF_DIR/rndc.conf"
        chmod 640 "$BIND_CONF_DIR/rndc.conf"
    fi
    
    # Add rndc controls to named.conf.options if not present
    NAMED_CONF="$BIND_CONF_DIR/named.conf.options"
    if [ -f "$NAMED_CONF" ]; then
        if ! grep -q "include.*rndc.key" "$NAMED_CONF"; then
            echo "Adding rndc configuration to named.conf.options..."
            # Prepend rndc include and controls to the file
            TEMP_CONF=$(mktemp)
            cat > "$TEMP_CONF" << EOF
// Include rndc key for remote control
include "$BIND_CONF_DIR/rndc.key";

// Controls for rndc
controls {
    inet 127.0.0.1 port 953 allow { 127.0.0.1; } keys { "rndc-key"; };
};

EOF
            cat "$NAMED_CONF" >> "$TEMP_CONF"
            mv "$TEMP_CONF" "$NAMED_CONF"
            chown root:bind "$NAMED_CONF"
            chmod 644 "$NAMED_CONF"
        fi
    fi
    
    # Allow www-data to use rndc
    if [ -f "$BIND_CONF_DIR/rndc.key" ]; then
        chown root:bind "$BIND_CONF_DIR/rndc.key"
        chmod 640 "$BIND_CONF_DIR/rndc.key"
    fi
    
    echo "rndc configuration complete"

    # Build frontend with Vite (replaces individual library downloads)
    # All frontend dependencies (Vue, Bootstrap-Vue, Axios, ApexCharts, FontAwesome)
    # are now bundled via npm and Vite
    # Note: For container deployments, frontend is built in Dockerfile
    build_frontend
else
    echo "Skipping local DB and cron setup in container's env"
    echo "Frontend assets are pre-built in Docker image"
    echo "Fixing owner for named.conf"
    chown 82:82 /opt/rpidns/config/bind/named.conf
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

