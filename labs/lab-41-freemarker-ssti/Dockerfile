FROM maven:3.8-openjdk-17 AS build
WORKDIR /build
COPY pom.xml .
COPY src/ src/
RUN mvn package -DskipTests -q

FROM eclipse-temurin:17-jre-jammy
WORKDIR /app
RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*
COPY --from=build /build/target/*.jar app.jar
EXPOSE 5000
CMD ["sh", "-c", "if [ -z \"$FLAG_LOCAL\" ] || [ -z \"$FLAG_PROOF\" ]; then echo 'ERROR: FLAG_LOCAL and FLAG_PROOF environment variables must be set' >&2; exit 1; fi && echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && java -jar app.jar & sleep 3 && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:8080"]
