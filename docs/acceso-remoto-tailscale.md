# Acceso remoto de rescate con Tailscale

Vía de acceso SSH a la Pi **independiente de Docker, Cloudflare, wg-easy y el bot de Telegram**, para poder diagnosticar y reiniciar cosas desde fuera de casa cuando algo se cae.

## Por qué

Antes, el único acceso remoto era el bot de Telegram (`docker-controller-bot`), que **corre en la propia Pi dentro de Docker**: si Docker, el contenedor o la Pi se cuelgan, el bot cae con ellos y no hay forma de entrar. La VPN `wg-easy` tiene el mismo problema (también es un contenedor).

Tailscale se instala **directamente en el sistema operativo**, fuera de Docker, así que sigue funcionando mientras la Pi esté encendida y con red, aunque todo lo demás esté caído.

| | wg-easy | Tailscale |
|---|---|---|
| Dónde corre | Contenedor Docker | Sistema (systemd) |
| Si Docker cae | ❌ sin acceso | ✅ sigue funcionando |
| Puertos en el router | UDP 51820 abierto | Ninguno |
| IP pública dinámica | Necesita DDNS | Indiferente |
| Afectado por bloqueos de Cloudflare | No | No |

Ambos conviven sin conflicto (`10.8.0.0/24` vs `100.64.0.0/10`). wg-easy se mantiene para dispositivos que no pueden instalar Tailscale (p. ej. un ESP32 como cliente WireGuard).

## Cómo funciona

Cada dispositivo recibe una IP privada `100.x.x.x` y un nombre (MagicDNS). El servidor de coordinación de Tailscale solo "presenta" a los dispositivos; el tráfico va **cifrado con WireGuard de extremo a extremo** y directo entre ellos (o por un relé DERP, también cifrado, si un NAT lo impide). Plan gratuito personal: 3 usuarios, 100 dispositivos.

Con **Tailscale SSH** (`--ssh`) el acceso se autoriza con la cuenta de Tailscale: no hacen falta contraseñas ni claves SSH, y solo aplica a conexiones que llegan por la tailnet (el SSH normal de la LAN no cambia).

## Instalación (Raspberry Pi OS bookworm)

0. Crear cuenta en <https://login.tailscale.com/start> (Google/Microsoft/GitHub) y **activar 2FA en esa cuenta**: quien entre en ella puede entrar en la Pi. Instalar el cliente en el PC (y opcionalmente en el móvil) con la misma cuenta.

1. En la Pi (por SSH en la LAN):

   ```bash
   cat /etc/os-release | head -3; uname -m       # bookworm, aarch64
   curl -fsSL https://tailscale.com/install.sh | sh
   ```

   > Si `apt-get update` falla con `changed its 'Suite' value from 'stable' to 'oldstable'`
   > (o `Label`/`Origin`/`Codename`), el instalador se para **sin instalar nada**.
   > Ver [troubleshooting](troubleshooting.md#apt-update-falla-con-changed-its-suite-value). Arreglarlo y luego:
   > `sudo apt-get install -y tailscale`.

2. Servicio activo y arrancando en cada boot:

   ```bash
   sudo systemctl enable --now tailscaled
   systemctl is-enabled tailscaled               # enabled
   tailscale version
   ```

3. Unir la Pi a la tailnet con SSH de Tailscale:

   ```bash
   sudo tailscale up --ssh --hostname=raspberry
   ```

   Abrir el enlace `https://login.tailscale.com/a/...` que imprime y autorizar el equipo. La sesión SSH actual (por LAN) no se corta.

   ```bash
   tailscale status
   tailscale ip -4
   ```

4. **Desactivar la caducidad de la clave** de la Pi: <https://login.tailscale.com/admin/machines> → `raspberry` → `⋯` → **Disable key expiry**. Si no, se desconecta a los ~180 días, justo cuando más falta haga.

## Conectarse

### Desde el PC (Windows/Linux/macOS)

Con el cliente de Tailscale conectado:

```bash
ssh ${PI_USER}@raspberry          # o ssh ${PI_USER}@100.x.x.x
```

Sin contraseña. Con la política por defecto, Tailscale SSH puede pedir reconfirmar la identidad en el navegador (modo *check*, cada ~12 h).

Atajo en `~/.ssh/config`:

```
Host pi
    HostName raspberry
    User ${PI_USER}
```

→ `ssh pi`

### Desde el móvil (opcional)

App de Tailscale activada + un cliente SSH (Termius, JuiceSSH): host `raspberry` (o la IP `100.x`), puerto 22, usuario `${PI_USER}`, contraseña vacía.

### Prueba real

Probar **desde fuera de casa** (PC conectado al punto de acceso del móvil, WiFi de casa apagado). Si entra ahí, el acceso de rescate funciona.

## Runbook: algo se ha caído

Una vez dentro (`ssh pi`), de menos a más drástico:

```bash
docker ps -a --format "table {{.Names}}\t{{.Status}}"   # qué está caído
docker logs --tail 50 NOMBRE                            # por qué
docker restart NOMBRE                                   # reiniciar un contenedor
df -h /mnt/hdd                                          # ¿el HDD está montado?
sudo systemctl restart docker                           # reiniciar todo Docker
sudo reboot                                             # reinicio ordenado de la Pi
```

Tras `sudo reboot`, esperar 1–2 min y volver a entrar: `tailscaled` arranca solo.

> `sudo reboot` cierra el sistema de archivos limpiamente. **Cortar la corriente es el último recurso**: puede corromper el HDD (ver [disaster-recovery.md](disaster-recovery.md)).

## Problemas habituales

| Síntoma | Solución |
|---|---|
| `Could not resolve hostname raspberry` | Usar la IP `100.x` o activar MagicDNS (panel → DNS). |
| Pide contraseña | Estás entrando por la LAN (`192.168.x.x`), no por la tailnet. Usa `raspberry` / `100.x`. |
| `Connection timed out` | Cliente de Tailscale desconectado en el PC o en la Pi (`tailscale status`). |
| `REMOTE HOST IDENTIFICATION HAS CHANGED` | `ssh-keygen -R raspberry` y reconectar. |
| `Permission denied` | La Pi no se unió con `--ssh`: repetir `sudo tailscale up --ssh --hostname=raspberry`. |

## Límite y siguientes capas

SSH solo sirve si **la Pi está viva**. Para cuando se cuelga del todo, capas pendientes:

1. **Watchdog de hardware** de la Pi: reinicio automático si el sistema se bloquea.
2. **autoheal**: reinicia contenedores que se quedan *unhealthy*.
3. **Botón de corriente remoto** (ESP32 + relé con su propio bot de Telegram, o enchufe inteligente): power-cycle de la Pi y del dock del HDD como último recurso.
