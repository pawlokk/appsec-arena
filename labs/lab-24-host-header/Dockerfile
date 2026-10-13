FROM python:3.11-slim

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .
COPY init_db.py .

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "echo $FLAG_LOCAL > /flag_local.txt && echo $FLAG_PROOF > /flag_proof.txt && python init_db.py && python app.py"]
