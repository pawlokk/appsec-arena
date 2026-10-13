FROM php:8.1-apache

RUN apt-get update && apt-get install -y git libxml2-dev socat \
    && docker-php-ext-install xml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

RUN git clone --depth 1 https://github.com/bmdyy/meme_upload_service.git . \
    && mkdir -p images messages \
    && echo "php_flag engine off" > messages/.htaccess \
    && chown -R www-data:www-data /var/www/html

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

# Apache runs on 80; socat forwards standard internal port 5000 -> 80
RUN cat > /start.sh <<'EOF'
#!/bin/sh
echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt
chmod 640 /flag_local.txt /flag_proof.txt
chown root:www-data /flag_local.txt /flag_proof.txt
apache2-foreground &
sleep 1
exec socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80
EOF
RUN chmod +x /start.sh

EXPOSE 5000

CMD ["/start.sh"]
