#!/bin/bash
# Nextcloud / MariaDB — paso 1 de 4 (ver docs/disaster-recovery.md)
#  - aparta la MariaDB dañada a $OLD_BASE y crea una MariaDB nueva y limpia (contenedor mariadb-new)
#  - instala un Nextcloud temporal (ncschema) en la BD nc_schema SOLO para obtener el esquema exacto
#    de la misma versión de Nextcloud que tenías (la imagen debe ser la misma versión)
# No toca tus archivos ni la BD antigua (solo la mueve de carpeta).
# Uso:  sudo bash 01-schema-reference.sh 2>&1 | tee ~/nc01.log
set -euo pipefail
STATE=/root/recuperacion
[ -f "$STATE/env" ] && . "$STATE/env"     # NEXTCLOUD_DB_USER, NEXTCLOUD_DB_PASSWORD, ...
: "${NEXTCLOUD_DB_USER:?define NEXTCLOUD_DB_USER en $STATE/env}"
: "${NEXTCLOUD_DB_PASSWORD:?define NEXTCLOUD_DB_PASSWORD en $STATE/env}"

LIVE_BASE=${LIVE_BASE:-/mnt/hdd/mariadb}          # carpeta del stack (montada como /config)
OLD_BASE=${OLD_BASE:-/mnt/hdd/mariadb-viejo}      # aquí queda la BD dañada
T=/mnt/hdd/_tmp-ncschema
mkdir -p "$STATE"

espera_log() { # contenedor patrón segundos
  for _ in $(seq 1 "$3"); do docker logs "$1" 2>&1 | grep -q "$2" && return 0; sleep 1; done
  echo "TIMEOUT esperando a $1"; docker logs --tail 40 "$1"; exit 1
}

echo "== 1. Apartar la MariaDB antigua a $OLD_BASE"
if [ -d "$OLD_BASE" ]; then
  echo "$OLD_BASE ya existe, no muevo nada"
else
  [ -z "$(docker ps -q -f name='^mariadb$')" ] || { echo "!! Para antes el contenedor mariadb"; exit 1; }
  [ -f "$LIVE_BASE/config/custom.cnf.orig" ] || cp -a "$LIVE_BASE/config/custom.cnf" "$LIVE_BASE/config/custom.cnf.orig"
  mv "$LIVE_BASE" "$OLD_BASE"
fi
mkdir -p "$LIVE_BASE/config"; chown -R 1000:1000 "$LIVE_BASE"

echo "== 2. MariaDB nueva y limpia (mariadb-new)"
docker network create ncrec >/dev/null 2>&1 || true
docker rm -f mariadb-new >/dev/null 2>&1 || true
docker run -d --name mariadb-new --network ncrec -e PUID=1000 -e PGID=1000 -e TZ=Europe/Madrid \
  -e MYSQL_DATABASE=nextcloud_db -e MYSQL_USER="$NEXTCLOUD_DB_USER" -e MYSQL_PASSWORD="$NEXTCLOUD_DB_PASSWORD" \
  -v "$LIVE_BASE/config:/config" linuxserver/mariadb >/dev/null
espera_log mariadb-new "ls.io-init\] done" 300
# linuxserver/mariadb: root entra solo por unix_socket (sin contraseña) desde dentro del contenedor
qn() { docker exec -i mariadb-new mariadb -uroot -N -B "$@"; }
for i in $(seq 1 30); do qn -e "SELECT 1" >/dev/null 2>&1 && break; sleep 2; [ "$i" = 30 ] && { echo "mariadb-new no responde"; exit 1; }; done
qn -e "CREATE DATABASE IF NOT EXISTS nextcloud_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
       CREATE DATABASE IF NOT EXISTS nc_schema CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
       CREATE USER IF NOT EXISTS '$NEXTCLOUD_DB_USER'@'%' IDENTIFIED BY '$NEXTCLOUD_DB_PASSWORD';
       ALTER USER '$NEXTCLOUD_DB_USER'@'%' IDENTIFIED BY '$NEXTCLOUD_DB_PASSWORD';
       GRANT ALL PRIVILEGES ON nextcloud_db.* TO '$NEXTCLOUD_DB_USER'@'%';
       GRANT ALL PRIVILEGES ON nc_schema.* TO '$NEXTCLOUD_DB_USER'@'%';
       FLUSH PRIVILEGES;"

echo "== 3. Nextcloud temporal (ncschema) para generar el esquema"
docker rm -f ncschema >/dev/null 2>&1 || true
rm -rf "$T"; mkdir -p "$T/config" "$T/data"; chown -R 1000:1000 "$T"
docker run -d --name ncschema --network ncrec -e PUID=1000 -e PGID=1000 -e TZ=Europe/Madrid \
  -v "$T/config:/config" -v "$T/data:/data" linuxserver/nextcloud >/dev/null
echo "(tarda: copia el código de Nextcloud al disco)"
espera_log ncschema "ls.io-init\] done" 1200
docker exec ncschema occ maintenance:install --database mysql --database-host mariadb-new \
  --database-name nc_schema --database-user "$NEXTCLOUD_DB_USER" --database-pass "$NEXTCLOUD_DB_PASSWORD" \
  --admin-user tmpadmin --admin-pass "$(openssl rand -hex 16)" --data-dir /data
grep -E "'version'" "$T/config/www/nextcloud/config/config.php" | tee "$STATE/nc_version.txt"
docker stop ncschema >/dev/null
qn -e "SELECT CONCAT('Tablas en nc_schema: ',COUNT(*)) FROM information_schema.TABLES WHERE TABLE_SCHEMA='nc_schema'"
echo "== FIN. mariadb-new en marcha; ncschema parado. Siguiente: 02-regen-frm-and-dump.py"
