FROM php:8.1-apache

RUN apt-get -q update && apt-get install -q -y \
    libpq-dev \
    postgresql \
    postgresql-client \
    git \
    socat \
    cron \
    && docker-php-ext-install pgsql \
    && pecl install xdebug && docker-php-ext-enable xdebug \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "AddType application/x-httpd-php .phar" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

# Copy application source (patched)
COPY app /var/www/html/
COPY init.sql /docker-entrypoint-initdb.d/init.sql

RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/images \
    && chown www-data:www-data /var/www/html/images \
    && mkdir -p /var/www/html/templates_c \
    && chown www-data:www-data /var/www/html/templates_c

# Initialise PostgreSQL data directory as postgres user
USER postgres
RUN /usr/lib/postgresql/*/bin/initdb -D /var/lib/postgresql/data 2>/dev/null || true && \
    echo "host all all 127.0.0.1/32 trust" >> /var/lib/postgresql/data/pg_hba.conf && \
    echo "listen_addresses='127.0.0.1'" >> /var/lib/postgresql/data/postgresql.conf
USER root

EXPOSE 5000

CMD ["sh", "-c", "\
    echo \"$FLAG_LOCAL\" > /flag_local.txt && \
    chmod 640 /flag_local.txt && chown root:www-data /flag_local.txt && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 640 /flag_proof.txt && chown root:www-data /flag_proof.txt && \
    touch /var/log/postgresql.log && chown postgres /var/log/postgresql.log && \
    su -c '/usr/lib/postgresql/*/bin/pg_ctl -D /var/lib/postgresql/data -l /var/log/postgresql.log start' postgres && \
    sleep 3 && \
    su -c 'psql -c \"CREATE DATABASE tudo;\"' postgres 2>/dev/null || true && \
    su -c 'psql -d tudo -f /docker-entrypoint-initdb.d/init.sql' postgres 2>/dev/null || true && \
    apache2ctl start 2>/dev/null || true && \
    exec socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80 \
"]
