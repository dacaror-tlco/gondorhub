# RPi Monitor

Dashboard web ligero para monitorizar tu Raspberry Pi: CPU (global y por núcleo),
frecuencia, RAM, swap, disco(s), red, temperatura, load average, uptime y número
de procesos. Se actualiza cada 2 segundos vía AJAX, sin dependencias externas
(no usa CDN, todo el HTML/CSS/JS va embebido en la propia app).

## Requisitos

- Docker y Docker Compose instalados en la Raspberry Pi (`sudo apt install docker.io docker-compose-plugin`, o el método que prefieras).

## Uso

```bash
# Copia esta carpeta a tu Raspberry Pi, entra en ella y ejecuta:
docker compose up -d --build
```

Luego abre en el navegador:

```
http://<ip-de-tu-raspberry>:5000
```

Como el contenedor usa `network_mode: host`, no hace falta mapear puertos:
el servicio queda escuchando directamente en el puerto 5000 de la Raspberry Pi.

## Por qué monta /proc, /sys y / del host

Por defecto, un contenedor ve su propio namespace y no las métricas reales del
host. Para que `psutil` reporte CPU, RAM y disco reales de la Raspberry Pi (y
no del contenedor), montamos:

- `/proc` del host → `/host/proc` (solo lectura)
- `/sys` del host → `/host/sys` (solo lectura, usado para leer la temperatura)
- `/` del host → `/host/rootfs` (solo lectura, usado para el disco raíz)

Todos los montajes son de solo lectura (`:ro`), así que el contenedor no puede
modificar nada del sistema host.

## Temperatura

Se lee directamente de `/sys/class/thermal/thermal_zone0/temp`, que no requiere
privilegios especiales. Funciona en cualquier Raspberry Pi con Raspberry Pi OS
o similar.

## Estado de throttling (opcional, avanzado)

El estado de "throttled" (limitación por voltaje/temperatura, típico de la Pi)
se obtiene con `vcgencmd get_throttled`, pero esta herramienta solo funciona
si el contenedor tiene acceso al dispositivo `/dev/vchiq` y corre en modo
`privileged`. Si quieres activarlo, descomenta esas líneas en
`docker-compose.yml`. Si no está disponible, el dashboard simplemente no
muestra ese dato (sin errores).

## Detener / eliminar

```bash
docker compose down
```

## Estructura

```
rpi-monitor/
├── app.py              # Backend Flask + psutil y la interfaz web embebida
├── Dockerfile
├── docker-compose.yml
├── requirements.txt
└── README.md
```

## Personalización rápida

- Cambiar el intervalo de refresco: en `app.py`, busca `setInterval(refresh, 2000)`
  dentro del HTML embebido y cambia el valor en milisegundos.
- Cambiar el puerto: modifica `app.run(host="0.0.0.0", port=5000)` en `app.py`
  y ajusta el `docker-compose.yml` si dejas de usar `network_mode: host`.
