FROM python:3.11-slim

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASS=ARENA_ADMIN_placeholder

RUN apt-get update && apt-get install -y \
    default-mysql-server \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .
COPY init.sql .
COPY mysql-arena.cnf /etc/mysql/conf.d/arena.cnf

EXPOSE 5000

CMD ["sh", "-c", "\
    printf '%s' \"$FLAG_LOCAL\" > /flag_local.txt && \
    printf '%s' \"$FLAG_PROOF\" > /flag_proof.txt && \
    chmod 644 /flag_local.txt /flag_proof.txt && \
    service mariadb start && \
    sleep 4 && \
    mysql -u root -e \"CREATE USER IF NOT EXISTS 'arena'@'localhost' IDENTIFIED BY 'arena';\" 2>/dev/null || true && \
    mysql -u root -e \"GRANT ALL PRIVILEGES ON *.* TO 'arena'@'localhost' WITH GRANT OPTION;\" 2>/dev/null || true && \
    mysql -u root -e \"GRANT FILE ON *.* TO 'arena'@'localhost';\" 2>/dev/null || true && \
    mysql -u root -e \"FLUSH PRIVILEGES;\" 2>/dev/null || true && \
    mysql -u arena -parena -e \"SET @ADMIN_PASS='${ADMIN_PASS}';\" arena 2>/dev/null || true && \
    mysql -u arena -parena --init-command=\"SET @ADMIN_PASS='${ADMIN_PASS}'\" arena < /app/init.sql && \
    python app.py \
"]
