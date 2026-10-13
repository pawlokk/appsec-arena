FROM php:8.2-apache

ENV FLAG_LOCAL="ARENA{placeholder_flag_local}" \
    FLAG_PROOF="ARENA{placeholder_flag_proof}" \
    ADMIN_PASS="" \
    DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    default-mysql-server \
    socat \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# MySQL config
COPY mysql-custom.cnf /etc/mysql/conf.d/custom.cnf

# Application files
COPY config.php      /var/www/html/config.php
COPY index.php       /var/www/html/index.php
COPY login.php       /var/www/html/login.php
COPY logout.php      /var/www/html/logout.php
COPY reset.php       /var/www/html/reset.php
COPY import.php      /var/www/html/import.php
COPY search.php      /var/www/html/search.php
COPY search_api.php  /var/www/html/search_api.php
COPY xml_viewer.php  /var/www/html/xml_viewer.php
COPY admin.php       /var/www/html/admin.php
COPY preview_import.php   /var/www/html/preview_import.php
COPY process_import.php   /var/www/html/process_import.php
COPY session_restore.php  /var/www/html/session_restore.php
COPY storage_health.php   /var/www/html/storage_health.php
COPY .htaccess            /var/www/html/.htaccess
COPY .htaccess.bak        /var/www/html/.htaccess.bak
COPY includes/            /var/www/html/includes/
COPY admin/               /var/www/html/admin/
COPY migrations/          /var/www/html/migrations/
COPY init.sql             /init.sql

# Upload queue directory — outside direct serve path but writable
RUN mkdir -p /var/www/html/uploads_queue && chmod 777 /var/www/html/uploads_queue

# Enable Apache mod_rewrite for .htaccess processing
RUN a2enmod rewrite

# Apache webroot permissions
RUN chmod 777 /var/www/html && chown www-data:www-data /var/www/html

# PHP configuration
RUN echo "request_order = \"GPC\"" > /usr/local/etc/php/conf.d/request_order.ini

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
