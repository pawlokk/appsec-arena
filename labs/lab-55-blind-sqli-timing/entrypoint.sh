#!/bin/bash
set -e

# Write flags to filesystem
echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt
chmod 644 /flag_local.txt /flag_proof.txt

# Start PostgreSQL
service postgresql start

# Wait for postgres to be ready
until pg_isready -U postgres; do
    sleep 0.5
done

# Create DB user and database if they don't exist
su -c "psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='arena_user'\" | grep -q 1 || psql -c \"CREATE USER arena_user WITH PASSWORD 'arena_pass'\"" postgres
su -c "psql -tc \"SELECT 1 FROM pg_database WHERE datname='arena_db'\" | grep -q 1 || createdb -O arena_user arena_db" postgres
su -c "psql -d arena_db -f /app/init.sql" postgres

# Seed admin password from environment variable (never hardcoded)
ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 20 | head -n 1)}"
su -c "psql -d arena_db -c \"INSERT INTO users (username, password) VALUES ('admin', '$ADMIN_PASSWORD') ON CONFLICT (username) DO UPDATE SET password=EXCLUDED.password\"" postgres
su -c "psql -d arena_db -c \"GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO arena_user; GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO arena_user;\"" postgres

# Start the Flask application
exec python /app/app.py
