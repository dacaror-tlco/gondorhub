# Instalación de Docker y Portainer en Raspberry Pi

## Docker y Docker Compose

Fuente: https://docs.docker.com/engine/install/debian/

```bash
# Instalar Docker y Docker Compose
sudo curl -fsSL https://get.docker.com/ -o get-docker.sh
sudo sh get-docker.sh

# Añadir el usuario al grupo docker (para no depender de sudo)
sudo usermod -aG docker ${USER}

# Reiniciar para que el cambio de grupo surta efecto
sudo reboot

# Contenedor de prueba
docker run hello-world
```

## Portainer

Fuente: https://docs.portainer.io/start/install-ce/server/docker/linux

```bash
# Crear el volumen donde Portainer guarda sus datos
docker volume create portainer_data

# Descargar y arrancar el contenedor de Portainer
docker run -d -p 8000:8000 -p 9443:9443 --name portainer --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest

# Ver contenedores instalados
docker ps
```

Acceso al panel de Portainer:

```
https://${PI_HOST_IP}:9443
```

> Nota: en este homelab, Portainer se usa como unidad principal de despliegue
> (stacks), pero el editor web de stacks **no soporta** compose con directiva
> `build:`. Cuando un servicio necesita build (por ejemplo, `rpi-monitor`),
> primero se construye la imagen manualmente por SSH con
> `docker build -t nombre:tag .`, y luego se pega el compose en Portainer
> referenciando esa imagen ya construida, sin la línea `build:`.
