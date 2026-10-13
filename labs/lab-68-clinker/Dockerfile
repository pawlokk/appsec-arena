FROM maven:3.8-openjdk-17 AS build
WORKDIR /build

# Build clinker-helpers.jar from source, install to local repo, then delete sources
COPY helpers-src/ helpers-src/
RUN mkdir -p helpers-bin && \
    find helpers-src -name "*.java" | xargs javac -d helpers-bin && \
    jar cf clinker-helpers.jar -C helpers-bin . && \
    mvn install:install-file \
        -Dfile=clinker-helpers.jar \
        -DgroupId=com.arena \
        -DartifactId=clinker-helpers \
        -Dversion=1.0 \
        -Dpackaging=jar \
        -q && \
    rm -rf helpers-src helpers-bin

COPY pom.xml .
COPY src/ src/
RUN mvn package -DskipTests -q

FROM eclipse-temurin:17-jre-jammy
WORKDIR /app
COPY --from=build /build/target/*-jar-with-dependencies.jar app.jar
# Helpers JAR included separately so it can be found at /app/clinker-helpers.jar (decompilation target)
COPY --from=build /build/clinker-helpers.jar clinker-helpers.jar
COPY config/clinker.properties /app/config/clinker.properties

RUN apt-get update -qq && apt-get install -y -qq wget ca-certificates && \
    wget -q https://github.com/frohoff/ysoserial/releases/download/v0.0.6/ysoserial-all.jar \
         -O /app/ysoserial.jar || exit 1

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV CLINKER_JOB_INTERVAL_MS=15000
EXPOSE 5000
CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && java -cp app.jar:clinker-helpers.jar com.arena.clinker.ClinkerApp"]
