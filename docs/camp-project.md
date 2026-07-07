# Proyecto: webs del campamento juvenil

Proyecto paralelo al homelab: dos sitios estáticos con temática de fantasía épica para un campamento juvenil.

## Sitios

- **web-campamento:** sitio público de cara a las familias/participantes (`campamento.${DOMAIN}`).
- **web-palantir:** sitio interno de organización/staff (`palantir.${DOMAIN}`), con optimización para móvil aplicada.

## Stack técnico

- HTML/CSS/JS vanilla — sin frameworks ni build tools, deliberadamente.
- Cada sitio se sirve con su propio contenedor `nginx:alpine`.
- Los archivos se almacenan en `/mnt/hdd/` en la Raspberry Pi.

## Flujo de trabajo

1. Edición local en Windows (con copia de seguridad en Nextcloud).
2. Subida a la Raspberry Pi por SCP (`C:\Windows\System32\OpenSSH\scp.exe`).
3. Los contenedores `nginx:alpine` montan esos directorios como bind mount y sirven el contenido directamente — no hace falta reiniciar el contenedor tras cada cambio de archivos estáticos.

## Pendiente / en el horizonte

- Backend propio para el formulario de inscripciones (Node.js + Express en Docker), con subdominio dedicado (ej. `inscripciones.${DOMAIN}`) y almacenamiento de envíos en SQLite o archivo con volumen persistente.
