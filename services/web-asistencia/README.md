# Web de asistencia · Juniors Axenia 603 D

Aplicación web para llevar el control de asistencia de **educadores y colaboradores**
a las actividades del curso.

- **Personas**: lista de educadores y colaboradores (se puede añadir, editar, dar de baja).
- **Actividades**: se crean a mano; para cada una se marca quién ha asistido.
- **Resumen**: por cada persona, actividades totales que le corresponden, asistidas y porcentaje.

Diseño basado en la identidad de Juniors M.D. (verdes), con los logos de
Juniors Axenia 603 D, Projecte Xaipe y la Parroquia Asunción de Nuestra Señora (Ayora).

---

## 0. Opción rápida: Docker / Portainer (recomendada)

Hay un `docker-compose.yml` listo. En Portainer: **Stacks → Add stack →**
pega el contenido del fichero, ajusta el puerto (`8088:80` por defecto) y despliega.

Antes, en la Pi, en `/mnt/hdd/webs/web-asistencia`:

```bash
# 1) crear la config real y la lista real a partir de las plantillas
cp includes/config.example.php includes/config.php   # y editar la contraseña
cp data/seed.example.json      data/seed.json        # y poner la lista real

# 2) permisos: el contenedor (www-data = uid 33) tiene que poder leer todo
sudo chmod -R a+rX .
sudo chown -R 33:33 data
sudo chmod -R 775   data
```

El contenedor usa la imagen oficial `php:8.3-apache`, activa `AllowOverride All`
(para que el `.htaccess` bloquee `data/` e `includes/`) y trae un *healthcheck*
para que Portainer muestre si sigue vivo. Los datos persisten en
`/mnt/hdd/webs/web-asistencia/data/` (la carpeta va montada en el contenedor).

Para actualizar la web: copiar los archivos nuevos y reiniciar el contenedor.

El resto de este README es la instalación **sin Docker** (Apache/nginx directos).

---

## 1. Qué necesita la Raspberry Pi

Un servidor web con **PHP 7.4 o superior** (probado pensando en PHP 8.x, que es
lo que trae Raspberry Pi OS actual). Sirve con Apache o con nginx + php-fpm.

No hace falta base de datos: **todo se guarda en `data/data.json`**.

### Instalar (si aún no está)

```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php
```

## 2. Instalación

1. Copia toda la carpeta `web-asistencia` al sitio donde sirves las webs, por
   ejemplo `/var/www/asistencia`:

   ```bash
   sudo cp -r web-asistencia /var/www/asistencia
   ```

2. Da permisos de escritura a la carpeta `data/` al usuario del servidor web
   (normalmente `www-data`):

   ```bash
   sudo chown -R www-data:www-data /var/www/asistencia/data
   sudo chmod 775 /var/www/asistencia/data
   ```

   El resto de archivos pueden quedarse como solo lectura.

3. Crea `includes/config.php` a partir de la plantilla y **pon tu contraseña**:

   ```bash
   cp includes/config.example.php includes/config.php
   ```
   ```php
   const APP_PASSWORD = 'CAMBIA_ESTA_CONTRASENA';   // <-- tu contraseña
   ```

   (`config.php` está en `.gitignore`: no se sube al repo.)

4. Crea `data/seed.json` a partir de `data/seed.example.json` y pon la lista
   real de educadores y colaboradores (o empieza con la de ejemplo y edítala
   desde la propia web). `data/seed.json` y `data/data.json` también están
   en `.gitignore`.

5. Abre la web en el navegador. La primera vez se crea `data/data.json` a
   partir de `data/seed.json`.

6. **Comprobación de seguridad** (importante): con el navegador, entra a
   `http://TU-WEB/data/data.json`. Debe dar **403 o 404**, nunca mostrar el
   contenido. Si se ve el JSON, Apache no está aplicando el `.htaccess`
   (revisa `AllowOverride All`) o, en nginx, faltan las reglas `location` de
   abajo. Mientras se vea, cualquiera puede descargar la lista de nombres.

### Apache: servirla en una subcarpeta o subdominio

- **Subcarpeta** (`http://tu-raspberry/asistencia`): con un enlace simbólico
  dentro de tu DocumentRoot, o moviendo la carpeta ahí. El `.htaccess` incluido
  ya bloquea el acceso directo a `data/` e `includes/`. Necesitas
  `AllowOverride All` en la config de Apache para ese directorio.

- **VirtualHost propio** (recomendado):

  ```apache
  <VirtualHost *:80>
      ServerName asistencia.midominio.org
      DocumentRoot /var/www/asistencia
      <Directory /var/www/asistencia>
          AllowOverride All
          Require all granted
      </Directory>
  </VirtualHost>
  ```

### nginx: bloquear datos (el .htaccess NO se aplica en nginx)

Añade dentro del `server { ... }`:

```nginx
location ~ ^/(data|includes)/ { deny all; return 404; }
location ~ \.json$           { deny all; return 404; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## 3. Probarla en el ordenador antes de subirla

Con PHP instalado en el PC, desde la carpeta `web-asistencia`:

```bash
php -S localhost:8000
```

y abre `http://localhost:8000`. (El servidor de pruebas de PHP no aplica
`.htaccess`, pero el API ya comprueba la sesión en cada petición.)

## 4. Uso

1. **Entrar**: se pide la contraseña una vez por navegador.
2. **Personas**: revisa la lista. El botón *Ver bajas* muestra a quien se dio
   de baja (su historial se conserva). Clic en un nombre para editarlo.
3. **Actividades**: rellena *nombre*, *fecha* y *para qué grupo cuenta*
   (solo educadores / solo colaboradores / ambos) y pulsa **Crear actividad**.
   Luego, en la tarjeta de la actividad, **Marcar asistencia**, marca las
   casillas de quien asistió y **Guardar asistencia**.
4. **Resumen**: tabla con asistidas / totales / porcentaje por persona.
   Se puede ordenar por cualquier columna, filtrar por categoría y
   **Exportar CSV** (se abre bien en Excel/LibreOffice).

### Cómo se calcula el porcentaje

Para cada persona, el total son las actividades **que le corresponden según su
categoría**:

- Un **educador** cuenta las actividades de ámbito *educadores* y *ambos*.
- Un **colaborador** cuenta las de ámbito *colaboradores* y *ambos*.

`porcentaje = asistidas / totales que le corresponden × 100`

## 5. Copias de seguridad

- Todo está en `data/data.json`. Haz copia de ese archivo cuando quieras
  (o cópialo con la Nextcloud).
- Dentro de la web, en **Inicio → Copia de seguridad**:
  - **Exportar copia (.json)**: descarga el estado actual.
  - **Importar copia…**: **sustituye** todos los datos por los del archivo
    (úsalo solo para restaurar).

## 6. Estructura de archivos

```
web-asistencia/
├── index.php            Inicio (resumen rápido + copias de seguridad)
├── actividades.php      Crear actividades y marcar asistencia
├── personas.php         Gestión de educadores y colaboradores
├── resumen.php          Resumen de asistencia por persona
├── login.php / logout.php
├── api.php              API JSON (todas las lecturas/escrituras)
├── includes/
│   ├── config.example.php   Plantilla → copiar a config.php (CONTRASEÑA y curso)
│   ├── config.php           (git-ignored) config real, solo en la Pi
│   ├── auth.php             Sesión y token de seguridad (CSRF)
│   ├── data.php             Lectura/escritura de data.json (con bloqueo)
│   └── layout.php / layout_fin.php   Cabecera y pie comunes
├── data/
│   ├── seed.example.json    Plantilla → copiar a seed.json
│   ├── seed.json            (git-ignored) lista real de personas
│   └── data.json            (git-ignored) se crea al primer uso. NO se sirve por web.
└── assets/
    ├── css/style.css
    ├── js/{app,personas,actividades,resumen}.js
    └── img/  (logos y favicon)
```

## 7. Notas

- Cuando cambies un `.css` o `.js`, sube también el número de versión `?v=` en
  `includes/layout.php` y `includes/layout_fin.php` para que los navegadores
  recarguen la versión nueva.
- La protección por contraseña es **básica** (una clave compartida): sirve para
  que no entre cualquiera, no es seguridad fuerte. Ponla detrás de HTTPS si la
  publicas en internet.
- `meta robots noindex` está puesto en todas las páginas para que no la
  indexen los buscadores.
