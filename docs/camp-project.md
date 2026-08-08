# Proyecto: webs del campamento juvenil

Proyecto paralelo al homelab: dos sitios estáticos con temática de fantasía épica para un campamento juvenil.

## Sitios

- **web-campamento:** sitio público de cara a las familias/participantes (`campamento.${DOMAIN}`).
- **web-palantir:** sitio interno de organización/staff (`palantir.${DOMAIN}`), con optimización para móvil aplicada.

## Stack técnico

- HTML/CSS/JS vanilla — sin frameworks ni build tools, deliberadamente.
- **web-campamento:** contenedor `nginx:alpine`, 100% estático.
- **web-palantir:** contenedor `node:20-alpine` (migrado desde `nginx:alpine`) con un backend Express mínimo, porque necesita un endpoint de subida de archivos además de servir el sitio.
- Los archivos se almacenan en `/mnt/hdd/` en la Raspberry Pi.

## Flujo de trabajo

1. Edición local en Windows (con copia de seguridad en Nextcloud).
2. Subida a la Raspberry Pi por SCP (`C:\Windows\System32\OpenSSH\scp.exe`).
3. Ambos contenedores montan esos directorios como bind mount y sirven el contenido directamente — no hace falta reiniciar el contenedor tras cambios en archivos estáticos (HTML/CSS/imágenes/fuentes). Solo hay que reiniciar/redesplegar el contenedor de `web-palantir` si cambia el código del backend (`backend/server.js`, `package.json`).
4. Tras cada SCP, hay que asegurar permisos de lectura para el usuario con el que corre el contenedor, o las imágenes/fuentes no se sirven correctamente — ver el apartado de permisos en [troubleshooting.md](troubleshooting.md).

## Backend de subida de documentos (web-palantir)

`web-palantir` pasó de ser un sitio 100% estático a incluir un backend Express pequeño (`backend/server.js`), porque el apartado "Documentación" del sitio necesita que el staff pueda subir certificados en PDF directamente desde el navegador.

- **Endpoint:** `POST /api/upload/:categoria` (Multer, almacenamiento en memoria, límite 10 MB, solo `application/pdf`).
- **Categorías:** definidas en un objeto `CATEGORIES` en `server.js` — cada una mapea a una subcarpeta y un prefijo de nombre de archivo. Añadir una categoría nueva es añadir una entrada ahí, no hace falta tocar más código.
- **Categoría actual:** `delitos-sexuales` → certificados de delitos de naturaleza sexual, guardados como `certificadoDS Nombre Apellido.pdf` (con sufijo `(2)`, `(3)`... si ya existe un archivo con ese nombre).
- **Almacenamiento:** los PDFs se guardan en `/mnt/hdd/webs/web-palantir-uploads`, montado como volumen aparte de solo-escritura para el backend — separado del contenido del sitio (que se sirve de solo lectura).
- El resto del sitio se sigue sirviendo estático vía `express.static`, montado de solo lectura (`/site`), y el backend bloquea explícitamente el acceso público a `/backend` y a `docker-compose.yml` (viven en el mismo volumen que el código del sitio).

## Pendiente / en el horizonte

- Backend propio para el formulario de inscripciones (Node.js + Express en Docker), con subdominio dedicado (ej. `inscripciones.${DOMAIN}`) y almacenamiento de envíos en SQLite o archivo con volumen persistente. (Proyecto distinto del backend de subida de documentos de `web-palantir` descrito arriba.)
