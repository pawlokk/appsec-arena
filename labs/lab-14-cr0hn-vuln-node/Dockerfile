FROM node:20-bookworm-slim

LABEL maintainer="AppSec Arena wrapper"

ENV DEBIAN_FRONTEND=noninteractive
ENV STAGE=LOCAL
ENV PORT=5000
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

# Install dependencies: git, postgresql, socat, supervisor
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    git \
    postgresql \
    postgresql-client \
    supervisor \
    netcat-openbsd \
    socat \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Clone the vulnerable app
WORKDIR /app
RUN git clone --depth 1 https://github.com/cr0hn/vulnerable-node .

# Install Node dependencies (upgrade pg and pg-promise for PostgreSQL 15 compatibility)
RUN npm install --legacy-peer-deps && \
    npm install pg@8 pg-promise@10 --save --legacy-peer-deps

# Write the DB init script
RUN echo "CREATE DATABASE vulnerablenode;" > /init_db.sql && \
    echo "GRANT ALL PRIVILEGES ON DATABASE vulnerablenode TO postgres;" >> /init_db.sql

# Create supervisor config
RUN mkdir -p /etc/supervisor/conf.d
RUN printf '[supervisord]\nnodaemon=true\n\n[program:postgresql]\ncommand=/usr/lib/postgresql/15/bin/postgres -D /var/lib/postgresql/15/main -c config_file=/etc/postgresql/15/main/postgresql.conf\nuser=postgres\nautostart=true\nautorestart=true\nstdout_logfile=/var/log/pg_stdout.log\nstderr_logfile=/var/log/pg_stderr.log\n\n[program:nodeapp]\ncommand=/start.sh\nuser=root\nautostart=true\nautorestart=false\nstartretries=5\nstartsecs=0\nstdout_logfile=/var/log/node_stdout.log\nstderr_logfile=/var/log/node_stderr.log\n' > /etc/supervisor/conf.d/app.conf

# Inject flag routes into app.js
COPY flag_routes.js /app/flag_routes.js
RUN sed -i "s|app.use('', login);|app.use('', login);\nrequire('./flag_routes')(app);|" /app/app.js

# Create start script
# App runs on PORT=5000 natively — no socat needed
RUN printf '#!/bin/bash\nset -e\n\necho "$FLAG_LOCAL" > /flag_local.txt\nchmod 640 /flag_local.txt\necho "$FLAG_PROOF" > /flag_proof.txt\nchmod 640 /flag_proof.txt\n\nuntil pg_isready -U postgres -h 127.0.0.1; do\n  echo "Waiting for PostgreSQL..."\n  sleep 1\ndone\n\npsql -U postgres -h 127.0.0.1 -f /init_db.sql 2>/dev/null || true\n\ncd /app && exec npm start\n' > /start.sh && chmod +x /start.sh

# Configure PostgreSQL
USER root
RUN sed -i "s|host    all             all             127.0.0.1/32            scram-sha-256|host    all             all             127.0.0.1/32            trust|g" /etc/postgresql/15/main/pg_hba.conf && \
    sed -i "s|listen_addresses = 'localhost'|listen_addresses = '127.0.0.1'|" /etc/postgresql/15/main/postgresql.conf || true

EXPOSE 5000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
