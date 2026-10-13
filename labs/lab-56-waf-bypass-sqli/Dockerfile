FROM python:3.11-slim

ENV FLAG_LOCAL=placeholder
ENV FLAG_PROOF=placeholder
ENV ADMIN_PASS=""
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y default-mysql-server && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py .
COPY init.sql .

EXPOSE 5000

CMD ["sh", "-c", "if [ -z \"$ADMIN_PASS\" ]; then echo 'ERROR: ADMIN_PASS environment variable is not set' >&2; exit 1; fi && echo \"$FLAG_LOCAL\" > /flag_local.txt && chmod 640 /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_proof.txt && service mysql start && sleep 5 && sed \"s|__ADMIN_PASS__|$ADMIN_PASS|g\" /app/init.sql | mysql && python3 app.py"]
