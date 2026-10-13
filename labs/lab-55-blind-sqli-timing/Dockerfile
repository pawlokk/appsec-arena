FROM python:3.12-slim

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    postgresql \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py init.sql entrypoint.sh ./
RUN chmod +x /app/entrypoint.sh

ENV DB_HOST=localhost
ENV DB_NAME=arena_db
ENV DB_USER=arena_user
ENV DB_PASS=arena_pass
ENV FLASK_ENV=production

EXPOSE 5000

CMD ["/bin/bash", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && /app/entrypoint.sh"]
