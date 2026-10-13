FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /build
COPY src/ .
RUN dotnet publish -c Release -o /out

FROM mcr.microsoft.com/dotnet/aspnet:8.0
WORKDIR /app
COPY --from=build /out .
ENV FLAG_LOCAL=ARENA{LOCAL_placeholder}
ENV FLAG_PROOF=ARENA{PROOF_placeholder}
EXPOSE 5000
ENTRYPOINT ["dotnet", "Cipher.dll"]
