FROM ubuntu:20.04
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && \
    apt-get install -y php7.4 php7.4-mysqli php7.4-zip php7.4-xml libapache2-mod-php7.4 apache2 mariadb-server curl && \
    rm -rf /var/lib/apt/lists/*
RUN mkdir -p /var/content/import/1 && chmod 777 /var/content/import/1
RUN rm -f /etc/apache2/sites-enabled/000-default.conf && \
    printf 'Listen 5000\n' > /etc/apache2/ports.conf
COPY apache.conf /etc/apache2/sites-enabled/quartzite.conf
COPY www/ /var/www/html/
RUN chmod 777 /var/www/html/mods
COPY init.sql /init.sql
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENV PORT=5000
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["/entrypoint.sh"]
