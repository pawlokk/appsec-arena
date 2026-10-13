FROM python:3.11-slim
WORKDIR /app
RUN pip install --no-cache-dir flask
COPY app.py .
EXPOSE 5000
CMD sh -c 'echo "$FLAG_LOCAL" > /flag_local.txt && echo "$FLAG_PROOF" > /root/flag_proof.txt && exec python app.py'
