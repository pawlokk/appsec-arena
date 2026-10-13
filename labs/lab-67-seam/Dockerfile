# Stage 1: Build
FROM maven:3.8-openjdk-17 AS builder
WORKDIR /build
COPY pom.xml .
COPY src ./src
RUN mvn -q package -DskipTests

# Stage 2: Run
FROM eclipse-temurin:17-jdk-jammy
WORKDIR /app

ENV DEBIAN_FRONTEND=noninteractive
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASSWORD=seam_admin_2024

RUN apt-get update && apt-get install -y \
    postgresql \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /build/target/seam.jar /app/seam.jar
COPY --from=builder /root/.m2/repository/com/h2database/h2/2.2.224/h2-2.2.224.jar /app/h2.jar

# Configure PostgreSQL: trust auth
RUN PG_VERSION=$(ls /etc/postgresql/ | head -1) && \
    sed -i 's|^local\s\+all\s\+all\s\+peer|local all all trust|' /etc/postgresql/$PG_VERSION/main/pg_hba.conf && \
    echo "host all all 127.0.0.1/32 trust" >> /etc/postgresql/$PG_VERSION/main/pg_hba.conf

EXPOSE 5000
EXPOSE 9001

RUN mkdir -p /app/webroot /app/cache /app/config
COPY config/seam-cache.sh /app/config/seam-cache.sh
COPY config/seam-cache.xml /app/config/seam-cache.xml

CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  chmod 644 /flag_local.txt /flag_proof.txt && \
  service postgresql start && \
  sleep 5 && \
  su -c \"psql -c \\\"CREATE USER seamuser WITH SUPERUSER PASSWORD 'seampass';\\\" 2>/dev/null || true\" postgres && \
  su -c \"psql -c \\\"CREATE DATABASE seamdb OWNER seamuser;\\\" 2>/dev/null || true\" postgres && \
  java -jar /app/seam.jar \
"]
