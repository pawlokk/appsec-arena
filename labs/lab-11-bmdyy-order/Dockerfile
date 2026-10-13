FROM debian:bullseye

ENV DEBIAN_FRONTEND=noninteractive
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

RUN apt-get update && apt-get install -y \
    python3 python3-pip libpq-dev \
    postgresql postgresql-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN pip3 install flask psycopg2-binary

WORKDIR /app
COPY app.py .
COPY templates ./templates
COPY static ./static
COPY .sql ./.sql

EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  chmod 644 /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  chmod 644 /flag_proof.txt && \
  /etc/init.d/postgresql start && \
  sleep 2 && \
  su -c \"psql -c \\\"CREATE DATABASE \\\\\\\"order\\\\\\\";\\\"\" postgres 2>/dev/null || true && \
  su -c \"psql -c \\\"ALTER USER postgres PASSWORD 'postgres';\\\"\" postgres && \
  su -c \"psql -d \\\"order\\\" -f /app/.sql/init.sql\" postgres 2>/dev/null || true && \
  exec python3 /app/app.py \
"]
