FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .
COPY users.xml .

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASSWORD=changeme

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && sed -i \"s|ADMIN_PASSWORD_PLACEHOLDER|${ADMIN_PASSWORD}|g\" /app/users.xml && python app.py"]
