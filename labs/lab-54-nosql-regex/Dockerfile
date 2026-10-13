FROM node:18-alpine

WORKDIR /app

COPY package.json ./
RUN npm install --production

COPY app.js ./

ENV PORT=3000

EXPOSE 3000

CMD sh -c 'echo "$FLAG_LOCAL" > /flag_local.txt && echo "$FLAG_PROOF" > /flag_proof.txt && node app.js'
