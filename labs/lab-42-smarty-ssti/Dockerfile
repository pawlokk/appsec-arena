FROM php:8.1-apache

RUN apt-get update && apt-get install -y socat curl unzip && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json /var/www/html/composer.json
RUN cd /var/www/html && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-security-blocking

COPY index.php /var/www/html/index.php

RUN mkdir -p /tmp/smarty_templates /tmp/smarty_compile /tmp/smarty_cache /tmp/smarty_config \
    && chmod 777 /tmp/smarty_templates /tmp/smarty_compile /tmp/smarty_cache /tmp/smarty_config

RUN echo "FrontendPage index.php" > /dev/null \
    && a2enmod rewrite \
    && printf '<VirtualHost *:80>\n    DocumentRoot /var/www/html\n    DirectoryIndex index.php\n    PassEnv ADMIN_USER ADMIN_PASS\n    <Directory /var/www/html>\n        AllowOverride All\n        Require all granted\n        FallbackResource /index.php\n    </Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && apache2-foreground & socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80"]
