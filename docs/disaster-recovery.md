# Recuperación ante corrupción del disco (fsck → `/lost+found`)

Runbook escrito tras el incidente de **2026-10-20**. Ese día el ext4 del HDD (que es a la vez el sistema raíz de la Pi y donde vive `/mnt/hdd`) se corrompió. El `fsck` lo reparó vaciando `/mnt/hdd` dentro de `/lost+found`. Se recuperó **todo** salvo algunas tablas de Nextcloud que se pueden regenerar. Los scripts usados están en [`scripts/recovery/`](../scripts/recovery/).

## 1. Síntomas

- Los contenedores fallan con **`Data consistency error`**. Es como las imágenes basadas en Alpine/musl (linuxserver) muestran el error `EUCLEAN`; en glibc sale como *"Structure needs cleaning"*. **Significa ext4 corrupto, no un fallo de la app.**
- Tras el fsck, cada servicio arranca **"de cero"**: Nextcloud muestra el instalador, Jellyfin el asistente inicial y MariaDB pone `Setting Up Initial Databases`. Lo que ha pasado es que Docker creó carpetas vacías donde antes estaban los datos.

## 2. Primeras acciones (lo que NO hay que hacer)

- **No completar ningún instalador o asistente** (Nextcloud, Jellyfin, vaultwarden…). Crean datos nuevos encima y complican la recuperación.
- **Parar todos los contenedores que escriben en `/mnt/hdd`.** Se pueden dejar en marcha los de acceso remoto (`wg-easy`, `pihole`, `portainer`, `docker-controller-bot`, `ddclient`), para no perder la VPN.
- **No borrar nada** de `/lost+found` ni de `/mnt/hdd`.

```bash
sudo dmesg | grep -iE "ext4|error" | tail -30          # confirmar la corrupción
```

Forzar el fsck en el próximo arranque (el disco es el root, no se puede reparar en caliente):

```bash
sudo sed -i 's/$/ fsck.mode=force/' /boot/firmware/cmdline.txt   # debe seguir siendo UNA línea
sudo reboot
# después:
sudo sed -i 's/ fsck.mode=force//' /boot/firmware/cmdline.txt
```

Si cae a `(initramfs)`: `e2fsck -f -y /dev/sda` y luego `exit`.

## 3. Triage de `/lost+found`

`/mnt/hdd` **no es un punto de montaje**: es una carpeta dentro del root. Así que lo rescatado aparece en **`/lost+found` de la raíz**, con nombres `#<inodo>`. En 2026-10 fueron 935 GB repartidos en 251 entradas.

```bash
sudo du -sh /lost+found; sudo ls /lost+found | wc -l
sudo bash scripts/recovery/lostfound-inventory.sh > ~/lf-inventario.txt 2>&1
```

> `/lost+found` es `drwx------`: los comodines (`/lost+found/#123/*`) hay que expandirlos **dentro** de `sudo bash -c '...'`. Si no, los expande tu usuario antes del `sudo` y fallan.

Pistas para identificar cada entrada (comparando con `compose/`):

| Contenido de la carpeta `#…` | Es |
|---|---|
| `books LOTR movies series music metube …` | `/mnt/hdd/samba` |
| `web-* RedBook` | `/mnt/hdd/webs` |
| `db.sqlite3 rsa_key.pem icon_cache` | `/mnt/hdd/vaultwarden-data` |
| `data letsencrypt` | `/mnt/hdd/npm` |
| `filebrowser.db settings.json` | `/mnt/hdd/filebrowser-config` |
| `entrypoint.sh watch.sh` | `/mnt/hdd/gondorhub-watch` |
| `aria_log* ibdata1 ib_logfile0 mysql photoprism sys` | `/mnt/hdd/photoprism-mariadb` (datadir completo) |
| `data/photoprism-storage` | `/mnt/hdd/photoprism` |
| `config/` con `.aspnet data database.xml system.xml` | `/mnt/hdd/jellyfin` ⚠️ |
| `config/` con `qBittorrent` | `/mnt/hdd/qbittorrent` ⚠️ |
| `config/` con `extensions .local workspace` | `/mnt/hdd/code-server` ⚠️ |
| `config/ddclient.conf` | `/mnt/hdd/ddclient` |
| `cache files files_trashbin files_versions uploads` | `/mnt/hdd/nextcloud/data/<usuario>` |
| `appstore avatar preview theming js …` | `/mnt/hdd/nextcloud/data/appdata_<instanceid>` |
| `oc_*.ibd oc_*.frm db.opt` | `mariadb/config/databases/nextcloud_db` |
| `column_stats.* db.frm event.*` / `host_summary*.frm` / solo `db.opt` | BD `mysql` / `sys` / `performance_schema` |
| Ficheros sueltos `data` de 10 MB recién modificados | `undo001-003`: se distinguen por el *space_id* (abajo) |
| Fichero suelto que empieza por `Phys` | `ib_logfile0` |
| Muchos `MySQL replication log` sueltos | binlogs: descartables |
| Decenas de carpetas `appinfo lib l10n …` con la misma fecha | código de apps de Nextcloud: descartable (lo trae la imagen) |

⚠️ **Error que cometimos:** si la carpeta `#…` **contiene** una subcarpeta `config/`, la carpeta `#…` es el **padre**. Hay que moverla a `/mnt/hdd/<servicio>`, **no** a `/mnt/hdd/<servicio>/config`. Si no, queda `config/config` y el servicio arranca vacío: Jellyfin pidió crear el servidor de nuevo. Arreglo:

```bash
d=/mnt/hdd/jellyfin; mv $d/config $d/config-vacio && mv $d/config-vacio/config $d/config
```

Identificar los ficheros sueltos de InnoDB:

```bash
sudo head -c 4 "/lost+found/#N"; echo                                         # "Phys" = ib_logfile0
sudo od -An -tu4 --endian=big -j34 -N4 "/lost+found/#N"                      # space_id: 0 = ibdata1
```

Los `undo00X` tienen space_ids consecutivos (p. ej. 130, 131, 132 → undo001, undo002, undo003).

## 4. Reconstruir `/mnt/hdd`

1. `mv /mnt/hdd /mnt/hdd.nuevo-FECHA` (la versión vacía creada hoy; no se borra) y `mkdir /mnt/hdd`.
2. Devolver a la nueva `/mnt/hdd` las carpetas que usan los contenedores que siguen en marcha (`wg-easy` → `wireguard`, `ddclient`). Se ven con:
   `docker inspect -f '{{range .Mounts}}{{.Source}} {{end}}' wg-easy ddclient`.
   Un contenedor en marcha sigue viendo su carpeta aunque se renombre, pero **al reiniciar** necesita la ruta. Si falta, pierdes la VPN.
3. `mv` de cada `#…` a su sitio. Es en el mismo disco, así que es instantáneo y reversible.
4. Lo que no corresponde a ningún stack actual va a `/mnt/hdd/_recuperado/`.
5. Redesplegar las stacks en Portainer. **Parar una stack en Portainer elimina sus contenedores**: tras el incidente, `docker ps -a` solo mostraba los que estaban en marcha. Las definiciones siguen en Portainer.

## 5. Nextcloud + MariaDB

Lo que nos encontramos:

- **InnoDB (`ibdata1`, redo, undo) estaba sano**, pero la BD `mysql` había perdido ficheros (`mysql/db.MAI`), así que MariaDB abortaba con `Can't open and lock privilege tables`.
  → Se arranca con **`skip-grant-tables`** solo para leer y volcar.
- **20 tablas tenían `.ibd` pero no `.frm`**: entre ellas `oc_filecache`, `oc_calendars`, `oc_calendarobjects`, `oc_share` y `oc_preferences`. El fsck se lleva sobre todo ficheros pequeños.
  → El `.frm` se **regenera**. Hay que crear la tabla con la definición exacta de la misma versión de Nextcloud, y para ello se instala un Nextcloud temporal. Se ajustan los índices a los que tenía la tabla según el diccionario InnoDB (`INNODB_SYS_INDEXES`/`FIELDS`) y se copia el `.frm` junto al `.ibd` antiguo. MariaDB valida el encaje al abrirla (`CHECK TABLE` = OK).
- **32 tablas se perdieron enteras**: `oc_jobs`, `oc_mounts`, `oc_previews`, `oc_memories*`, `oc_sharing_*`, `oc_files_lock`, `oc_twofactor_totp_secrets`… Se crean vacías desde el esquema de referencia. Las de Memories se recrean reinstalando la app.
- **`config.php` se perdió.** Se reconstruye:
  - el `instanceid` se saca del nombre `appdata_<id>` en `oc_filecache`;
  - la versión, del Nextcloud temporal;
  - la BD, de las credenciales del stack.

  El **`secret` es nuevo**, y eso tiene dos consecuencias: los clientes tienen que volver a iniciar sesión, y los valores cifrados con el antiguo dan `HMAC does not match`, así que hay que volver a configurarlos.

Pasos, en la Pi:

```bash
sudo mkdir -p /root/recuperacion
sudo tee /root/recuperacion/env >/dev/null <<'EOF'
NEXTCLOUD_DB_USER=...
NEXTCLOUD_DB_PASSWORD=...
DOMAIN=yourdomain.com
PI_HOST_IP=192.168.1.x
EOF
# 0. datadir antiguo colocado en /mnt/hdd/mariadb/config/databases y COPIA DE SEGURIDAD:
sudo cp -a /mnt/hdd/mariadb/config/databases /root/backup-mariadb-$(date +%F)
# appdata recuperado renombrado a appdata_<instanceid> (o renómbralo cuando 02 te dé el id)
sudo bash    scripts/recovery/nextcloud/01-schema-reference.sh   2>&1 | tee ~/nc01.log
sudo python3 scripts/recovery/nextcloud/02-regen-frm-and-dump.py 2>&1 | tee ~/nc02.log
sudo bash    scripts/recovery/nextcloud/03-import-and-config.sh <instanceid> 2>&1 | tee ~/nc03.log
# → desplegar el stack de Nextcloud en Portainer
sudo bash    scripts/recovery/nextcloud/04-post-deploy.sh        2>&1 | tee ~/nc04.log
```

Notas:

- En `linuxserver/mariadb` actual, **root solo entra por socket y sin contraseña**: `docker exec mariadb mariadb -uroot`. Con `-p` da `Access denied`.
- `mariadb-dump --routines` falla (`Index for table 'proc' is corrupt`): usar `--skip-routines`. Nextcloud no usa rutinas.
- `occ` en modo mantenimiento solo deja usar `maintenance:*`/`upgrade`. Hay que quitarlo antes de `db:*` y `app:*`.
- `files:scan --all` tardó ~4 min para unos 130.000 ficheros. `files:scan-app-data` (miniaturas) se arrastra durante horas por USB: lánzalo aparte con `docker exec -d`.

## 6. Cómo trabajar por SSH durante la recuperación

- **Pegar scripts largos en la terminal los rompe**: se mezclan líneas y un `mv` acaba con argumentos cambiados. Escribe el script en el PC y súbelo:
  ```powershell
  scp script.sh usuario@pi:~/
  ```
  ```bash
  sed -i 's/\r$//' ~/script.sh && sudo bash ~/script.sh 2>&1 | tee ~/script.log
  ```
- Cada script **comprueba antes de mover**: aborta si hay contenedores escribiendo y no pisa ficheros existentes.

## 7. Prevención

- Es la segunda caída por el HDD en dos meses (la primera, la fuente del adaptador USB-SATA). La corrupción probablemente viene de cortes de alimentación sin apagado limpio.
- **Copias de seguridad automáticas** a un segundo disco: es lo que habría convertido esto en un `restore` de 10 minutos. Prioridad: volcado diario de las BDs (`mariadb-dump` de Nextcloud y PhotoPrism, `sqlite3 .backup` de vaultwarden) + `config.php` + configs de los servicios.
- Vigilar la salud del disco: `sudo smartctl -a -d sat /dev/sda` (`Reallocated_Sector_Ct`, `Current_Pending_Sector`).
