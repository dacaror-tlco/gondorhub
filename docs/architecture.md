# Arquitectura

## Visión general

```
Internet
   │
   ▼
Cloudflare (DNS + proxy naranja + SSL)
   │  CNAME flattening → ${DDNS_HOSTNAME}
   ▼
Router doméstico (IP dinámica)
   │
   ▼
Raspberry Pi 4B (${PI_HOST_IP})
   │
   ├── Nginx Proxy Manager (puertos 80/443)
   │     └── enruta cada subdominio.${DOMAIN} → contenedor correspondiente
   │
   └── Docker Engine
         ├── Nextcloud
         ├── Jellyfin
         ├── PhotoPrism
         ├── qBittorrent
         ├── MeTube
         ├── Pi-hole
         ├── WireGuard (wg-easy)
         ├── Portainer
         ├── code-server
         └── nginx:alpine (webs estáticas del campamento)
```

## DNS y SSL

- El dominio `${DOMAIN}` está gestionado en Cloudflare.
- Cada subdominio de servicio es un registro CNAME que apunta (vía flattening) a `${DDNS_HOSTNAME}`, un DDNS que se actualiza automáticamente cuando cambia la IP pública de la Raspberry Pi.
- NPM gestiona los certificados SSL mediante el reto DNS de Cloudflare (DNS challenge), lo que permite emitir certificados sin exponer puertos adicionales.
- **Importante:** con el proxy de Cloudflare activado (nube naranja) y HSTS activado en NPM a la vez, se producen bucles de redirección. La configuración estable es:
  - Cloudflare SSL/TLS mode: **Full (Strict)**
  - HSTS: **desactivado en NPM**

## Redes Docker y `network_mode: host`

Algunos servicios (por ejemplo, el dashboard de monitorización Flask) usan `network_mode: host` para simplificar el acceso a métricas del sistema. Esto tiene una implicación importante:

- NPM **no puede** resolver estos contenedores por nombre (como hace normalmente con la red de Docker), porque no están en la red bridge de Docker.
- La solución es apuntar el "Forward Hostname/IP" en NPM directamente a `${PI_HOST_IP}` y el puerto expuesto por la app (ej. `5000`), en lugar del nombre del contenedor.
- Portainer no mostrará mapeos de puertos en la UI para estos contenedores — es el comportamiento esperado, no un error.

## Acceso remoto

- **WireGuard (wg-easy)** provee acceso VPN completo a la red local para administración remota.
- **Cloudflare Access** se usa selectivamente para proteger algunos subdominios con autenticación adicional (Google OAuth como IdP). Importante: el IdP solo autentica identidad — hace falta una política "Allow" explícita con selectores de email concretos para restringir quién entra.
