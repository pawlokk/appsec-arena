FROM python:3.10-slim

WORKDIR /app

RUN pip install --no-cache-dir "cython<3" && pip install --no-cache-dir flask "pyyaml==5.4.1" --no-build-isolation

COPY app.py .

RUN mkdir -p /root

EXPOSE 5000

CMD sh -c 'echo "$FLAG_LOCAL" > /app/flag_local.txt && echo "$FLAG_PROOF" > /root/flag_proof.txt && exec python app.py'
