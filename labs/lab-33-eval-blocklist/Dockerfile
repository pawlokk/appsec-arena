FROM php:8.2-apache

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASSWORD=changeme

COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_local.txt && chmod 644 /flag_proof.txt && apache2ctl start && socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80"]
