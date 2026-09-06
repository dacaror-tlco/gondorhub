gondorhub-watch en el Portainer de la Pi
=======================================

NO necesitas Docker en Windows ni SSH. Windows solo es donde tienes los archivos.
NO se construye ninguna imagen: usamos alpine (se baja de Docker Hub) y le
pasamos los scripts por un volumen.

------------------------------------------------------------------
PASO 1 — subir los scripts a la Pi con Filebrowser (una sola vez)
------------------------------------------------------------------
1. Abre Filebrowser (files.tudominio...).
2. Crea la carpeta:  /mnt/hdd/gondorhub-watch
3. Sube dentro estos DOS archivos (arrástralos desde el PC):
      - watch.sh          (de  ...\Nextcloud\pi\gondorhub-watch\ )
      - entrypoint.sh     (de  ...\Nextcloud\pi\gondorhub-watch\docker\ )
   No hace falta darles permisos: el contenedor los ejecuta con  bash <archivo>.

------------------------------------------------------------------
PASO 2 — crear el stack en Portainer
------------------------------------------------------------------
1. Portainer > Stacks > Add stack.  Nombre:  gondorhub-watch
2. Web editor: pega el contenido de  docker-compose.yml  (este mismo directorio).
3. En 'environment' rellena:
      TELEGRAM_TOKEN  = el mismo del docker-controller-bot
      TELEGRAM_ADMIN  = tu chat id
   (opcional) descomenta y rellena  GIST_RAW_URL  si vas a usar el sensor externo.
4. Deploy the stack.

Al arrancar, el contenedor instala bash+curl (unos segundos) y empieza el bucle.

------------------------------------------------------------------
COMPROBAR
------------------------------------------------------------------
Portainer > Containers > gondorhub-watch > Logs. Deberias ver:
    gondorhub-watch: arrancando (intervalo 120s, dominio yourdomain.com)
    AAAA-MM-DD HH:MM:SS CEST  cambio candidato: INIT -> UP (se confirma ...)
    AAAA-MM-DD HH:MM:SS CEST  NOTIFICADO: INIT -> UP
y te llega el "Monitor en marcha" por Telegram (~2-4 min tras arrancar).

El estado vive en el volumen  gondorhub-watch-data  (/data): sobrevive a recrear
el contenedor, asi el mensaje de "desbloqueado" sigue diciendo cuanto duro el corte.

------------------------------------------------------------------
ACTUALIZAR watch.sh mas adelante
------------------------------------------------------------------
1. Filebrowser: sube el watch.sh nuevo a  /mnt/hdd/gondorhub-watch  (sobrescribe).
2. Portainer > Containers > gondorhub-watch > Restart.
No hay que reconstruir nada.

------------------------------------------------------------------
AJUSTES
------------------------------------------------------------------
- INTERVAL_SECONDS: cada cuanto comprueba (120 por defecto). Latencia de
  deteccion = 2 x INTERVAL (exige 2 lecturas iguales antes de avisar).
- Solo trafico saliente: DoH de Google, Cloudflare, api.telegram.org,
  gist.githubusercontent.com y el repo de alpine. Sin puertos, sin red host.
- Nota: al colgar los scripts de /mnt/hdd, el vigilante depende del HDD. Si
  prefieres que sea independiente del HDD, esta la via de imagen propia en
  Dockerfile (construir por SSH, patron rpi-monitor) — mas robusta pero necesita
  shell en el host.
