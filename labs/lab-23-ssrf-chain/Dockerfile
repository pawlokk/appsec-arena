FROM node:18-alpine

RUN apk add --no-cache socat

WORKDIR /app

COPY package.json ./
RUN npm install --production

COPY app.js ./

# Port 5000 is the externally exposed port. socat bridges external :5000 to the
# Node application listening on :3000. The internal admin service binds only to
# 127.0.0.1:3001 and is never exposed directly.
EXPOSE 5000

ENV DEMO_PASSWORD=demo1234

# Start the application and expose it via the socat bridge.
CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && node app.js & sleep 1 && socat TCP-LISTEN:5000,fork,reuseaddr TCP:127.0.0.1:3000"]
