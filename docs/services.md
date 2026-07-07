# Servicios

## PhotoPrism

Gestión de fotos con reconocimiento facial. Corre con backend SQLite por defecto.

**Problema conocido:** errores `database is locked` provocados por los workers de indexación facial ejecutándose en paralelo sobre SQLite. Este motor no está pensado para escrituras concurrentes intensivas en hardware tipo Raspberry Pi.

**Solución recomendada:** migrar a MariaDB como backend de base de datos. Ejemplo de servicio adicional en el stack:

```yaml
photoprism-db:
  image: mariadb:10.11
  restart: unless-stopped
  environment:
    MARIADB_AUTO_UPGRADE: "1"
    MARIADB_INITDB_SKIP_TZINFO: "1"
    MARIADB_DATABASE: photoprism
    MARIADB_USER: photoprism
    MARIADB_PASSWORD: ${PHOTOPRISM_DB_PASSWORD}
    MARIADB_ROOT_PASSWORD: ${PHOTOPRISM_DB_PASSWORD}
  volumes:
    - ./data/photoprism-db:/var/lib/mysql
  command: mariadbd --transaction-isolation=READ-COMMITTED --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --max-connections=512 --innodb-rollback-on-timeout=OFF --innodb-lock-wait-timeout=120
```

Y en el servicio `photoprism`, cambiar `PHOTOPRISM_DATABASE_DRIVER` de `sqlite` a `mysql`, apuntando a `photoprism-db`.

## Dashboard de monitorización (Flask + psutil)

App casera en Python/Flask que expone métricas del sistema (CPU, RAM, disco, temperatura) de la Raspberry Pi.

- Construida con Docker (`docker build`), desplegada como stack en Portainer.
- Usa `network_mode: host` — ver [architecture.md](architecture.md) para las implicaciones en NPM.
- Portainer solo acepta compose **sin** directiva `build:`. Flujo de trabajo:
  1. `docker build -t monitor-pi:latest .` por SSH directamente en la Pi.
  2. Pegar el `docker-compose.yml` en Portainer **sin** la línea `build:`, referenciando la imagen ya construida.

## code-server

Editor de código (VS Code en navegador) para editar las webs estáticas sin necesidad de SSH.

- Desplegado en el puerto `8085`.
- Estado: contenedor saludable (HTTP 302 al acceder directo al puerto), pero el acceso vía subdominio a través de NPM ha dado problemas — pendiente de verificar el enrutado NPM → contenedor y el estado del proxy de Cloudflare para ese subdominio en concreto.

## Webs estáticas del campamento (web-campamento / web-palantir)

Sitios HTML/CSS/JS vanilla (sin frameworks ni build tools), servidos cada uno con su propio contenedor `nginx:alpine`.

- Los archivos fuente viven en `/mnt/hdd/` en la Raspberry Pi.
- Flujo de despliegue: se editan/guardan localmente en Windows (con copia también en Nextcloud), y se suben por SCP.
- Ver [camp-project.md](camp-project.md) para más detalle.

## Otros servicios

- **Nextcloud:** almacenamiento en la nube personal.
- **Jellyfin:** streaming multimedia.
- **qBittorrent / MeTube:** descarga de contenido.
- **Pi-hole:** bloqueo de anuncios a nivel de DNS para toda la red local.
- **Portainer:** UI de gestión de Docker, unidad de despliegue preferida: stacks.
