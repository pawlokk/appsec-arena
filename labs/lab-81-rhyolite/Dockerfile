FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y \
    apache2 \
    php \
    php-mysql \
    libapache2-mod-php \
    mariadb-server \
    nodejs \
    npm \
    python3 \
    python3-pip \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN pip3 install aiosmtpd mysql-connector-python --break-system-packages 2>/dev/null || \
    pip3 install aiosmtpd mysql-connector-python

WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev

COPY www/ /var/www/html/
COPY apache.conf /apache.conf
COPY bot.js /app/bot.js
COPY smtp_server.py /smtp_server.py
COPY init.sql /init.sql
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p /var/www/html/tmp /var/www/html/plugins && \
    printf 'order deny, allow\ndeny from all\n' > /var/www/html/tmp/.htaccess && \
    chown -R www-data:www-data /var/www/html

ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"

EXPOSE 5000 25

CMD ["/entrypoint.sh"]
