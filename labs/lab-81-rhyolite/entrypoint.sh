#!/bin/bash
set -e

echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt

# MariaDB
service mariadb start
sleep 2
until mysqladmin ping --silent; do sleep 1; done

mysql -u root <<'SQL'
CREATE USER IF NOT EXISTS 'mailuser'@'127.0.0.1' IDENTIFIED BY 'mailpass';
GRANT ALL ON maildb.* TO 'mailuser'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

mysql -u root < /init.sql

# Apache
a2enmod rewrite 2>/dev/null || true
for v in php8.2 php8.1 php8.0 php; do a2enmod $v 2>/dev/null && break; done || true
cp /apache.conf /etc/apache2/sites-available/000-default.conf
# Remove default port 80 listener, use only 5000
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf 2>/dev/null || true
service apache2 start

# Ensure www/tmp writable
chmod 777 /var/www/html/tmp
chmod 777 /var/www/html/plugins

# Node bot
cd /app && npm install --silent 2>/dev/null
node /app/bot.js &

# SMTP
pip3 install aiosmtpd mysql-connector-python --quiet 2>/dev/null || true
python3 /smtp_server.py &

wait
