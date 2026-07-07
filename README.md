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
│   ├── web-campamento.yml
│   ├── web-palantir.yml
│   └── wg-easy.yml
├── services/
│   └── rpi-monitor/           # Dashboard de monitorización (Flask + psutil), código completo
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
- SQLite no aguanta bien la indexación facial concurrente de PhotoPrism en esta hardware → migrar a MariaDB.
- Cloudflare con proxy (nube naranja) + HSTS activo en NPM = bucle de redirección. Solución: SSL Full (Strict) en Cloudflare y HSTS desactivado en NPM.
- Portainer solo acepta compose sin `build:` — las imágenes custom hay que construirlas antes por SSH.
