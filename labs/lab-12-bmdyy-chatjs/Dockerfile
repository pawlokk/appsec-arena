FROM mongo:7.0

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

RUN apt-get update && apt-get install -y \
    curl socat \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm install

COPY app.js .
COPY views ./views
COPY public ./public
COPY .docker ./.docker

EXPOSE 5000

CMD ["sh", "-c", "\
    echo \"$FLAG_LOCAL\" > /flag_local.txt && \
    chmod 640 /flag_local.txt && \
    echo \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 640 /flag_proof.txt && \
    mkdir -p /data/db && \
    mongod --bind_ip 127.0.0.1 --fork --logpath /var/log/mongod.log && \
    sleep 3 && \
    mongosh /app/.docker/init.js 2>/dev/null || true && \
    socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:3000 & \
    exec node app.js \
"]
