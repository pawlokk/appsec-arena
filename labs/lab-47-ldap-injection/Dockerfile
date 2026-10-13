FROM node:18-slim

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV JWT_SECRET=changeme-set-at-runtime
ENV ADMIN_PASSWORD=changeme-set-at-runtime
ENV USER_PASSWORD=changeme-set-at-runtime

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

# Maintenance token required to unlock the exec endpoint
RUN echo "maint-$(head -c 8 /dev/urandom | od -An -tx1 | tr -d ' \n')" > /etc/maintenance_token && chmod 400 /etc/maintenance_token

WORKDIR /app

COPY package.json ./
RUN npm install

COPY app.js ./

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && chmod 600 /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 600 /flag_proof.txt && socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:3000 & node app.js"]
