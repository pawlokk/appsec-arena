FROM php:8.2-apache

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_USER=admin
ENV ADMIN_PASS=

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY index.php /var/www/html/index.php
COPY classes/ /var/www/html/classes/

RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    DirectoryIndex index.php\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
    </Directory>\n\
    FallbackResource /index.php\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && apache2ctl start && socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80"]
