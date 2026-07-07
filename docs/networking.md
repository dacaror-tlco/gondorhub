# Red, puertos y DNS dinámico

## Reenvío de puertos en el router

Puertos abiertos actualmente en el router, redirigidos hacia la Raspberry Pi (`${PI_HOST_IP}`):

| Servicio | Puerto externo | Puerto interno | Protocolo | Destino |
|---|---|---|---|---|
| WireGuard | 51820 | 51820 | UDP | `${PI_HOST_IP}` |
| HTTP (NPM) | 80 | 80 | TCP | `${PI_HOST_IP}` |
| HTTPS (NPM) | 443 | 443 | TCP | `${PI_HOST_IP}` |

> Solo estos puertos deberían estar expuestos a Internet. Todo lo demás
> (paneles de administración como Portainer, NPM `:81`, SSH, etc.) se
> gestiona vía WireGuard o red local únicamente.

## DNS dinámico (DDNS)

Como la IP pública no es fija, se usa un cliente DDNS (`ddclient`) para mantener
actualizado un hostname (`${DDNS_HOSTNAME}`) que siempre apunta a la IP actual.

- `ddclient` corre en un contenedor Docker propio (ver `compose/ddclient.yml`).
- Cloudflare usa ese hostname como destino final tras el CNAME flattening de
  `${DOMAIN}` y sus subdominios (ver `docs/architecture.md`).

## ZeroTier

Se usa como red overlay adicional para unir dispositivos a una red privada
virtual. Unir un nodo a la red:

```bash
docker exec zerotier zerotier-cli join ${ZEROTIER_NETWORK_ID}
```

## Bot de control por Telegram

`docker-controller-bot` (ver `compose/docker-controller-bot.yml`) permite
gestionar los contenedores Docker (arrancar/parar, comprobar actualizaciones)
desde Telegram. Necesita:

- `TELEGRAM_TOKEN`: token del bot, obtenido de [@BotFather](https://t.me/BotFather).
- `TELEGRAM_ADMIN`: tu ID numérico de usuario de Telegram (para restringir quién puede controlar el bot).
