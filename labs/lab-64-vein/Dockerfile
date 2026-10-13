FROM python:3.11-slim

RUN apt-get update && apt-get install -y \
    postgresql postgresql-contrib libpq-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py config.py screenshot_service.py init.sql ./
COPY services/ services/
COPY workers/ workers/
COPY config/ config/
COPY migrations/ migrations/

ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV SECRET_KEY="vein-secret-k9x3p"
ENV ADMIN_PASSWORD="docproc2024"
ENV DATABASE_URL="dbname=vein user=vein password=veinpass host=localhost"
ENV BOT_SESSION=""
ENV INTERNAL_API_TOKEN="vein-internal-svc-k9Pw3mXqR7"

EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  service postgresql start && \
  sleep 4 && \
  su postgres -c \"psql -c \\\"CREATE ROLE vein WITH LOGIN PASSWORD 'veinpass';\\\" || true\" && \
  su postgres -c \"createdb -O vein vein || true\" && \
  HASH=$(python3 -c \"from werkzeug.security import generate_password_hash; print(generate_password_hash('analyst123'))\") && \
  sed \"s|PLACEHOLDER_HASH|$HASH|g\" /app/init.sql | su postgres -c \"psql -d vein\" && \
  python3 /app/screenshot_service.py & \
  python3 /app/workers/import_worker.py & \
  python3 /app/workers/report_bot.py & \
  python3 /app/app.py \
"]
