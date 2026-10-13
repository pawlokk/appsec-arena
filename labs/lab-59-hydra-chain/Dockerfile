FROM node:18-slim

ENV FLAG_LOCAL="ARENA{placeholder}" \
    FLAG_PROOF="ARENA{placeholder}" \
    INTERNAL_API_KEY=""

RUN apt-get update && apt-get install -y \
    openjdk-17-jdk \
    maven \
    wget \
    socat \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json /app/package.json
RUN npm install

COPY app.js /app/app.js

COPY internal-service /build/internal-service
WORKDIR /build/internal-service
RUN mvn package -q
RUN cp target/internal-service-1.0.0.jar /app/internal-service.jar

WORKDIR /app

RUN wget -q -O /app/ysoserial.jar \
    https://github.com/frohoff/ysoserial/releases/download/v0.0.6/ysoserial-all.jar || \
    (echo "ERROR: ysoserial download failed" && exit 1)

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && java -cp /app/internal-service.jar com.appsec.InternalService &\nsocat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000 &\nnode /app/app.js"]
