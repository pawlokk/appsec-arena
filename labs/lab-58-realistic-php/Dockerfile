FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    libxml2-dev \
    socat \
    curl \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN a2enmod rewrite

ENV FLAG_LOCAL="ARENA{placeholder_local}"
ENV FLAG_PROOF="ARENA{placeholder_proof}"
ENV ADMIN_PASSWORD=""
ENV ALICE_PASSWORD="Pr1sm@lice!"

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-interaction --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 5000

CMD ["sh", "-c", "\
    echo \"$FLAG_LOCAL\" > /flag_local.txt && \
    chmod 640 /flag_local.txt && \
    chown root:www-data /flag_local.txt && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 644 /flag_proof.txt && \
    php -S 127.0.0.1:8888 /var/www/html/config_service.php & \
    php /var/www/html/init_db.php && \
    apache2ctl -D FOREGROUND & \
    socat TCP-LISTEN:5000,fork TCP:127.0.0.1:80 \
"]
