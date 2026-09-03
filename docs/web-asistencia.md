# Proyecto: web de asistencia (Juniors Axenia 603 D)

App interna para llevar la asistencia de **educadores y colaboradores** de un
grupo juvenil a las actividades del curso. Sustituye a una hoja de Excel de
"recuento" que se llevaba a mano.

- **Subdominio:** `asistencia.${DOMAIN}` (vía Nginx Proxy Manager → `${PI_HOST_IP}:8088`).
- **Código:** [`services/web-asistencia/`](../services/web-asistencia/) · **Compose:** [`compose/web-asistencia.yml`](../compose/web-asistencia.yml)

## Qué hace

| Sección | Función |
|---|---|
| Personas | Alta/edición/baja de educadores y colaboradores (dos categorías). |
| Actividades | Se crean a mano (nombre, fecha, **ámbito**: educadores / colaboradores / ambos) y se marca quién asistió. |
| Resumen | Por persona: actividades que le corresponden, asistidas y % (con exportación a CSV). |

El **porcentaje** de cada persona se calcula sobre las actividades que le
aplican por su categoría: un educador cuenta las de ámbito *educadores* + *ambos*;
un colaborador, las de *colaboradores* + *ambos*.

## Stack técnico

- **PHP 8.3 + Apache** (imagen oficial `php:8.3-apache`), **sin base de datos**:
  todo el estado vive en un único `data/data.json` (escritura atómica con
  `flock` + rename). Elegido así por simplicidad y por lo pequeño del dataset
  (~50 personas, decenas de actividades al año).
- Frontend vanilla (sin build): las páginas cargan el estado desde `api.php`
  (JSON) y renderizan en el navegador.
- **Auth:** contraseña única compartida en `includes/config.php` + sesión PHP +
  token CSRF en las peticiones de escritura. Protección básica; va detrás de
  HTTPS (NPM).
- `.htaccess` bloquea el acceso web a `data/` e `includes/`. El `command:` del
  contenedor pone `AllowOverride All` para que ese `.htaccess` surta efecto
  (la imagen viene con `AllowOverride None`).
- **Healthcheck** en el compose (pide `login.php`), para que Portainer marque
  el contenedor como *healthy/unhealthy*.

## Archivos que NO van a git

`includes/config.php` (contraseña real) y `data/seed.json` + `data/data.json`
(nombres reales de las personas, muchas menores). Están en el `.gitignore` del
servicio. En el repo solo hay plantillas: `config.example.php` y
`seed.example.json`. El `.gitignore` raíz ya ignora `data/` entera; el
`.gitignore` del servicio re-incluye solo `data/.htaccess` y `data/seed.example.json`.

## Flujo de trabajo

1. Edición local en Windows (copia de trabajo en Nextcloud).
2. Subida a la Pi por SCP a `/mnt/hdd/webs/web-asistencia`.
3. En la Pi, la primera vez: `cp includes/config.example.php includes/config.php`
   (y editar contraseña) y `cp data/seed.example.json data/seed.json` (lista real).
4. Contenedor gestionado como **stack en Portainer**. Bind mount de la carpeta
   entera → cambios en `.php/.css/.js` no requieren reinicio; solo se
   redespliega si cambia `docker-compose.yml`.
5. Copia de seguridad: basta con guardar `data/data.json` (hay botón de
   exportar/importar dentro de la web, y se puede cronificar un `cp`).

## Permisos (gotcha)

Apache dentro del contenedor corre como `www-data` = **uid 33**. Tras cada SCP
como usuario normal, hay que dejar el árbol legible y `data/` escribible por el 33:

```bash
sudo chmod -R a+rX /mnt/hdd/webs/web-asistencia
sudo chown -R 33:33 /mnt/hdd/webs/web-asistencia/data
sudo chmod -R 775   /mnt/hdd/webs/web-asistencia/data
```

Si no, Apache devuelve `403` con
`pcfg_openfile: unable to check htaccess file` en el log y el healthcheck marca
el contenedor *unhealthy*. Ver también [troubleshooting.md](troubleshooting.md).

## Comprobación post-despliegue

```bash
curl -I https://asistencia.${DOMAIN}/login.php       # 200
curl -I https://asistencia.${DOMAIN}/data/data.json  # 403  (lo bloquea el .htaccess)
```

## Pendiente / en el horizonte

- Registro de asistencia con varias personas a la vez desde móvil (ya es
  responsive, pero se podría pulir el flujo de "pasar lista").
- Histórico por cursos (ahora `data.json` es de un curso; para archivar, copiar
  el fichero y empezar limpio).
