# Troubleshooting y aprendizajes

Problemas reales encontrados montando este homelab, y cómo se resolvieron.

## `network_mode: host` y NPM

**Síntoma:** NPM no puede enrutar a un contenedor que usa `network_mode: host`; el nombre del contenedor no resuelve.

**Causa:** con `network_mode: host`, el contenedor no está en la red bridge de Docker donde NPM busca los nombres.

**Solución:** en NPM, usar la IP del host (`${PI_HOST_IP}`) y el puerto expuesto por la aplicación, en lugar del nombre del contenedor. Es normal que Portainer no muestre mapeos de puertos en la UI para estos contenedores.

## Referencias stale a directorios tras montar/remontar el HDD externo

**Síntoma:** un contenedor con bind mount al HDD externo (`/mnt/hdd`) se comporta como si el directorio estuviera vacío, aunque los archivos existen.

**Causa:** el contenedor arrancó antes de que el HDD terminara de montarse (o durante un remontaje), y quedó con una referencia obsoleta al punto de montaje.

**Solución:** reiniciar o recrear el contenedor después de confirmar que el HDD está montado correctamente.

## Portainer Stacks y contexto de build

**Síntoma:** al pegar un `docker-compose.yml` con una sección `build:` en el editor web de Portainer, falla o no construye correctamente.

**Causa:** el editor de stacks de Portainer no soporta build context.

**Solución:**
1. Construir la imagen manualmente por SSH: `docker build -t nombre:tag .`
2. Pegar el compose en Portainer **sin** la directiva `build:`, referenciando la imagen ya construida por su tag.

## Bucle de redirección con Cloudflare + NPM

**Síntoma:** al activar el proxy de Cloudflare (nube naranja) con HSTS activado en NPM, el navegador entra en un bucle infinito de redirecciones HTTPS.

**Solución:**
- Cloudflare SSL/TLS mode → **Full (Strict)**
- HSTS → **desactivado** en NPM

## SQLite y concurrencia en PhotoPrism

**Síntoma:** errores `database is locked` durante la indexación facial.

**Causa:** SQLite no está pensado para escrituras concurrentes intensivas, y los workers de reconocimiento facial de PhotoPrism generan justo ese patrón.

**Solución:** migrar el backend de base de datos a MariaDB (ver [services.md](services.md)).

## SCP desde Windows: comillas rotas por barra invertida final

**Síntoma:** un comando `scp` con una ruta de Windows entre comillas falla de forma extraña al parsear los argumentos.

**Causa:** una barra invertida justo antes de la comilla de cierre (`"C:\ruta\"`) escapa la comilla en vez de cerrarla, rompiendo el parsing del comando.

**Solución:** evitar la barra invertida final antes de la comilla de cierre, o usar rutas sin barra final.

## Permisos de archivo en discos externos (FAT32/NTFS vs ext4)

**Síntoma:** `chown` no tiene efecto en archivos de un HDD externo.

**Causa:** en sistemas de archivos FAT32/NTFS, `chown` no es aplicable — no soportan permisos POSIX de Unix.

**Solución:**
- FAT32/NTFS: remontar el disco con las opciones `uid=`/`gid=` para fijar el propietario a nivel de montaje.
- ext4: `sudo chown -R usuario:usuario /ruta` funciona de forma estándar.

## Cloudflare Access: el IdP no restringe por sí solo

**Síntoma:** cualquier cuenta de Google puede autenticarse en un servicio protegido con Cloudflare Access + Google OAuth, no solo la propia.

**Causa:** el proveedor de identidad (IdP) solo verifica *quién eres*, no *si tienes permiso*.

**Solución:** crear una política explícita de tipo "Allow" en Cloudflare Access con selectores de email concretos (o dominio de email) para restringir el acceso a las cuentas deseadas.
