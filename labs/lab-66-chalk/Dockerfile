FROM php:8.2-apache

ENV FLAG_LOCAL="ARENA{placeholder_flag_local}" \
    FLAG_PROOF="ARENA{placeholder_flag_proof}" \
    ADMIN_PASS="" \
    DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    default-mysql-server \
    socat \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# MySQL config
COPY mysql-custom.cnf /etc/mysql/conf.d/custom.cnf

# PHP session config
COPY php-session.ini /usr/local/etc/php/conf.d/session.ini

# Application files
COPY config.php   /var/www/html/config.php
COPY index.php    /var/www/html/index.php
COPY login.php    /var/www/html/login.php
COPY logout.php   /var/www/html/logout.php
COPY profile.php  /var/www/html/profile.php
COPY articles.php /var/www/html/articles.php
COPY admin/            /var/www/html/admin/
COPY api/              /var/www/html/api/
COPY cors_middleware.php /var/www/html/cors_middleware.php
COPY workers/          /var/www/html/workers/
COPY includes/         /var/www/html/includes/
COPY migrations/       /var/www/html/migrations/
COPY init.sql          /init.sql

# Apache must be able to write to webroot (restore writes files)
RUN chmod 777 /var/www/html && chown -R www-data:www-data /var/www/html

# Remove default page
RUN rm -f /var/www/html/index.html

EXPOSE 5000

CMD ["sh", "-c", "\
    echo \"$FLAG_LOCAL\" > /flag_local.txt && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 644 /flag_local.txt && chmod 600 /flag_proof.txt && \
    ADMIN_PASS=${ADMIN_PASS:-$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 20 | head -n 1)} && \
    export ADMIN_PASS && \
    ADMIN_HASH=$(php -r 'echo password_hash(getenv(\"ADMIN_PASS\"), PASSWORD_BCRYPT);') && \
    sed \"s|ADMIN_PASS_PLACEHOLDER|$ADMIN_HASH|g\" /init.sql > /tmp/init_runtime.sql && \
    service mariadb start && \
    sleep 4 && \
    mysql -u root < /tmp/init_runtime.sql && \
    rm -f /tmp/init_runtime.sql && \
    apache2ctl start && \
    socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80 \
"]
