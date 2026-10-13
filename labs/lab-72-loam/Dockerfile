FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /build

# Build TokenHelper.dll from real source (not the partial source visible to learner)
COPY TokenSource/ /tmp/tokenhelper-src/
RUN cd /tmp/tokenhelper-src && \
    dotnet publish -c Release -o /tmp/tokenhelper-out && \
    cp /tmp/tokenhelper-out/TokenHelper.dll /tmp/TokenHelper.dll && \
    rm -rf /tmp/tokenhelper-src /tmp/tokenhelper-out

COPY loam.csproj .
RUN dotnet restore
COPY . .
RUN dotnet publish -c Release -o /out

FROM mcr.microsoft.com/dotnet/aspnet:8.0
WORKDIR /app
COPY --from=build /out .
COPY --from=build /tmp/TokenHelper.dll /app/Helpers/TokenHelper.dll
COPY appsettings.json /app/appsettings.json
COPY appsettings.Staging.json /app/appsettings.Staging.json
# Partial source visible to learner — compiled version is TokenHelper.dll
COPY Helpers/TokenHelper.cs.partial /app/Helpers/TokenHelper.cs.partial

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV DB_PATH=/app/loam.db
ENV TASK_PROCESSOR_INTERVAL_MS=20000

EXPOSE 5000

CMD echo "$FLAG_LOCAL" > /flag_local.txt && \
    echo "$FLAG_PROOF" > /flag_proof.txt && \
    dotnet loam.dll
