FROM mongo:7.0

RUN apt-get update && apt-get install -y \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY package.json .
RUN npm install --omit=dev
COPY app.js .
COPY middleware/ middleware/
COPY workers/ workers/
COPY routes/ routes/

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV EMAIL_WORKER_INTERVAL_MS=20000
EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  mkdir -p /data/db && \
  mongod --bind_ip 127.0.0.1 --fork --logpath /var/log/mongod.log && \
  sleep 2 && \
  node workers/email_worker.js >> /tmp/slag_email.log 2>&1 & \
  node app.js \
"]
