FROM php:8.1-apache

RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev socat \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY index.php db.php init.php ./

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
# Intentional demo credentials — shown openly on the login page as lab starting points.
ENV ALICE_PASS=alicepass
ENV BOB_PASS=bobpass

RUN cat > /start.sh <<'EOF'
#!/bin/sh
echo "$FLAG_LOCAL" > /var/www/html/flag_local.txt
chown www-data:www-data /var/www/html/flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt
chmod 644 /flag_proof.txt
# Initialise DB
php /var/www/html/init.php
apache2-foreground &
sleep 1
exec socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80
EOF
RUN chmod +x /start.sh

EXPOSE 5000

CMD ["/start.sh"]
