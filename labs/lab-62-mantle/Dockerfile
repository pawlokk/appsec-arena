FROM python:3.11-slim

RUN apt-get update && apt-get install -y \
    postgresql postgresql-contrib libpq-dev libmagic1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py init.sql ./

ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV SECRET_KEY="mantle-secret-xK9m2p"
ENV ADMIN_PASSWORD=""
ENV DATABASE_URL="dbname=mantle user=mantle password=mantlepass host=localhost"

EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  service postgresql start && \
  sleep 4 && \
  su postgres -c \"psql -c \\\"CREATE ROLE mantle WITH LOGIN PASSWORD 'mantlepass';\\\" || true\" && \
  su postgres -c \"createdb -O mantle mantle || true\" && \
  HASH=$(python3 -c \"from werkzeug.security import generate_password_hash; print(generate_password_hash('$ADMIN_PASSWORD'))\") && \
  sed \"s|PLACEHOLDER_HASH|$HASH|g\" /app/init.sql | su postgres -c \"psql -d mantle\" && \
  python3 /app/app.py \
"]
