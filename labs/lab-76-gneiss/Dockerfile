FROM php:8.1-cli
WORKDIR /app
COPY www/ www/
COPY entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh && \
    mkdir -p /var/app/data && \
    chmod 777 /var/app/data
ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["/app/entrypoint.sh"]
