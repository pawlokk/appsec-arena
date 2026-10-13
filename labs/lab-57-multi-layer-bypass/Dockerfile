FROM node:20-alpine

RUN apk add --no-cache socat

WORKDIR /app

COPY package.json ./
RUN npm install --production

COPY app.js ./

ENV FLAG_LOCAL=ARENA{placeholder}
ENV FLAG_PROOF=ARENA{placeholder}
ENV USER_PASSWORD=""

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && node app.js & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000"]
