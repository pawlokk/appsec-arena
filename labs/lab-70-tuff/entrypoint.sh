#!/bin/sh
set -e

echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt
chmod 644 /flag_local.txt /flag_proof.txt

printf 'DB_HOST=127.0.0.1\nDB_PORT=5432\nDB_NAME=tuff\nDB_USER=tuff\nDB_PASS=tuffpass\n' > /app/config/tuff.conf
chmod 644 /app/config/tuff.conf

service postgresql start
sleep 4

su postgres -c "psql -c \"CREATE ROLE tuff WITH LOGIN PASSWORD 'tuffpass';\" 2>/dev/null || true"
su postgres -c "createdb -O tuff tuff 2>/dev/null || true"
su postgres -c "psql -d tuff -c \"CREATE TABLE IF NOT EXISTS users (id TEXT PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, is_admin BOOLEAN NOT NULL DEFAULT false, display_name TEXT, bio TEXT);\""
su postgres -c "psql -d tuff -c \"CREATE TABLE IF NOT EXISTS review_queue (id SERIAL PRIMARY KEY, submitted_by TEXT, url TEXT NOT NULL, reason TEXT, status TEXT DEFAULT 'pending', created_at TIMESTAMP DEFAULT NOW(), processed_at TIMESTAMP);\""
su postgres -c "psql -d tuff -c \"CREATE TABLE IF NOT EXISTS review_results (id SERIAL PRIMARY KEY, queue_id INT, http_status INT, response_snippet TEXT, reviewed_at TIMESTAMP DEFAULT NOW());\""
su postgres -c "psql -d tuff -c \"CREATE TABLE IF NOT EXISTS activity_log (id SERIAL PRIMARY KEY, action TEXT NOT NULL, actor_id TEXT, detail TEXT, created_at TIMESTAMP DEFAULT NOW());\""
su postgres -c "psql -d tuff -c \"CREATE TABLE IF NOT EXISTS monitor_commands (id SERIAL PRIMARY KEY, name TEXT UNIQUE NOT NULL, command_line TEXT NOT NULL, created_by TEXT, active BOOLEAN DEFAULT TRUE, created_at TIMESTAMP DEFAULT NOW());\""

VIEWER_HASH=$(node -e "const b=require('bcryptjs');process.stdout.write(b.hashSync('viewer123',10))")
su postgres -c "psql -d tuff" <<SQL
INSERT INTO users (id, username, password_hash, is_admin)
VALUES ('00000000-0000-0000-0000-000000000001', 'viewer', '${VIEWER_HASH}', false)
ON CONFLICT DO NOTHING;
INSERT INTO monitor_commands (name, command_line, created_by)
VALUES
  ('check_host', './check_ping -H {host} -p 1 -w 3000,80% -c 5000,100%', 'admin'),
  ('check_web',  './check_http -I {host} -p {port}', 'admin')
ON CONFLICT DO NOTHING;
INSERT INTO activity_log (action, actor_id, detail)
VALUES ('system_start', NULL, 'application initialized');
GRANT ALL ON ALL TABLES IN SCHEMA public TO tuff;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO tuff;
SQL

node workers/link_scanner.js >> /tmp/tuff_link_scanner.log 2>&1 &
exec node app.js
