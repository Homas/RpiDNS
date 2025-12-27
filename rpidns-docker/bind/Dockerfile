# RpiDNS Bind Container
# ISC Bind9 DNS server with RPZ support
FROM alpine:3.21

LABEL maintainer="Vadim Pavlov"
LABEL description="ISC Bind9 DNS server with RPZ support for RpiDNS"

# Install bind and required packages
# rsyslog is needed for log forwarding in forward mode (Requirements: 10.4, 11.6)
RUN apk add --no-cache \
    bind \
    bind-tools \
    bash \
    rsyslog \
    && rm -rf /var/cache/apk/*

# Create directory structure for bind
RUN mkdir -p /var/cache/bind \
    && mkdir -p /etc/bind \
    && mkdir -p /opt/rpidns/logs \
    && mkdir -p /var/run/named

# Set ownership for named user (bind runs as 'named' on Alpine)
RUN chown -R named:named /var/cache/bind \
    && chown -R named:named /var/run/named \
    && chown -R named:named /opt/rpidns/logs

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Copy empty zone template
COPY db.empty.pi /etc/bind/db.empty.pi

# Copy default named.conf (will be overridden by mounted config)
COPY named.conf /etc/bind/named.conf
RUN chown named:named /etc/bind/named.conf

# Expose DNS ports (TCP and UDP)
EXPOSE 53/tcp
EXPOSE 53/udp

# Health check - verify DNS service is responding
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD dig @127.0.0.1 localhost +short +time=2 +tries=1 || exit 1

# Set environment variables
ENV RPIDNS_HOSTNAME="rpidns.local"
ENV RPIDNS_DNS_TYPE="primary"
ENV RPIDNS_DNS_IPNET="192.168.0.0/16"
ENV RPIDNS_LOGGING="local"
ENV RPIDNS_LOGGING_HOST=""

# Entrypoint runs as root to fix permissions, then drops to named user
ENTRYPOINT ["/entrypoint.sh"]
