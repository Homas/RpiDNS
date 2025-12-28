# RpiDNS Web Container
# OpenResty (nginx with Lua) + PHP-FPM + RSyslog for RpiDNS web UI
# Multi-stage build: Stage 1 builds frontend with Vite, Stage 2 creates final image

#############################
# Stage 1: Build Frontend
#############################
FROM node:20-alpine AS frontend-builder

LABEL maintainer="Vadim Pavlov"
LABEL description="Frontend build stage for RpiDNS"

WORKDIR /build

# Copy frontend source files
COPY rpidns-frontend/package*.json ./

# Install npm dependencies
RUN npm install

# Copy the rest of the frontend source
COPY rpidns-frontend/ ./

# Build production assets with Vite
RUN npm run build

#############################
# Stage 2: Final Image
#############################
FROM alpine:3.21

LABEL maintainer="Vadim Pavlov"
LABEL description="OpenResty web server with PHP-FPM and RSyslog for RpiDNS"

# Enable community repository for openresty
RUN echo "http://dl-cdn.alpinelinux.org/alpine/v3.21/community" >> /etc/apk/repositories

# Install required packages
RUN apk add --no-cache \
    openresty \
    lua-resty-openssl \
    lua-resty-lock \
    php83 \
    php83-fpm \
    php83-sqlite3 \
    php83-openssl \
    php83-session \
    php83-pdo \
    php83-pdo_sqlite \
    php83-json \
    php83-curl \
    rsyslog \
    dcron \
    git \
    openssl \
    sqlite \
    apache2-utils \
    bash \
    curl \
    iproute2 \
    bind-tools \
    && rm -rf /var/cache/apk/*

# Create www-data user/group (matching common web server conventions)
RUN addgroup -g 82 -S www-data 2>/dev/null || true \
    && adduser -u 82 -D -S -G www-data www-data 2>/dev/null || true

# Configure php-fpm to run as www-data
RUN sed -i 's/^user = .*/user = www-data/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/group = www-data/' /etc/php83/php-fpm.d/www.conf

# Create directory structure
RUN mkdir -p /opt/rpidns/www \
    && mkdir -p /opt/rpidns/www/db \
    && mkdir -p /opt/rpidns/www/rpi_admin/dist \
    && mkdir -p /opt/rpidns/conf/ssl \
    && mkdir -p /opt/rpidns/conf/ssl_sign \
    && mkdir -p /opt/rpidns/conf/ssl_cache \
    && mkdir -p /opt/rpidns/logs \
    && mkdir -p /opt/rpidns/scripts \
    && mkdir -p /run/php \
    && mkdir -p /run/openresty \
    && mkdir -p /var/log/rsyslog \
    && ln -s /sbin/ip /bin/ip

# Copy built frontend assets from builder stage
COPY --from=frontend-builder /build/dist /opt/rpidns/www/rpi_admin/dist

# Set ownership
RUN chown -R www-data:www-data /opt/rpidns \
    && chown -R www-data:www-data /run/php \
    && chown -R www-data:www-data /run/openresty

# Copy configuration files
COPY rpidns-docker/web/nginx.conf.template /etc/nginx/nginx.conf
COPY rpidns-docker/web/rsyslog.conf /etc/rsyslog.conf
COPY rpidns-docker/web/crontab /etc/crontabs/root
COPY rpidns-docker/web/entrypoint.sh /entrypoint.sh

# Set permissions
RUN chmod +x /entrypoint.sh \
    && chmod 0600 /etc/crontabs/root

# Create a simple blocked.php for health check
RUN echo '<?php echo "blocked"; ?>' > /opt/rpidns/www/blocked.php \
    && chown www-data:www-data /opt/rpidns/www/blocked.php

# Expose ports
# 80 - HTTP
# 443 - HTTPS
# 10514 - Syslog (for remote RpiDNS log collection)
EXPOSE 80
EXPOSE 443
EXPOSE 10514

# Health check - verify web server is responding
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/blocked.php || exit 1

# Set environment variables
ENV RPIDNS_HOSTNAME="rpidns.local"
ENV RPIDNS_LOGGING="local"
ENV RPIDNS_LOGGING_HOST=""
ENV PHP_FPM_VERSION="83"

ENTRYPOINT ["/entrypoint.sh"]
