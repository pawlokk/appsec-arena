FROM php:8.2-cli

RUN apt-get update && apt-get install -y libsqlite3-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app
COPY index.php .

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && php -S 0.0.0.0:5000 /app/index.php"]
