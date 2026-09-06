# Imagen de gondorhub-watch. Construir con el contexto en la carpeta padre:
#     cd ~/Nextcloud/pi/gondorhub-watch
#     docker build -f docker/Dockerfile -t gondorhub-watch:latest .
FROM alpine:3.20

RUN apk add --no-cache bash curl ca-certificates tzdata bind-tools

WORKDIR /app
COPY watch.sh          /app/watch.sh
COPY docker/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/watch.sh /app/entrypoint.sh

ENV STATE_DIR=/data \
    INTERVAL_SECONDS=120

ENTRYPOINT ["/app/entrypoint.sh"]
