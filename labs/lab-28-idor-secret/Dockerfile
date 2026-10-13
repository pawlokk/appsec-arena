FROM python:3.11-slim

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "printf '%s' \"$FLAG_LOCAL\" > /flag_local.txt && printf '%s' \"$FLAG_PROOF\" > /flag_proof.txt && python app.py & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:5001"]
