FROM node:20-slim

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY app.js ./

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "printf '%s' \"$FLAG_LOCAL\" > /flag_local.txt && printf '%s' \"$FLAG_PROOF\" > /flag_proof.txt && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000 & node app.js"]
