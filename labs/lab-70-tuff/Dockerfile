FROM node:18-slim

RUN apt-get update && apt-get install -y postgresql postgresql-contrib monitoring-plugins && rm -rf /var/lib/apt/lists/*

RUN useradd -r -s /bin/sh -d /app/plugins tuffmon

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY app.js ./
COPY workers/ workers/
COPY middleware/ middleware/
COPY public/ public/
COPY entrypoint.sh ./

RUN mkdir -p /app/plugins /app/config && \
    for p in /usr/lib/nagios/plugins/check_*; do [ -f "$p" ] && ln -sf "$p" /app/plugins/ 2>/dev/null || true; done && \
    chmod +x /app/entrypoint.sh

ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV SESSION_SECRET="tuff-session-secret-k9Lm2pQx"
ENV DATABASE_URL="postgresql://tuff:tuffpass@localhost/tuff"
ENV SCANNER_SESSION="tuff-scanner-x9k2m3p7"
ENV LINK_SCANNER_INTERVAL_MS=15000
ENV PORT=5000
ENV MONITOR_KEY="tuff-mon-k3y-7f2a1b9c"

EXPOSE 5000

CMD ["/app/entrypoint.sh"]
