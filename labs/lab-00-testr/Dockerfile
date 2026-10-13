FROM php:8.1-apache
RUN mkdir -p /app
WORKDIR /var/www/html
COPY index.php .
EXPOSE 5000
CMD ["/bin/sh", "-c", \
  "printf '%s\\n' \"${FLAG_LOCAL}\" > /app/flag_local.txt && \
   printf '%s\\n' \"${FLAG_PROOF}\" > /flag_proof.txt && \
   sed -i 's/80/5000/g' /etc/apache2/sites-enabled/000-default.conf /etc/apache2/ports.conf && \
   apache2-foreground"]
