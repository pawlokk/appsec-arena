FROM node:18-alpine

RUN apk add --no-cache socat

WORKDIR /app

COPY package.json .
RUN npm install --production

COPY app.js .

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /tmp/flag_local.txt && echo \"$FLAG_PROOF\" > /tmp/flag_proof.txt && node app.js & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000"]
