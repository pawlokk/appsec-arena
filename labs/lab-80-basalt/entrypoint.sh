#!/bin/sh
set -e
echo "$FLAG_LOCAL"  > /flag_local.txt
echo "$FLAG_PROOF"  > /flag_proof.txt
exec node /app/app.js
