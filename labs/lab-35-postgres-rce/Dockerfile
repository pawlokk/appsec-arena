FROM python:3.11-slim

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASSWORD=ARENA_ADMIN_placeholder
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    postgresql \
    postgresql-client \
    libpq-dev \
    gcc \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .
COPY init.sql .
COPY setup_db.sh /app/setup_db.sh
RUN chmod +x /app/setup_db.sh

RUN PG_VERSION=$(ls /etc/postgresql/ | head -1) && \
    sed -i 's/^local\s\+all\s\+all\s\+peer/local all all trust/' /etc/postgresql/$PG_VERSION/main/pg_hba.conf && \
    echo "host all all 127.0.0.1/32 trust" >> /etc/postgresql/$PG_VERSION/main/pg_hba.conf

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /var/lib/postgresql/flag_proof.txt && chmod 644 /flag_local.txt && service postgresql start && sleep 4 && /app/setup_db.sh && python /app/app.py"]
