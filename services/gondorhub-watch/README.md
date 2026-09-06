gondorhub-watch — aviso por Telegram cuando LaLiga bloquea (y desbloquea) yourdomain.com
======================================================================================

IDEA
----
El bloqueo de LaLiga es a nivel de ISP español: durante los partidos,
Movistar/Orange/Vodafone/DIGI tiran rangos de IPs de Cloudflare. Resultado: el
dominio deja de responder para tus usuarios (y para la propia Pi, que sale por el
mismo ISP), PERO:
  - Los contenedores siguen vivos en la LAN.
  - La salida a api.telegram.org NO se ve afectada (no es Cloudflare).

Así que la Pi puede detectar el bloqueo probando su propio dominio, y avisarte por
el mismo bot que ya usas (docker-controller-bot), porque el aviso sale "hacia
fuera" y eso no está cortado.

CÓMO DISTINGUE BLOQUEO DE CAÍDA REAL
-----------------------------------
  UP       el dominio responde con normalidad
  BLOCKED  la Pi tiene internet (llega a Google/Telegram) pero NO alcanza
           Cloudflare, y el sensor externo (GitHub Actions, fuera de España) ve el
           sitio arriba -> patrón inequívoco de bloqueo de LaLiga
  OUTAGE   Cloudflare responde 5xx (origen/túnel caído), o el sitio tampoco se ve
           desde fuera -> caída real: revisa cloudflared / NPM / la Pi
  NETDOWN  la Pi se quedó sin internet

Solo avisa en los CAMBIOS de estado, con hora (Europe/Madrid) y, al recuperarse,
cuánto tiempo estuvo caído. Antes de dar por bueno un cambio exige 2 lecturas
iguales seguidas (anti-parpadeo).

Sin el sensor externo TAMBIÉN funciona: "hay internet pero Cloudflare no responde"
ya es, en la práctica, el bloqueo. GitHub Actions solo sube la fiabilidad y te
avisa además si el sitio se cae de verdad estando la Pi sin cobertura de red.

------------------------------------------------------------------------------------
DÓNDE EJECUTARLO — DOS OPCIONES
------------------------------------------------------------------------------------
  A) Stack en el Portainer de la Pi (recomendado). SIN Docker en Windows y SIN
     construir imagen: se usa alpine tal cual y se le pasan los scripts por un
     volumen. Los subes a la Pi con Filebrowser. Pasos completos en
     docker/README.txt. Resumen:
       1. Filebrowser: crea /mnt/hdd/gondorhub-watch y sube ahi watch.sh y
          docker/entrypoint.sh
       2. Portainer > Stacks > Add stack > pega docker/docker-compose.yml >
          rellena TELEGRAM_TOKEN y TELEGRAM_ADMIN > Deploy
     No usa cron: el propio contenedor repite watch.sh cada INTERVAL_SECONDS.
  B) Cron en el host (más ligero, sin Docker, pero necesita shell en la Pi)
     -> pasos abajo.

------------------------------------------------------------------------------------
OPCIÓN B — CRON EN LA PI
------------------------------------------------------------------------------------
1. Lleva esta carpeta a la Pi. Si usas la sync de Nextcloud ya la tienes en
   ~/Nextcloud/pi/gondorhub-watch. Recomendado copiarla fuera de la carpeta
   sincronizada para no generar sync del log:
       cp -r ~/Nextcloud/pi/gondorhub-watch ~/gondorhub-watch

2. Dependencias (Pi OS Lite):
       sudo apt-get update && sudo apt-get install -y curl ca-certificates
   (dig es opcional: si no está, resuelve por DNS-over-HTTPS de Google)

3. Configuración:
       cd ~/gondorhub-watch
       cp watch.env.example watch.env
       nano watch.env        # pega TELEGRAM_TOKEN y TELEGRAM_ADMIN del docker-controller-bot
       chmod 600 watch.env
       chmod +x watch.sh

4. Prueba (lánzalo DOS veces: la 1ª arma el cambio, la 2ª lo confirma y notifica):
       ./watch.sh
       ./watch.sh
   Deberías recibir el "🟢 Monitor en marcha". Estado actual:
       cat ~/.local/state/gondorhub-watch/state

5. Cron cada 2 min:
       crontab -e
   añade:
       */2 * * * * /home/pi/gondorhub-watch/watch.sh >> /home/pi/.local/state/gondorhub-watch/watch.log 2>&1
   Latencia de detección ~4 min (2 pasadas). Pon */1 si lo quieres más rápido.

------------------------------------------------------------------------------------
SENSOR EXTERNO — GITHUB ACTIONS (opcional, recomendado)
------------------------------------------------------------------------------------
1. Crea un Gist en https://gist.github.com con un archivo llamado  status.json  y
   contenido  {}  . Apunta el ID (lo que va tras tu usuario en la URL del gist).

2. Token: GitHub > Settings > Developer settings > Personal access tokens >
   Fine-grained tokens > Generate. Permiso  "Gists: Read and write"  . Cópialo.

3. En el repo donde vayas a poner el workflow:
   Settings > Secrets and variables > Actions > New repository secret:
       GIST_ID          = el id del gist
       GIST_TOKEN       = el token del paso 2
       TELEGRAM_TOKEN   = (opcional) para que Actions avise también en caídas reales
       TELEGRAM_ADMIN   = (opcional) tu chat id
   (Opcional) Variable  GONDORHUB_DOMAIN  si el dominio no es yourdomain.com.

4. Copia  github-actions/gondorhub-probe.yml  a  .github/workflows/  del repo y
   haz push.
       - Repo PÚBLICO: minutos de Actions ilimitados -> deja el cron en */5.
       - Repo PRIVADO: 2000 min/mes gratis -> pon el cron en */15 o */30.
       - GitHub PAUSA los workflows cron tras 60 días sin actividad en el repo.
       - Los cron de GitHub a veces se retrasan 5-15 min: es normal.

5. En la Pi, en  watch.env  , descomenta y ajusta:
       GIST_RAW_URL=https://gist.githubusercontent.com/TU_USUARIO/TU_GIST_ID/raw/status.json
   (sin hash de commit: esa URL siempre sirve la última versión)

------------------------------------------------------------------------------------
NOTAS
------------------------------------------------------------------------------------
- Split-horizon DNS: si Pi-hole reescribe yourdomain.com a la IP de la LAN, el
  script lo evita resolviendo por DNS público (DoH de Google / 8.8.8.8), así que
  siempre prueba el camino real por Cloudflare.
- El token de Telegram da control total del bot: watch.env con chmod 600 y fuera
  de cualquier carpeta que se suba a git.
- Si dejas el script dentro de ~/Nextcloud, excluye la carpeta state/ de la sync
  o tendrás sync constante por el log y los ficheros de estado.
- "Falso NETDOWN": si Google y Telegram fallan a la vez (rarísimo) lo marca como
  NETDOWN. En ese caso tampoco podría notificar, así que no hay spam.
