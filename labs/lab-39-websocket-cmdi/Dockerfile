FROM node:20-slim

RUN apt-get update && apt-get install -y socat procps iputils-ping && rm -rf /var/lib/apt/lists/*

# Create low-priv user for su -c
RUN useradd -m -s /bin/bash monitor

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY server.js ./

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_local.txt && chmod 644 /flag_proof.txt && node server.js & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000"]
