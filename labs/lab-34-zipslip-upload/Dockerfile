FROM php:8.2-apache

RUN apt-get update && apt-get install -y socat libzip-dev && \
    docker-php-ext-install zip && \
    rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY src/index.php /var/www/html/index.php
COPY src/apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod 755 /var/www/html/uploads

RUN chown -R www-data:www-data /var/www/html

ENV APP_PASSWORD=

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_local.txt && chmod 644 /flag_proof.txt && chown www-data:www-data /flag_local.txt /flag_proof.txt && apache2-foreground & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:80"]
