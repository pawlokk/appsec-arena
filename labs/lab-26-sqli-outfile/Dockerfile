FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
    default-mysql-server \
    socat \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY init.sql /docker-entrypoint-initdb.d/init.sql
COPY mysql-custom.cnf /etc/mysql/conf.d/custom.cnf
COPY index.php /var/www/html/index.php
COPY search.php /var/www/html/search.php
COPY admin.php /var/www/html/admin.php

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && mkdir -p /root && echo \"$FLAG_PROOF\" > /root/flag_proof.txt && chown root:root /flag_local.txt && chmod 644 /flag_local.txt && chown root:root /root/flag_proof.txt && chmod 600 /root/flag_proof.txt && chmod 777 /var/www/html && ADMIN_PASS=${ADMIN_PASS:-$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 20 | head -n 1)} && export ADMIN_PASS && sed \"s|ADMIN_PASS_PLACEHOLDER|$ADMIN_PASS|g\" /docker-entrypoint-initdb.d/init.sql > /tmp/init_runtime.sql && service mariadb start && sleep 4 && mysql -u root < /tmp/init_runtime.sql && rm -f /tmp/init_runtime.sql && apache2ctl start && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:80"]
