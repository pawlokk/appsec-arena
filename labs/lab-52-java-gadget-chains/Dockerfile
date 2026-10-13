FROM maven:3.8-openjdk-17 AS build
WORKDIR /build
COPY pom.xml .
COPY src/ src/
RUN mvn package -DskipTests -q

FROM eclipse-temurin:17-jre-jammy
WORKDIR /app
COPY --from=build /build/target/*.jar app.jar
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
EXPOSE 5000
CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && java -jar app.jar"]
