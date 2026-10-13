FROM maven:3.8-openjdk-17 AS build
WORKDIR /build
COPY pom.xml .
COPY src ./src
RUN mvn -q package -DskipTests

FROM eclipse-temurin:17-jre-jammy
WORKDIR /app

RUN apt-get update && apt-get install -y socat && rm -rf /var/lib/apt/lists/*

COPY --from=build /build/target/lab-44-ognl-injection-1.0-SNAPSHOT-jar-with-dependencies.jar /app/app.jar

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}

EXPOSE 5000

CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && java -jar /app/app.jar & socat TCP-LISTEN:5000,fork TCP:127.0.0.1:8080"]
