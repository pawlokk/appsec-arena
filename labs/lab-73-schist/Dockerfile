FROM node:18-slim

RUN apt-get update && apt-get install -y \
    postgresql postgresql-contrib \
    chromium \
    libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libgbm1 libgtk-3-0 libnspr4 libnss3 \
    libxcomposite1 libxdamage1 libxfixes3 libxkbcommon0 libxrandr2 \
    libasound2 fonts-liberation xdg-utils \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev

COPY app.js ./
COPY workers/ workers/
COPY migrations/ migrations/
COPY public/ public/
COPY entrypoint.sh ./

RUN chmod +x /app/entrypoint.sh

ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV SESSION_SECRET="schist-session-k9Lm2pQx7r"
ENV DATABASE_URL="postgresql://schist:schistpass@localhost/schist"
ENV AGENT_TOKEN="mK4pQnLr3vWxZy9B"
ENV REVIEW_INTERVAL_MS=15000
ENV PORT=5000

EXPOSE 5000

CMD ["/app/entrypoint.sh"]
