FROM mongo:7.0

RUN apt-get update && apt-get install -y \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY package.json .
RUN npm install --omit=dev
COPY app.js .

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
EXPOSE 5000

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  mkdir -p /data/db && \
  mongod --bind_ip 127.0.0.1 --fork --logpath /var/log/mongod.log && \
  sleep 2 && \
  node app.js \
"]
