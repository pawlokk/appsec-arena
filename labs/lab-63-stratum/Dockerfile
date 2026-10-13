FROM python:3.11-slim

RUN apt-get update && apt-get install -y \
    postgresql postgresql-contrib libpq-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py init.sql ./
COPY services/ services/
COPY workers/ workers/
COPY config/ config/
COPY migrations/ migrations/

ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV SECRET_KEY="stratum-secret-p4Xw9nQr"
ENV ADMIN_PASSWORD=""
ENV APP_ENV="production"
ENV DATABASE_URL="dbname=stratum user=stratum password=stratumpass host=localhost"

EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  service postgresql start && \
  sleep 4 && \
  su postgres -c \"psql -c \\\"CREATE ROLE stratum WITH LOGIN PASSWORD 'stratumpass';\\\" || true\" && \
  su postgres -c \"createdb -O stratum stratum || true\" && \
  HASH=$(python3 -c \"from werkzeug.security import generate_password_hash; print(generate_password_hash('$ADMIN_PASSWORD'))\") && \
  sed \"s|PLACEHOLDER_HASH|$HASH|g\" /app/init.sql | su postgres -c \"psql -d stratum\" && \
  python3 /app/workers/report_worker.py &\
  python3 /app/app.py \
"]
