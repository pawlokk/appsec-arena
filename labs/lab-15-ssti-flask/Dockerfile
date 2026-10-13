FROM python:3.9

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

RUN apt-get update && apt-get install -y git socat && apt-get clean

WORKDIR /app

RUN git clone --depth 1 https://github.com/filipkarc/ssti-flask-hacking-playground .

RUN pip3 install --upgrade pip && pip3 install -r requirements.txt

# Overlay patched app with flag delivery
COPY __init__.py /app/__init__.py

RUN echo '#!/bin/sh' > /start.sh && \
    echo 'echo "$FLAG_LOCAL" > /flag_local.txt' >> /start.sh && \
    echo 'echo "$FLAG_PROOF" > /flag_proof.txt' >> /start.sh && \
    echo 'chmod 640 /flag_proof.txt && chown root:www-data /flag_proof.txt 2>/dev/null || true' >> /start.sh && \
    echo 'socat TCP-LISTEN:5000,fork TCP:127.0.0.1:8089 &' >> /start.sh && \
    echo 'exec python3 /app/__init__.py' >> /start.sh && \
    chmod +x /start.sh

EXPOSE 5000

CMD ["/start.sh"]
