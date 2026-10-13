FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends unzip curl && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY index.php ./

EXPOSE 5000

CMD ["/bin/sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && chmod 640 /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && php -S 0.0.0.0:5000 index.php"]
