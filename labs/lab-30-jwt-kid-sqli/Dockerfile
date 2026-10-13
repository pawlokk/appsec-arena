FROM node:20-alpine

RUN apk add --no-cache socat

WORKDIR /app

COPY package.json ./
RUN npm install --production

COPY app.js ./

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV JWT_SECRET=runtime-jwt-secret

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && node app.js & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000"]
