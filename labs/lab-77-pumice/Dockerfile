FROM node:18-slim
RUN apt-get update && apt-get install -y postgresql-15 curl && rm -rf /var/lib/apt/lists/*
RUN mkdir -p /var/lib/postgresql/data && chown postgres:postgres /var/lib/postgresql/data
USER postgres
RUN /usr/lib/postgresql/15/bin/initdb -D /var/lib/postgresql/data \
    && echo "local all all trust" > /var/lib/postgresql/data/pg_hba.conf \
    && echo "host all all 127.0.0.1/32 trust" >> /var/lib/postgresql/data/pg_hba.conf
USER root
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY app.js init.sql entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh
ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["/app/entrypoint.sh"]
