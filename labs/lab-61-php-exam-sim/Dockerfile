FROM php:8.2-apache

ENV FLAG_LOCAL="ARENA{placeholder_flag_local}" \
    FLAG_PROOF="ARENA{placeholder_flag_proof}" \
    ADMIN_PASSWORD="" \
    DEBIAN_FRONTEND=noninteractive

# Install MySQL server, socat, and PHP MySQL extension
RUN apt-get update && apt-get install -y \
    default-mysql-server \
    socat \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application source
COPY index.php /var/www/html/index.php
COPY includes/  /var/www/html/includes/
COPY classes/   /var/www/html/classes/
COPY init.sql   /init.sql
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Ensure uploads directory exists with correct permissions
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads

# Remove default Apache index
RUN rm -f /var/www/html/index.html

EXPOSE 5000

CMD ["sh", "-c", "\
    echo 'Writing flags...' && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_proof.txt && \
    echo 'Starting MySQL...' && \
    service mariadb start && \
    sleep 3 && \
    echo 'Initialising database...' && \
    mysql -u root < /init.sql && \
    echo 'Updating admin password hash...' && \
    ADMIN_HASH=$(php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_BCRYPT);') && \
    mysql -u root solvent -e \"UPDATE users SET password_hash='$ADMIN_HASH' WHERE email='admin@solvent.lab'\" && \
    echo 'Starting Apache on port 80...' && \
    apache2ctl start && \
    echo 'Starting socat on port 5000...' && \
    socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:80 \
"]
