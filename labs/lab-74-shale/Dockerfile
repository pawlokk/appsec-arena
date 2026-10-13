FROM node:18-slim
RUN apt-get update && apt-get install -y lua5.3 lua-socket && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY app.js state.js network.js ./
COPY services/ services/
COPY public/ public/
COPY entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV PORT=5000
EXPOSE 5000
CMD ["/app/entrypoint.sh"]
