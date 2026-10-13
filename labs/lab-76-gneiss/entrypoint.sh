#!/bin/sh
echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt
ln -sf /flag_local.txt /flag_local
ln -sf /flag_proof.txt /flag_proof
mkdir -p /var/app/data
exec php -S 0.0.0.0:${PORT:-5000} -t /app/www
