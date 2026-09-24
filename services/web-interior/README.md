# Web de tesorería · Juniors Zona Interior

Aplicación web para llevar toda la tesorería de la Zona Interior (Juniors M.D., Vicaría VI).
Misma base que `web-jefatura` (PHP + ficheros JSON, sin base de datos), con estas secciones:

| Sección | Qué hace | Documentos que imprime |
|---|---|---|
| **Movimientos** | Ingresos y gastos por partidas (las de *BALANCE 2026.xlsx*). Cada movimiento puede ir vinculado a una actividad y llevar proveedor, CIF y nº de factura. | CSV |
| **Balance** | Ficha de balance por año natural o curso escolar, con tartas y comentario. | Ficha balance (1 página A4) |
| **Actividades** | Presupuesto y balance de cada actividad, ingresos por centros, facturas para el informe y movimientos vinculados. | Presupuesto, balance (con desgloses tipo INTENDENCIA y comentario), ingresos por centros |
| **Transporte CJZ** | Consejos del curso (previstos / celebrados), asistencia de cada miembro con la tarifa según la sede, liquidaciones de pago. | Tabla del curso, hoja de pago con IBAN |
| **Cuota** | Gastos que cubre la cuota, cuota por centro (censo × precio), variación frente a la referencia, otros modelos, cobros. | Cálculo de la cuota, fichas de adjudicación |
| **Informe económico** | Por curso: ingresos, participación de la zona, gastos por tipo y control de facturas de cada proyecto. | Una hoja por proyecto |
| **Ajustes** | Centros y otros miembros (delegado), IBAN, cuenta de la zona, firmantes, copia de seguridad. | — |

### Cosas que se enlazan solas

- **Aportación de la Zona que cuadra**: en una línea de ingresos, ⚙ → «Cuadra el balance»: su importe es siempre
  *gastos − resto de ingresos* (como la APORTACIÓN ZONA INTERIOR del Excel).
- **Desglose**: ⚙ → «Añadir al desglose» convierte una línea (p. ej. INTENDENCIA) en la suma de sus tickets; en el PDF
  sale la tabla de desglose debajo.
- **Ingresos por centros**: «Usar en el balance» crea una línea cuya cantidad y precio salen de la tabla por centros.
- **Informe económico**: *ingresos por actividades* = ingresos del balance que no son de la Zona; *participación de la zona* =
  lo que falta para cubrir las facturas. Los dos se pueden escribir a mano.
- **Transporte → Movimientos**: «Apuntar gasto en Movimientos» crea el gasto G3.1 de la liquidación.
- **Cuota → Movimientos**: «Apuntar cobro» crea el ingreso I2.1 de cada centro.
- **Cuota ← Transporte**: «Poner el transporte CJZ previsto» calcula lo celebrado + lo previsto (si van todos).
- Un curso nuevo de cuota parte del anterior (precio, censo, gastos, y el anterior queda como referencia).
- En los comentarios, `**texto**` sale en negrita verde en el PDF.

## Datos

- `data/zona.json` — centros, sedes y tarifas, consejos, liquidaciones, cuotas y actividades.
- `data/tesoreria.json` — movimientos y comentarios del balance.
- `data/seed.json` — datos iniciales (solo se usan la primera vez que se abre la web, cuando aún no existen los otros dos).
  El `seed.json` actual se generó desde los Excel de 2025-2026: centros e IBAN, tarifas y consejos de
  *Transportes Zona Interior.xlsx*, cuota 2025-2026, las actividades del curso 2025-2026 y los movimientos de *BALANCE 2026.xlsx*.
  **Los movimientos no tenían fecha**: los vinculados a una actividad llevan la fecha de la actividad y el resto el 01/01/2026. Revísalas.

Todos los `.json` de `data/` y `includes/config.php` están en `.gitignore` y el script de subida nunca los envía.

## Probar en el ordenador

Doble clic en `iniciar-local.bat` → http://localhost:8001 (contraseña: la de `includes/config.php`).

## Instalar en la Raspberry (Docker / Portainer)

1. **La primera vez**: doble clic en `primera-instalacion.bat`. Copia el código, `data/seed.json` y `includes/config.php`
   a `/mnt/hdd/webs/web-interior` y ajusta permisos (aborta si allí ya hay datos).
   ⚠️ Hazlo **antes** o justo después de desplegar el stack: si el stack arranca con la carpeta vacía, sale *unhealthy*
   (404 en `login.php`) hasta que se copian los archivos.
2. En la Pi, pon una contraseña de verdad: `sudo nano /mnt/hdd/webs/web-interior/includes/config.php`
3. Portainer → Stacks → Add stack → pega `docker-compose.yml` (puerto `8092`) y despliega.
4. Comprueba que `http://IP:8092/data/zona.json` da **403/404**.

Después, para actualizar el código: doble clic en `subir-a-raspberry.bat` (no toca `data/` ni `config.php`,
hace copia antes en `~/copias-web-interior/`).

## Copias de seguridad

Ajustes → **Exportar copia** descarga un único `.json` con todo (zona + tesorería). **Importar** lo sustituye todo
(y deja una copia `.bak` de lo anterior en `data/`).

## Estructura

```
index.php                 Inicio
tesoreria.php / balance.php / api-tesoreria.php   Movimientos y ficha de balance
actividades.php / actividad.php / doc-actividad.php
transporte.php / doc-transporte.php
cuota.php / doc-cuota.php / fichas-cuota.php
informe.php / doc-informe.php
ajustes.php
api.php                   API de todo lo que no son movimientos (+ exportar / importar)
includes/
  config.php              contraseña y curso (no se sube)
  data.php                lectura/escritura JSON y utilidades
  partidas.php            partidas de la Zona
  tesoreria.php           movimientos y balance
  zona.php                centros, transporte, cuota, actividades (cálculos y validación)
  doc.php                 piezas de los documentos imprimibles
  layout.php / layout_fin.php
assets/css/style.css      (al cambiar css/js, sube VERSION_ESTATICOS en includes/layout.php)
assets/js/*.js
```
