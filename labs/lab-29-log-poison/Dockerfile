FROM php:8.2-apache

# Install socat for port forwarding from 80 to 5000
RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy application files
COPY index.php /var/www/html/index.php
COPY view.php /var/www/html/view.php
COPY docs/ /var/www/html/docs/

# Copy Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Ensure Apache log directory exists and is writable by www-data
RUN mkdir -p /var/log/apache2 && \
    touch /var/log/apache2/access.log && \
    chown -R www-data:www-data /var/log/apache2

# Set flag env vars (overridden at runtime)
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

# Write flags from env vars at startup, then start Apache forwarded to 5000
CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && chmod 600 /flag_local.txt && chown root:root /flag_local.txt && echo \"$FLAG_PROOF\" > /root/flag_proof.txt && chmod 400 /root/flag_proof.txt && chown root:root /root/flag_proof.txt && printf '#!/bin/sh\ncat /root/flag_proof.txt\n' > /readflag && chown root:root /readflag && chmod 4755 /readflag && apache2-foreground & sleep 1 && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:80"]
