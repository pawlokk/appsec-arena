FROM python:3.11-slim

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

RUN apt-get update && apt-get install -y imagemagick ghostscript && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/rights="none" pattern="MVG"/rights="read|write" pattern="MVG"/' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/rights="none" pattern="MSL"/rights="read|write" pattern="MSL"/' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/rights="none" pattern="MVG"/rights="read|write" pattern="MVG"/' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/<policy domain="coder" rights="none" pattern="LABEL"/<policy domain="coder" rights="read|write" pattern="LABEL"/' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/domain="coder" rights="none"/domain="coder" rights="read|write"/g' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/domain="delegate" rights="none"/domain="delegate" rights="read|write"/g' /etc/ImageMagick-6/policy.xml || true
RUN sed -i 's/<policy domain="path" rights="none" pattern="@\*"/<policy domain="path" rights="read|write" pattern="@*"/' /etc/ImageMagick-6/policy.xml || true

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py .

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && chmod 644 /flag_local.txt /flag_proof.txt && python app.py"]
