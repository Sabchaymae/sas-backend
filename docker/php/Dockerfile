# ── Service image: inherits from pre-built oriotel-php-base ──────────────────
# Run first: docker build -f docker/php/Dockerfile.base -t oriotel-php-base .
FROM oriotel-php-base:latest

# Copy custom PHP-FPM pool config (enables /ping healthcheck)
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-custom-pool.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

# Healthcheck: probe PHP-FPM ping endpoint
HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

EXPOSE 9000

CMD ["/usr/local/sbin/php-fpm"]
