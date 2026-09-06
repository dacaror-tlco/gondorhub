#!/usr/bin/env bash
# gondorhub-watch — avisa por Telegram cuando LaLiga bloquea (y desbloquea) yourdomain.com
#
# Pensado para correr por cron EN LA PROPIA PI: sale por el mismo ISP que tus
# usuarios, así que ve el bloqueo igual que ellos. El aviso va a api.telegram.org
# (no es Cloudflare -> LaLiga no lo bloquea), por eso la notificación llega
# incluso durante el corte.
#
# Estados:
#   UP       el dominio responde con normalidad
#   BLOCKED  hay internet (llega a Google/Telegram) pero NO se alcanza Cloudflare,
#            y el sensor externo (si está configurado) ve el sitio arriba
#            -> patrón típico del bloqueo de IPs de LaLiga
#   OUTAGE   Cloudflare responde 5xx (origen/túnel caído) o tampoco se ve desde
#            fuera de España -> caída real, no bloqueo
#   NETDOWN  la Pi se quedó sin internet
#
# Solo notifica en los CAMBIOS de estado. Antes de dar por bueno un cambio exige
# 2 lecturas iguales seguidas (anti-parpadeo).

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${GONDORHUB_WATCH_ENV:-$SCRIPT_DIR/watch.env}"
# shellcheck disable=SC1090
[ -f "$ENV_FILE" ] && . "$ENV_FILE"

DOMAIN="${DOMAIN:-yourdomain.com}"
TELEGRAM_TOKEN="${TELEGRAM_TOKEN:?falta TELEGRAM_TOKEN en watch.env}"
TELEGRAM_ADMIN="${TELEGRAM_ADMIN:?falta TELEGRAM_ADMIN en watch.env}"
GIST_RAW_URL="${GIST_RAW_URL:-}"
RESOLVER="${RESOLVER:-8.8.8.8}"
CURL_TIMEOUT="${CURL_TIMEOUT:-12}"
TZ_DISPLAY="${TZ_DISPLAY:-Europe/Madrid}"
STATE_DIR="${STATE_DIR:-${XDG_STATE_HOME:-$HOME/.local/state}/gondorhub-watch}"

mkdir -p "$STATE_DIR"
STATE_FILE="$STATE_DIR/state"
PENDING_FILE="$STATE_DIR/pending"
SINCE_FILE="$STATE_DIR/since"

now_epoch() { date +%s; }
now_human() { TZ="$TZ_DISPLAY" date '+%Y-%m-%d %H:%M:%S %Z'; }
log() { printf '%s  %s\n' "$(now_human)" "$*"; }

tg_send() {
  curl -sS --max-time 15 \
    -X POST "https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage" \
    --data-urlencode "chat_id=${TELEGRAM_ADMIN}" \
    --data-urlencode "text=${1}" \
    --data-urlencode "parse_mode=HTML" \
    --data-urlencode "disable_web_page_preview=true" \
    -o /dev/null
}

human_duration() {
  local s="$1" d h m out=""
  d=$(( s / 86400 )); s=$(( s % 86400 ))
  h=$(( s / 3600 ));  s=$(( s % 3600 ))
  m=$(( s / 60 ))
  [ "$d" -gt 0 ] && out="${out}${d}d "
  [ "$h" -gt 0 ] && out="${out}${h}h "
  printf '%s%dm' "$out" "$m"
}

# Resuelve el dominio por DNS PÚBLICO (DoH de Google, con dig de reserva) para
# saltarse cualquier rewrite local de Pi-hole y probar siempre el camino real.
resolve_public() {
  local out
  out="$(curl -sS --max-time 10 "https://dns.google/resolve?name=${DOMAIN}&type=A" 2>/dev/null \
         | tr ',' '\n' \
         | sed -n 's/.*"data":[[:space:]]*"\([0-9.]\{7,\}\)".*/\1/p' \
         | grep -Eo '^[0-9]+(\.[0-9]+){3}$' | head -1)"
  if [ -z "$out" ] && command -v dig >/dev/null 2>&1; then
    out="$(dig +short "@${RESOLVER}" "$DOMAIN" A 2>/dev/null \
           | grep -Eo '^[0-9]+(\.[0-9]+){3}$' | head -1)"
  fi
  printf '%s' "$out"
}

# --- 1. Sonda local: probar yourdomain.com por el camino real (Cloudflare) ---
DOMAIN_IP="$(resolve_public)"
http_code="000"; curl_exit=1
if [ -n "$DOMAIN_IP" ]; then
  http_code="$(curl -sS --max-time "$CURL_TIMEOUT" \
                 --resolve "${DOMAIN}:443:${DOMAIN_IP}" \
                 -o /dev/null -w '%{http_code}' "https://${DOMAIN}/" 2>/dev/null)"
  curl_exit=$?
fi
[[ "$http_code" =~ ^[0-9]+$ ]] || http_code="000"

if [ "$curl_exit" -eq 0 ] && [ "$http_code" -ge 100 ]; then
  if [ "$http_code" -ge 500 ]; then
    local_state="origin_down"   # Cloudflare respondió, pero el origen/túnel no
  else
    local_state="up"
  fi
else
  local_state="unreachable"     # timeout / reset / refused -> posible bloqueo
fi

# --- 2. Control: ¿tiene internet la Pi? (destinos NO-Cloudflare) ---
control_ok=0
if curl -sS --max-time 10 -o /dev/null -w '%{http_code}' \
     "https://www.google.com/generate_204" 2>/dev/null | grep -q '204'; then
  control_ok=1
elif curl -sS --max-time 10 -o /dev/null "https://api.telegram.org/" 2>/dev/null; then
  control_ok=1
fi

# --- 3. Sensor externo (GitHub Actions -> Gist), opcional ---
external="unknown"   # up | down | unknown
if [ -n "$GIST_RAW_URL" ]; then
  gist_json="$(curl -sS --max-time 10 "$GIST_RAW_URL" 2>/dev/null || true)"
  if [ -n "$gist_json" ]; then
    up_field="$(printf '%s' "$gist_json" | grep -Eo '"up"[[:space:]]*:[[:space:]]*(true|false)' | grep -Eo 'true|false')"
    ts_epoch="$(printf '%s' "$gist_json" | grep -Eo '"checked_at_epoch"[[:space:]]*:[[:space:]]*[0-9]+' | grep -Eo '[0-9]+$')"
    if [ -z "$ts_epoch" ]; then
      ts_field="$(printf '%s' "$gist_json" | grep -Eo '"checked_at"[[:space:]]*:[[:space:]]*"[^"]*"' | sed 's/.*"\([^"]*\)"$/\1/')"
      [ -n "$ts_field" ] && ts_epoch="$(date -d "$ts_field" +%s 2>/dev/null || echo 0)"
    fi
    ts_epoch="${ts_epoch:-0}"
    if [ "$ts_epoch" -gt 0 ] && [ $(( $(now_epoch) - ts_epoch )) -lt 1200 ]; then
      [ "$up_field" = "true" ]  && external="up"
      [ "$up_field" = "false" ] && external="down"
    fi
  fi
fi

# --- 4. Estado final ---
case "$local_state" in
  up)          state="UP" ;;
  origin_down) state="OUTAGE" ;;
  *)
    if   [ "$control_ok" -eq 0 ];   then state="NETDOWN"
    elif [ "$external" = "up" ];    then state="BLOCKED"
    elif [ "$external" = "down" ];  then state="OUTAGE"
    else                                state="BLOCKED"   # sin sensor: internet OK + Cloudflare KO = bloqueo
    fi
    ;;
esac

# --- 5. Transición + aviso (con anti-parpadeo de 2 lecturas) ---
prev_state="$(cat "$STATE_FILE" 2>/dev/null || echo INIT)"
prev_since="$(cat "$SINCE_FILE" 2>/dev/null || now_epoch)"
pending="$(cat "$PENDING_FILE" 2>/dev/null || echo '')"

if [ "$state" = "$prev_state" ]; then
  [ -s "$PENDING_FILE" ] && : > "$PENDING_FILE"
  log "sin cambios (estado=$state http=$http_code exit=$curl_exit ext=$external)"
  exit 0
fi

if [ "$pending" != "$state" ]; then
  printf '%s' "$state" > "$PENDING_FILE"
  log "cambio candidato: $prev_state -> $state (se confirma en la próxima pasada)"
  exit 0
fi

tnow="$(now_epoch)"
dur="$(human_duration $(( tnow - prev_since )))"
hh="$(now_human)"

case "$state" in
  UP)
    case "$prev_state" in
      BLOCKED) msg="$(printf '🟢 <b>yourdomain.com desbloqueado</b>\nLaLiga ha levantado el bloqueo. Estuvo cortado para tus usuarios ~%s.\n%s' "$dur" "$hh")" ;;
      OUTAGE)  msg="$(printf '🟢 <b>yourdomain.com recuperado</b>\nVuelve a responder tras ~%s de caída.\n%s' "$dur" "$hh")" ;;
      NETDOWN) msg="$(printf '🟢 <b>yourdomain.com OK</b>\nLa Pi recuperó internet (~%s sin conexión).\n%s' "$dur" "$hh")" ;;
      *)       msg="$(printf '🟢 <b>yourdomain.com OK</b>\nMonitor en marcha, el sitio responde con normalidad.\n%s' "$hh")" ;;
    esac ;;
  BLOCKED)
    msg="$(printf '🔴 <b>yourdomain.com bloqueado por LaLiga</b>\nLa Pi tiene internet pero no alcanza Cloudflare (patrón típico del bloqueo de IPs de LaLiga). Tus servicios siguen vivos en la LAN; lo cortado es el acceso por el dominio.\n%s' "$hh")" ;;
  OUTAGE)
    msg="$(printf '🟠 <b>yourdomain.com caído — NO es bloqueo</b>\nCloudflare devuelve error de origen/túnel, o el sitio tampoco se ve desde fuera de España. Revisa cloudflared / NPM / la Pi.\n%s' "$hh")" ;;
  NETDOWN)
    msg="$(printf '⚫ <b>La Pi se quedó sin internet</b>\nNo se puede comprobar el dominio. Este aviso puede llegar con retraso.\n%s' "$hh")" ;;
esac

if tg_send "$msg"; then
  : > "$PENDING_FILE"
  printf '%s' "$state" > "$STATE_FILE"
  printf '%s' "$tnow"  > "$SINCE_FILE"
  log "NOTIFICADO: $prev_state -> $state"
else
  log "no se pudo enviar el aviso a Telegram; se reintentará en la próxima pasada"
  exit 1
fi
