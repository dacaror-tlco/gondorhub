# Proyecto: web de tesorería de la Zona Interior (Juniors M.D.)

App interna para llevar toda la tesorería de la Zona Interior (Vicaría VI), que
sustituye a varios Excel: *BALANCE*, presupuestos/balances de actividades,
*Transportes Zona Interior*, *CUOTA ZONA* + fichas de adjudicación e *Informe Económico*.
Misma base técnica que [web-jefatura](web-jefatura.md).

- **Subdominio:** `interior.${DOMAIN}` (vía Nginx Proxy Manager → `${PI_HOST_IP}:8092`).
- **Código:** [`services/web-interior/`](../services/web-interior/) · **Compose:** [`compose/web-interior.yml`](../compose/web-interior.yml)

## Qué hace

| Sección | Función | Imprime |
|---|---|---|
| Movimientos | Ingreso/gasto por partidas de la Zona (I2.1 Cuota Zona, G2.1 Cuota diocesana…), vinculables a una actividad, con proveedor/CIF/nº factura. | CSV |
| Balance | Ficha de balance por año natural o curso escolar (grupos, %, tartas, comentario). | Ficha A4 |
| Actividades | Presupuesto y balance (líneas con desglose, línea de aportación de la Zona que cuadra sola), ingresos por centros, facturas y movimientos vinculados. | Presupuesto, balance, ingresos por centros |
| Transporte CJZ | Consejos (previstos/celebrados) × miembros; importe según tarifa de la sede; liquidaciones que se apuntan como gasto G3.1. | Tabla del curso, hoja de pago con IBAN |
| Cuota | Gastos a cubrir, censo × precio por centro, variación, modelos alternativos, cobros (ingreso I2.1). | Cálculo, fichas de adjudicación |
| Informe económico | Por curso: ingresos, participación de la zona, gastos por tipo y control de facturas de cada proyecto. | Una hoja por proyecto |
| Ajustes | Centros y otros miembros (delegado), IBAN, cuenta de la zona, firmantes, copia de seguridad. | — |

## Stack técnico

- **PHP 8.3 + Apache** (`php:8.3-apache`), **sin base de datos**: `data/zona.json`
  (centros, transporte, cuotas, actividades) y `data/tesoreria.json` (movimientos),
  con escritura atómica (`flock` + rename). La primera vez se crean desde `data/seed.json`.
- Frontend vanilla sin build; los editores guardan solos (autoguardado con aviso de estado).
  Los documentos imprimibles se generan en PHP con CSS de impresión (A4, apaisado donde hace falta).
- **Auth:** contraseña única en `includes/config.php` + sesión PHP + token CSRF.
- `.htaccess` bloquea `data/` e `includes/`; el `command:` del compose activa `AllowOverride All`.
- **Healthcheck** sobre `login.php`.

## Archivos que NO van a git

`includes/config.php`, todo `data/*.json` salvo `seed.example.json` (el `seed.json` real lleva
IBAN de los centros) y los `.bat` locales (`primera-instalacion.bat`, `subir-a-raspberry.bat`,
`iniciar-local.bat`), que llevan usuario/IP de la Pi.

## Flujo de trabajo

1. Edición local en Windows (copia de trabajo en Nextcloud), prueba con `iniciar-local.bat` (puerto 8001).
2. **Primera vez:** `primera-instalacion.bat` copia código + `seed.json` + `config.php` a
   `/mnt/hdd/webs/web-interior` y ajusta permisos (aborta si ya hay datos). Luego desplegar el stack.
3. **Actualizaciones:** `subir-a-raspberry.bat` (solo código; copia de `data/` en `~/copias-web-interior/`).
4. Bind mount de la carpeta: cambios en `.php/.css/.js` sin reiniciar.

## Problemas conocidos

- **Unhealthy con 404 en `login.php`** y 403 en `/` (*No matching DirectoryIndex*): la carpeta
  montada está vacía (se desplegó el stack antes de copiar la web). Ejecutar `primera-instalacion.bat`;
  no hace falta reiniciar el contenedor.
- **403 con `pcfg_openfile`**: permisos; ver [web-jefatura.md](web-jefatura.md#permisos-gotcha).
