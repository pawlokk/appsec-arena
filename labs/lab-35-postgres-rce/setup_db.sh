#!/bin/sh
set -e

# Create arena superuser if not exists
su -c "psql -U postgres -c \"CREATE USER arena WITH SUPERUSER;\"" postgres 2>/dev/null || true

# Create analytics database if not exists
su -c "psql -U postgres -c \"CREATE DATABASE analytics OWNER arena;\"" postgres 2>/dev/null || true

# Initialize schema and seed data
su -c "psql -U arena -d analytics -f /app/init.sql" postgres

echo "Database setup complete."
