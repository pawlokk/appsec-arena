FROM php:8.1-cli
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev nmap && \
    docker-php-ext-install pdo pdo_sqlite && \
    rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY index.php .
EXPOSE 5000
CMD ["/bin/sh", "-c", "printf '%s\\n' \"${FLAG_LOCAL}\" > /app/flag_local.txt && printf '%s\\n' \"${FLAG_PROOF}\" > /flag_proof.txt && php -S 0.0.0.0:5000 index.php"]
