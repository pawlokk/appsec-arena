FROM php:8.1-apache

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

RUN a2enmod include && a2enmod rewrite

RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options +Includes\n\
        AddType text/x-server-parsed-html .shtml\n\
    </Directory>\n\
    <Directory /var/www/html/pages>\n\
        Options +Includes\n\
        AllowOverride All\n\
        Require all granted\n\
        AddType text/x-server-parsed-html .shtml\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

COPY index.php /var/www/html/index.php
COPY create.php /var/www/html/create.php
COPY login.php /var/www/html/login.php
RUN mkdir -p /var/www/html/pages && chown www-data:www-data /var/www/html/pages

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV APP_USER=admin

EXPOSE 5000

CMD ["sh", "-c", "APP_PASS=$(openssl rand -hex 12) && export APP_PASS && echo \"$APP_PASS\" > /etc/app_pass && chmod 644 /etc/app_pass && echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 600 /flag_local.txt && chmod 600 /flag_proof.txt && printf '<!DOCTYPE html>\\n<html lang=\"en\">\\n<head><meta charset=\"UTF-8\"><title>Imprint – Site Info</title><style>body{font-family:Arial,sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem;}pre{background:#f4f4f4;padding:1rem;border-radius:4px;}a{color:#4a90e2;}</style></head>\\n<body>\\n<h1>Imprint – Site Info</h1>\\n<p>Welcome to Imprint, a lightweight static site generator.</p>\\n<h2>Server Details</h2>\\n<pre>\\nHostname: <!--#echo var=\"SERVER_NAME\" -->\\nDate: <!--#echo var=\"DATE_LOCAL\" -->\\n</pre>\\n<h2>About</h2>\\n<p>Use the <a href=\"/login.php\">admin panel</a> to create and publish pages.</p>\\n</body>\\n</html>\\n' > /var/www/html/info.shtml && chown www-data:www-data /var/www/html/info.shtml && apache2-foreground & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:80"]
