FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite 2>/dev/null || true

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache access log with combined format (User-Agent included)
RUN echo 'CustomLog /var/log/apache2/access.log combined' >> /etc/apache2/sites-enabled/000-default.conf && \
    echo 'ErrorLog /dev/stderr' >> /etc/apache2/sites-enabled/000-default.conf && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY index.php .

# Create document library with sample files
RUN mkdir -p /var/www/html/docs && \
    printf 'Welcome to Labyrinth\n====================\nThis portal provides access to internal documentation.\nPlease refer to the FAQ for common questions.\nFor support, contact your system administrator.\n' \
        > /var/www/html/docs/welcome.txt && \
    printf 'Frequently Asked Questions\n==========================\nQ: How do I request access?\nA: Contact your system administrator.\n\nQ: Where are the release notes?\nA: See the changelog document.\n\nQ: How do I reset my password?\nA: Submit a ticket to IT helpdesk.\n' \
        > /var/www/html/docs/faq.txt && \
    printf 'Changelog\n=========\nv2.3.0 - 2026-05-20\n  - Document preview improvements\n  - Updated dependency versions\n\nv2.2.0 - 2026-04-08\n  - Admin panel redesign\n  - Audit log enhancements\n\nv2.1.0 - 2026-03-01\n  - New portal UI\n' \
        > /var/www/html/docs/changelog.txt && \
    printf 'IT Onboarding\n=============\nNew employees should complete onboarding within the first week.\n1. Set up VPN access\n2. Configure MFA\n3. Review the acceptable use policy\n' \
        > /var/www/html/docs/onboarding.txt

# Apache rewrite rule to route all requests through index.php
RUN printf '<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %%{REQUEST_FILENAME} !-f\n  RewriteRule ^ index.php [QSA,L]\n</IfModule>\n' \
    > /var/www/html/.htaccess

# Ensure Apache log directory exists and is writable
RUN mkdir -p /var/log/apache2 && \
    chown -R www-data:www-data /var/log/apache2

EXPOSE 80

# FLAG_LOCAL and FLAG_PROOF are injected at runtime by the platform
CMD sh -c '\
    echo "$FLAG_LOCAL" > /var/www/html/flag_local.txt && \
    chmod 640 /var/www/html/flag_local.txt && \
    chown root:www-data /var/www/html/flag_local.txt && \
    echo "$FLAG_PROOF" > /flag_proof.txt && \
    chmod 440 /flag_proof.txt && \
    chown root:www-data /flag_proof.txt && \
    apache2-foreground'
