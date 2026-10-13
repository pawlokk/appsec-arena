FROM node:20-slim

WORKDIR /app

COPY package.json ./
RUN npm install --production

COPY app.js ./

RUN mkdir -p /root

CMD sh -c 'echo "${FLAG_LOCAL:-FLAG_LOCAL_NOT_SET}" > /flag_local.txt && echo "${FLAG_PROOF:-FLAG_PROOF_NOT_SET}" > /flag_proof.txt && node app.js'

EXPOSE 5000
