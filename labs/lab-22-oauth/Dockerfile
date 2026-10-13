FROM python:3.11-slim
WORKDIR /app
RUN pip install --no-cache-dir flask requests
COPY app.py .
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
EXPOSE 5000
CMD sh -c 'echo "$FLAG_LOCAL" > /app/flag_local.txt && echo "$FLAG_PROOF" > /root/flag_proof.txt && exec python app.py'
