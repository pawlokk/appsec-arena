#!/bin/sh
set -e
echo "$FLAG_PROOF" > /flag_proof.txt
echo "$FLAG_LOCAL" > /flag_local.txt
exec node /app/app.js
