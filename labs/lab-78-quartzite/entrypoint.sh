#!/bin/sh
set -e

echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt

service mysql start

until mysqladmin ping --silent 2>/dev/null; do
    sleep 0.2
done

mysql < /init.sql

exec apache2ctl -D FOREGROUND
