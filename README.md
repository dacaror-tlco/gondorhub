# GondorHub 🏔️

Homelab self-hosted sobre una Raspberry Pi 4B (4GB RAM), con dominio propio, proxy inverso y una docena de servicios en Docker. Este repo documenta la arquitectura, las configuraciones (anonimizadas) y los aprendizajes acumulados montando todo esto.

> ⚠️ Todos los dominios, IPs y credenciales en este repo son **placeholders**. Los valores reales viven en un `.env` local que nunca se sube a git.

## Stack general

- **Hardware:** Raspberry Pi 4B, 4GB RAM + disco duro externo (`/mnt/hdd`)
- **Gestión Docker:** Portainer (stacks)
- **Proxy inverso / SSL:** Nginx Proxy Manager (NPM) + Cloudflare (DNS challenge)
- **Dominio:** `yourdomain.com` (placeholder) vía Cloudflare, con CNAME flattening apuntando a un DDNS (`${DDNS_HOSTNAME}`) para IP dinámica

## Servicios desplegados

| Servicio | Función | Subdominio (ejemplo) |
|---|---|---|
| Nextcloud | Almacenamiento en la nube personal | `cloud.yourdomain.com` |
| Jellyfin | Streaming multimedia | `media.yourdomain.com` |
| PhotoPrism | Gestión y reconocimiento facial de fotos | `photos.yourdomain.com` |
| qBittorrent | Cliente torrent | `torrent.yourdomain.com` |
| MeTube | Descarga de vídeos (yt-dlp con UI) | `metube.yourdomain.com` |
| Pi-hole | DNS ad-blocking | `pihole.yourdomain.com` |
| WireGuard (wg-easy) | VPN | `vpn.yourdomain.com` |
| Vaultwarden | Gestor de contraseñas (compatible con Bitwarden) | `vault.yourdomain.com` |
| Filebrowser | Explorador de archivos web, subida drag & drop al HDD | `files.yourdomain.com` |
| Portainer | Gestión de contenedores Docker | `portainer.yourdomain.com` |
| code-server | Edición de código vía navegador | `code.yourdomain.com` |
| web-campamento / web-palantir | Sitios estáticos de un proyecto de campamento juvenil | `campamento.yourdomain.com` / `palantir.yourdomain.com` |
| portalpi | Portal/índice estático adicional | `portal.yourdomain.com` |
| Samba | Compartición de archivos en red local | (solo red local) |
| ddclient | Cliente DDNS, mantiene actualizado el hostname dinámico | (sin subdominio propio) |
| docker-controller-bot | Bot de Telegram para gestionar contenedores Docker | (sin subdominio, uso vía Telegram) |
| rpi-monitor | Dashboard propio de monitorización del sistema (Flask + psutil) | `monitor.yourdomain.com` |

## Estructura del repo

```
gondorhub/
├── README.md
├── .env.example              # Variables de entorno (placeholders)
├── .gitignore
├── compose/                  # docker-compose.yml de cada stack, anonimizados
│   ├── code-server.yml
│   ├── ddclient.yml
│   ├── docker-controller-bot.yml
│   ├── filebrowser.yml
│   ├── jellyfin.yml
│   ├── metube.yml
│   ├── nextcloud.yml
│   ├── nginx-proxy-manager.yml
│   ├── photoprism.yml
│   ├── photoprism-mariadb.migration-example.yml
│   ├── pihole.yml
│   ├── portalpi.yml
│   ├── qbittorrent.yml
│   ├── samba.yml
│   ├── vaultwarden.yml
│   ├── web-campamento.yml
│   ├── web-palantir.yml
│   └── wg-easy.yml
├── services/
│   └── rpi-monitor/           # Dashboard de monitorización (Flask + psutil), imagen construida manualmente
├── docs/
│   ├── architecture.md        # Arquitectura de red, DNS, SSL
│   ├── services.md            # Detalle de cada servicio y su configuración
│   ├── troubleshooting.md     # Problemas resueltos y soluciones (aprendizajes)
│   ├── camp-project.md        # Proyecto de las webs del campamento
│   ├── networking.md          # Puertos, DDNS, ZeroTier, bot de Telegram
│   ├── cloudflare-access.md   # Zero Trust / Access con Google como IdP
│   ├── setup-docker-portainer.md # Instalación base de Docker + Portainer
│   └── cheatsheet-comandos.md # Chuleta de comandos Linux/Pi usados habitualmente
└── scripts/                  # Scripts de utilidad (monitorización, deploy, etc.)
```

## Cómo usar este repo

1. Copia `.env.example` a `.env` y rellena tus valores reales (nunca subas este archivo).
2. Los `docker-compose.yml` en `compose/` referencian variables `${...}` que Portainer o Docker Compose sustituyen en tiempo de despliegue.
3. La documentación en `docs/` explica el "por qué" de cada decisión, no solo el "qué".

## Aprendizajes clave

Ver [`docs/troubleshooting.md`](docs/troubleshooting.md) para el detalle, pero en resumen:

- `network_mode: host` rompe la resolución por nombre de contenedor en NPM → hay que apuntar a la IP del host.
- SQLite no aguanta bien la indexación concurrente de PhotoPrism en esta hardware (miniaturas, metadata y reconocimiento facial escribiendo a la vez) → migrado a MariaDB. Ver detalle abajo.
- Cloudflare con proxy (nube naranja) + HSTS activo en NPM = bucle de redirección. Solución: SSL Full (Strict) en Cloudflare y HSTS desactivado en NPM.
- Portainer solo acepta compose sin `build:` — las imágenes custom hay que construirlas antes por SSH.
- Bind mounts de **archivos individuales** (no carpetas) deben existir en el host antes del primer deploy, o Docker los crea como carpetas vacías y el contenedor falla. Ver el caso de Filebrowser abajo.
- Los servicios con imagen custom (como rpi-monitor) no se despliegan en un solo paso vía Portainer: la imagen hay que construirla antes por SSH, y solo entonces se pega el stack.

### Filebrowser: bind mounts de archivos individuales

Filebrowser monta `filebrowser.db` y `settings.json` como **archivos** individuales, no como una carpeta completa. Si no existen ya en el host cuando se levanta el contenedor, Docker los crea como **carpetas vacías** en su lugar (comportamiento estándar de bind mounts), y el contenedor arranca con configuración por defecto o falla directamente.

Por eso, antes del primer `docker compose up` / deploy en Portainer hay que crear los archivos vacíos a mano:

```bash
mkdir -p /mnt/hdd/filebrowser-config
touch /mnt/hdd/filebrowser-config/filebrowser.db
cat > /mnt/hdd/filebrowser-config/settings.json << 'EOF'
{
  "port": 80,
  "baseURL": "",
  "address": "",
  "log": "stdout",
  "database": "/database/filebrowser.db",
  "root": "/srv"
}
EOF
sudo chown -R "$USER":"$USER" /mnt/hdd/filebrowser-config
```

Esta misma regla aplica a cualquier futuro servicio que monte un archivo de config o una base de datos SQLite como volumen individual, no solo a Filebrowser.

### rpi-monitor: despliegue en varias fases

A diferencia del resto del stack (que se despliega pegando un compose directamente en Portainer, con imágenes públicas de Docker Hub), **rpi-monitor usa una imagen propia** construida a partir del código en `services/rpi-monitor/`. Portainer no puede construir imágenes desde su editor web (no acepta la directiva `build:` en el compose que se pega ahí), así que el despliegue tiene dos fases separadas:

1. **Build manual por SSH**, desde el directorio con el `Dockerfile`:
   ```bash
   cd services/rpi-monitor
   docker build -t rpi-monitor:latest .
   ```
2. **Deploy del stack en Portainer**, con un compose que referencia la imagen ya construida (sin `build:`, solo `image: rpi-monitor:latest`).

Además, como usa `network_mode: host` (ver punto anterior), NPM tiene que apuntar a la IP del host (`192.168.1.x:5000`) en vez de al nombre del contenedor, y Portainer no mostrará mapeo de puertos en su UI — es el comportamiento esperado con este modo de red, no un fallo.

### PhotoPrism: migración de SQLite a MariaDB

PhotoPrism usa SQLite por defecto: un único archivo, sin proceso de servidor separado. El problema es que SQLite solo permite **un escritor a la vez** — con el indexado en segundo plano (miniaturas, metadata EXIF, reconocimiento facial) escribiendo de forma concurrente, en un HDD externo sobre una Pi, esto provocaba bloqueos ("database is locked") y ralentizaba o colgaba la indexación.

**Importante:** esto no tiene relación con dónde viven los archivos de fotos. La carpeta de originales sigue montada igual desde Nextcloud (`/photoprism/originals`) y no se toca en ningún momento de la migración — la base de datos solo almacena el *índice* (miniaturas, metadata, caras), nunca las fotos en sí.

Pasos seguidos para migrar:

1. Añadir un servicio `photoprism-mariadb` (imagen `mariadb:11`) al mismo stack, con su propio volumen persistente.
2. Añadir las variables `PHOTOPRISM_DATABASE_DRIVER: mysql`, `PHOTOPRISM_DATABASE_SERVER`, `PHOTOPRISM_DATABASE_NAME`, `PHOTOPRISM_DATABASE_USER` y `PHOTOPRISM_DATABASE_PASSWORD` al servicio `photoprism`.
3. Redesplegar el stack — esto crea el esquema nuevo en MariaDB, vacío.
4. Forzar un reindexado completo de la librería desde **Library → Index** (o `docker exec -it photoprism photoprism index`), ya que la base nueva no trae histórico: PhotoPrism vuelve a leer los archivos originales (sin tocarlos) y reconstruye el índice desde cero.

Ver `compose/photoprism-mariadb.migration-example.yml` para la plantilla completa.
