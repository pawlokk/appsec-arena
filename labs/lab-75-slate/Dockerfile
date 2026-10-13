FROM node:16-slim
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev
COPY app.js ./
COPY views/ views/
COPY files/ files/
COPY public/ public/
COPY entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh
ENV TEMPLATING_ENGINE="hbs"
ENV TOKEN_SECRET="Ch1ps_S3cur3_T0k3n_K3y_32Bytes!!"
ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["/app/entrypoint.sh"]
