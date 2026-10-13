#!/bin/sh
set -e

echo "$FLAG_LOCAL" > /flag_local.txt
echo "$FLAG_PROOF" > /flag_proof.txt

su -s /bin/sh postgres -c "/usr/lib/postgresql/15/bin/pg_ctl start -D /var/lib/postgresql/data -l /tmp/pg.log -w"

until su -s /bin/sh postgres -c "pg_isready -h 127.0.0.1 -p 5432" > /dev/null 2>&1; do
    sleep 0.2
done

su -s /bin/sh postgres -c "createdb warehouse 2>/dev/null || true"
su -s /bin/sh postgres -c "psql -h 127.0.0.1 -d warehouse -f /app/init.sql"

FLAG_ESCAPED=$(echo "$FLAG_LOCAL" | sed 's/'\''/'"'"''"'"''"'"'/g')
su -s /bin/sh postgres -c "psql -h 127.0.0.1 -d warehouse -c \"UPDATE flags SET flag_value = '$FLAG_ESCAPED' WHERE flag_name = 'local';\""

exec node /app/app.js
