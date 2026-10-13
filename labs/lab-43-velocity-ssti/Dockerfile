# Stage 1: Build
FROM maven:3.8-openjdk-17 AS build
WORKDIR /build
COPY pom.xml .
RUN mvn dependency:go-offline -q
COPY src ./src
RUN mvn package -DskipTests -q

# Stage 2: Runtime
FROM eclipse-temurin:17-jre-jammy
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends socat && rm -rf /var/lib/apt/lists/*

COPY --from=build /build/target/lab-43-velocity-ssti-1.0-SNAPSHOT-jar-with-dependencies.jar /app/app.jar

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV ADMIN_PASS=platform_will_override_this_value

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:8080 & java -jar /app/app.jar"]
