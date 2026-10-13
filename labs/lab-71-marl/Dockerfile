FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /build
COPY helpers-src/ /build/helpers-src/
RUN dotnet publish /build/helpers-src/MarlHelpers.csproj -c Release -o /build/helpers-bin && \
    cp /build/helpers-bin/MarlHelpers.dll /build/MarlHelpers.dll && \
    rm -rf /build/helpers-src
COPY marl.csproj .
RUN dotnet restore
COPY . .

ARG BUILD_CONFIG=Release
RUN dotnet publish -c $BUILD_CONFIG -o /out

FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS runtime
WORKDIR /app
COPY --from=build /out .
COPY --from=build /build/MarlHelpers.dll /app/MarlHelpers.dll
COPY appsettings.json /app/appsettings.json
COPY appsettings.Development.json /app/appsettings.Development.json
COPY appsettings.Staging.json /app/appsettings.Staging.json

ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
ENV DB_PATH=/app/marl.db
ENV ASPNETCORE_ENVIRONMENT=Production

EXPOSE 5000

CMD echo "$FLAG_LOCAL" > /flag_local.txt && \
    echo "$FLAG_PROOF" > /flag_proof.txt && \
    dotnet marl.dll

FROM runtime AS debug
RUN apt-get update && apt-get install -y --no-install-recommends curl unzip && \
    curl -sSL https://aka.ms/getvsdbgsh | bash /dev/stdin -v latest -l /vsdbg && \
    apt-get purge -y curl unzip && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

ENV ASPNETCORE_ENVIRONMENT=Development

EXPOSE 5000
EXPOSE 4024

CMD echo "$FLAG_LOCAL" > /flag_local.txt && \
    echo "$FLAG_PROOF" > /flag_proof.txt && \
    dotnet marl.dll
