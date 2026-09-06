#!/usr/bin/env bash
# Bucle del servicio: ejecuta watch.sh cada INTERVAL_SECONDS. Sin cron.
set -u

INTERVAL="${INTERVAL_SECONDS:-120}"

term() { echo "gondorhub-watch: SIGTERM recibido, saliendo"; exit 0; }
trap term TERM INT

echo "gondorhub-watch: arrancando (intervalo ${INTERVAL}s, dominio ${DOMAIN:-yourdomain.com})"

while true; do
  bash /app/watch.sh || true
  # sleep en segundo plano + wait -> el contenedor responde rápido a 'docker stop'
  sleep "$INTERVAL" &
  wait $!
done
