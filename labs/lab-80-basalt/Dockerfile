FROM node:18-slim
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY app.js entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh
ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["/app/entrypoint.sh"]
