#!/bin/sh
set -e

echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt

service postgresql start
sleep 4

su postgres -c "psql -c \"CREATE USER schist WITH PASSWORD 'schistpass';\" 2>/dev/null; exit 0" || true
su postgres -c "psql -c \"CREATE DATABASE schist OWNER schist;\" 2>/dev/null; exit 0" || true
su postgres -c "psql schist -c \"CREATE EXTENSION IF NOT EXISTS pgcrypto;\" 2>/dev/null; exit 0" || true

node /app/migrations/run.js

node /app/app.js
