FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    default-mysql-server \
    default-mysql-client \
    socat \
    && docker-php-ext-install mysqli \
    && rm -rf /var/lib/apt/lists/*

ENV FLAG_LOCAL="ARENA{placeholder_local}"
ENV FLAG_PROOF="ARENA{placeholder_proof}"

COPY index.php /var/www/html/index.php
COPY init.sql /init.sql

RUN mkdir -p /var/www/html/reports && chown www-data:www-data /var/www/html/reports

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 5000

CMD ["sh", "-c", "\
    echo \"$FLAG_LOCAL\" > /flag_local.txt && \
    chmod 640 /flag_local.txt && \
    chown root:www-data /flag_local.txt && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 640 /flag_proof.txt && \
    chown root:www-data /flag_proof.txt && \
    service mariadb start 2>/dev/null || service mysql start 2>/dev/null && \
    sleep 3 && \
    mysql < /init.sql && \
    mysql meridian -e \"INSERT INTO users (username, password, role) VALUES ('admin', SHA2('M3ridian!', 256), 'admin')\" && \
    apache2ctl -D FOREGROUND & \
    socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80 \
"]
