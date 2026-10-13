FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /build
COPY pom.xml .
RUN mvn dependency:go-offline -q
COPY src ./src
RUN mvn package -DskipTests -q

FROM eclipse-temurin:17-jre-jammy
WORKDIR /app
COPY --from=build /build/target/aperture-1.0.jar /app/aperture.jar

EXPOSE 5000

CMD sh -c '\
  ADMIN_PASS=$(cat /dev/urandom | tr -dc "A-Za-z0-9_@#%" | head -c 24) && \
  echo "$ADMIN_PASS" > /app/secret.properties && \
  echo "$FLAG_LOCAL" > /app/flag_local.txt && \
  echo "$FLAG_PROOF" > /flag_proof.txt && \
  exec java -jar /app/aperture.jar'
