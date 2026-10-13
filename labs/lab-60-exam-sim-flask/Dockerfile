FROM python:3.11-slim

# Install PostgreSQL client libraries and socat
RUN apt-get update && apt-get install -y \
    postgresql postgresql-contrib \
    libpq-dev \
    socat \
    netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .
COPY init.sql .

# Flag placeholders — override at runtime
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV ADMIN_PASSWORD=""
ENV SECRET_KEY="titan-secret-do-not-share-xK9m"
ENV DATABASE_URL="dbname=titan user=titan password=titanpass host=localhost"

ENV PGDATA=/var/lib/postgresql/data
ENV POSTGRES_DB=titan
ENV POSTGRES_USER=titan
ENV POSTGRES_PASSWORD=titanpass

EXPOSE 5000

CMD ["sh", "-c", "\
  echo 'FLAG_PROOF content: '\"$FLAG_PROOF\" > /flag_proof.txt && \
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  chmod 644 /flag_proof.txt /flag_local.txt && \
  service postgresql start && \
  sleep 4 && \
  su postgres -c \"psql -c \\\"CREATE ROLE titan WITH LOGIN SUPERUSER PASSWORD 'titanpass';\\\" || true\" && \
  su postgres -c \"createdb -O titan titan || true\" && \
  ADMIN_HASH=$(python3 -c \"from werkzeug.security import generate_password_hash; print(generate_password_hash('$ADMIN_PASSWORD'))\") && \
  sed \"s|PLACEHOLDER_ADMIN_HASH|$ADMIN_HASH|g\" /app/init.sql | su postgres -c \"psql -d titan\" && \
  su postgres -c \"psql -d titan -c \\\"UPDATE users SET password='$ADMIN_HASH' WHERE username='admin';\\\"\" && \
  python3 /app/app.py \
"]
