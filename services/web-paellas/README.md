# Paellas Night · Centro Juniors Axenia

Web para **gestionar la reserva de tickets** de la Paellas Night y evitar que todo vaya por
llamada. Cada ticket son **8 €** (paella + bebida + fruta) y se pagan y recogen en el
Centro Juniors Axenia el día del evento.

- **Página pública** (`/`): información del evento + formulario de reserva (nombre y cantidad,
  teléfono opcional). Al enviar, la persona recibe un **código** que enseña al recoger.
- **Panel privado** (`/admin`): resumen (nº de reservas, tickets, dinero previsto y cobrado)
  y la lista completa, con casillas de *Pagado* / *Entregado*, alta manual, edición, borrado,
  exportar a CSV e imprimir la lista para la puerta. Se protege con **Cloudflare Access**.

Sin frameworks ni dependencias: solo **Node.js ≥ 18** (probado con Node 20/24). Los datos se
guardan en un fichero `data/reservas.json` fácil de copiar y respaldar.

---

## 1. Probar en local

```bash
node server.js
```

- Público: <http://localhost:3000/>
- Panel:   <http://localhost:3000/admin>

`npm start` hace lo mismo. `npm install` no instala nada (no hay dependencias).

---

## 2. Editar los textos del evento

Todo lo editable está en **`config.json`** (el servidor lo relee en cada visita, no hace falta
reiniciar):

| Campo | Para qué |
|---|---|
| `nombreEvento`, `subtitulo`, `tagline` | Cabecera |
| `fechaTexto`, `hora` | Fecha y hora que se muestran |
| `lugar`, `direccion`, `mapsUrl` | Ubicación y enlace a Google Maps |
| `incluye` | Lista de lo que incluye el ticket (`["Paella","Bebida","Fruta"]`) |
| `precioTicket` | Precio por ticket en € (ahora `8`) |
| `maxTicketsPorReserva` | Tope de tickets por reserva desde la web |
| `telefonoContacto` | **Número de contacto** (ahora es un marcador `6XX XX XX XX`, cámbialo) |
| `aforo` | `null` = sin límite; un número = deja de aceptar reservas al llegar a ese total de tickets |
| `reservasAbiertas` | `false` para cerrar el formulario (muestra un aviso con el teléfono) |
| `mensajeCerrado` | Texto de ese aviso |

> El **precio se valida también en el servidor**: aunque alguien manipule la página, el total y
> el CSV siempre usan `precioTicket` de `config.json`.

---

## 3. Puesta en producción (Raspberry Pi + Cloudflare)

### 3.1 Copiar el proyecto a la Pi

Por `scp`, `git`, USB o carpeta compartida. Por ejemplo a `/home/pi/paellas-night`.
No copies la carpeta `data/` si ya tienes reservas reales y no quieres pisarlas.

### 3.2 Node.js en la Pi

```bash
node -v      # si es < 18, instala una versión nueva
# opción sencilla con nvm:
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
# reabre la terminal
nvm install 20
```

Prueba: `HOST=127.0.0.1 node server.js` y abre <http://localhost:3000> en la propia Pi.

### 3.3 Arrancar como servicio (systemd)

```bash
sudo cp /home/pi/paellas-night/deploy/paellas-night.service /etc/systemd/system/
sudo nano /etc/systemd/system/paellas-night.service   # revisa WorkingDirectory, User y ruta de node (which node)
sudo systemctl daemon-reload
sudo systemctl enable --now paellas-night
systemctl status paellas-night
```

El servicio escucha solo en `127.0.0.1:3000` (nadie de la red local llega directo; solo Cloudflare).

### 3.4 Túnel de Cloudflare

```bash
# instala cloudflared (paquete de Cloudflare para arm64/armhf)
cloudflared tunnel login
cloudflared tunnel create paellas-night
# copia el config de ejemplo y edítalo con tu TUNNEL-ID y tu dominio:
cp /home/pi/paellas-night/deploy/cloudflared-config.example.yml ~/.cloudflared/config.yml
nano ~/.cloudflared/config.yml
cloudflared tunnel route dns paellas-night paellas.tudominio.com
sudo cloudflared service install
sudo systemctl enable --now cloudflared
```

Ya deberías ver la web pública en `https://paellas.tudominio.com`.

### 3.5 Proteger el panel con Cloudflare Access (¡importante!)

En **Cloudflare Zero Trust → Access → Applications → Add an application → Self-hosted**:

1. **Application domain**: `paellas.tudominio.com`, y añade **dos rutas (Paths)**:
   `admin` y `api/admin`.
   *(La parte pública `/` y `/api/reservas` se quedan abiertas para todo el mundo.)*
2. **Policy**: *Allow* → *Include* → *Emails* → tu correo (`dcarpioortiz@gmail.com`).
3. Guardar.

Ahora `/admin` te pide iniciar sesión con tu correo y nadie más lo ve.

### 3.6 (Opcional) Token extra de aplicación

Como cinturón y tirantes, puedes exigir además un token propio:

- Descomenta `Environment=ADMIN_TOKEN=...` en el `.service` con un valor largo y aleatorio.
- `sudo systemctl daemon-reload && sudo systemctl restart paellas-night`.
- La primera vez que entres en `/admin` te pedirá ese token (se guarda en el navegador).

---

## 4. El día del evento

- Abre `/admin` en el móvil o en un portátil en la puerta.
- Busca por nombre o código, marca **Pagado** y **Entregado** al entregar los tickets.
- **Imprimir lista** saca una hoja limpia para ir tachando a mano si falla el wifi.
- **Exportar CSV** para llevar la cuenta después (se abre bien en Excel/LibreOffice).
- La tabla se refresca sola cada 20 s, así varias personas pueden mirar a la vez.

---

## 5. Copias de seguridad

Todo está en **`data/reservas.json`** (y una copia automática en `data/reservas.json.bak`).
Para respaldar, copia ese fichero. Para restaurar, párale el servicio, sustituye el fichero y
vuelve a arrancar.

---

## 6. Actualizar la web

Sustituye los ficheros del proyecto (menos `data/`) y reinicia:

```bash
sudo systemctl restart paellas-night
```

---

## Estructura

```
server.js          Servidor HTTP (sin dependencias)
config.json        Textos y precio del evento (editable en caliente)
public/            Web pública + panel
  index.html  app.js
  admin.html  admin.js
  styles.css
  assets/patxi.png   assets/paella.svg
data/              reservas.json (se crea solo)
deploy/            unit de systemd + ejemplo de cloudflared
```
