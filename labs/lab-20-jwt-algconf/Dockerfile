FROM node:20-alpine
WORKDIR /app
COPY package.json app.js ./
RUN npm install
EXPOSE 5000
CMD sh -c 'echo "$FLAG_LOCAL" > /flag_local.txt && echo "$FLAG_PROOF" > /flag_proof.txt && exec node app.js'
