#!/bin/bash
# Nextcloud / MariaDB — paso 3 de 4 (ver docs/disaster-recovery.md)
#  - importa el volcado completo en la MariaDB nueva y crea vacías las tablas que se perdieron enteras
#  - renombra appdata y escribe un config.php reconstruido (secret nuevo → los clientes tendrán que volver a iniciar sesión)
#  - para y borra los contenedores temporales para poder desplegar el stack real en Portainer
# Uso:  sudo bash 03-import-and-config.sh <instanceid> 2>&1 | tee ~/nc03.log
#       (el instanceid lo imprime 02-regen-frm-and-dump.py: es el sufijo de appdata_<instanceid>)
set -euo pipefail
STATE=/root/recuperacion
[ -f "$STATE/env" ] && . "$STATE/env"
: "${NEXTCLOUD_DB_USER:?define NEXTCLOUD_DB_USER en $STATE/env}"
: "${NEXTCLOUD_DB_PASSWORD:?define NEXTCLOUD_DB_PASSWORD en $STATE/env}"
: "${DOMAIN:?define DOMAIN en $STATE/env (p.ej. yourdomain.com)}"
: "${PI_HOST_IP:?define PI_HOST_IP en $STATE/env}"
INSTANCEID=${1:?uso: $0 <instanceid>}
NC_SUBDOMAIN=${NC_SUBDOMAIN:-cloud}
NC_VERSION=${NC_VERSION:-$(grep -oE "[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+" "$STATE/nc_version.txt")}

OLD_DIR=${OLD_BASE:-/mnt/hdd/mariadb-viejo}/config
DUMP=$OLD_DIR/nextcloud_db-completa.sql
NC=${NC_BASE:-/mnt/hdd/nextcloud}
CFG=$NC/config/www/nextcloud/config
qn() { docker exec -i mariadb-new mariadb -uroot -N -B "$@"; }

echo "== 1. Importar en mariadb-new.nextcloud_db"
n=$(qn -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='nextcloud_db'")
[ "$n" = 0 ] || { echo "!! nextcloud_db no está vacía ($n tablas), no importo"; exit 1; }
qn nextcloud_db < "$DUMP"
qn -e "SELECT CONCAT('Tablas importadas: ', COUNT(*)) FROM information_schema.TABLES WHERE TABLE_SCHEMA='nextcloud_db';
       SELECT CONCAT('Ficheros: ',COUNT(*)) FROM nextcloud_db.oc_filecache;
       SELECT CONCAT('Usuarios: ',GROUP_CONCAT(uid)) FROM nextcloud_db.oc_users;"

echo "== 2. Crear vacías las tablas perdidas (desde nc_schema)"
qn -e "SELECT CONCAT('CREATE TABLE nextcloud_db.',CHAR(96),TABLE_NAME,CHAR(96),' LIKE nc_schema.',CHAR(96),TABLE_NAME,CHAR(96),';')
       FROM information_schema.TABLES WHERE TABLE_SCHEMA='nc_schema'
       AND TABLE_NAME NOT IN (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='nextcloud_db')" > "$STATE/crear_vacias.sql"
sed 's/^/   /' "$STATE/crear_vacias.sql"
qn < "$STATE/crear_vacias.sql"
qn -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='nextcloud_db'" | sort > "$STATE/tablas_nuevas.txt"
echo "Tablas que existían y siguen faltando (son de apps de la tienda; 04-post-deploy.sh las reinstala):"
comm -23 <(sort "$STATE/old_tables.txt") "$STATE/tablas_nuevas.txt" | sed 's/^/   /'
qn -e "DROP DATABASE nc_schema;"

echo "== 3. appdata y config.php"
[ -d "$NC/data/appdata_$INSTANCEID" ] || { echo "!! No existe $NC/data/appdata_$INSTANCEID — renombra antes la carpeta appdata recuperada"; exit 1; }
[ -f "$CFG/config.php" ] && mv "$CFG/config.php" "$CFG/config.php.antes-de-recuperar"
SALT=$(openssl rand -base64 30 | tr -d '\n/+=')
SECRET=$(openssl rand -base64 48 | tr -d '\n/+=')
cat > "$CFG/config.php" <<EOF
<?php
\$CONFIG = array (
  'instanceid' => '$INSTANCEID',
  'passwordsalt' => '$SALT',
  'secret' => '$SECRET',
  'trusted_domains' =>
  array (
    0 => '$PI_HOST_IP:8443',
    1 => '$PI_HOST_IP',
    2 => '$NC_SUBDOMAIN.$DOMAIN',
  ),
  'trusted_proxies' =>
  array (
    0 => '172.16.0.0/12',
    1 => '127.0.0.1',
  ),
  'overwrite.cli.url' => 'https://$NC_SUBDOMAIN.$DOMAIN',
  'overwriteprotocol' => 'https',
  'datadirectory' => '/data',
  'dbtype' => 'mysql',
  'version' => '$NC_VERSION',
  'dbname' => 'nextcloud_db',
  'dbhost' => 'mariadb',
  'dbport' => '',
  'dbtableprefix' => 'oc_',
  'mysql.utf8mb4' => true,
  'dbuser' => '$NEXTCLOUD_DB_USER',
  'dbpassword' => '$NEXTCLOUD_DB_PASSWORD',
  'installed' => true,
  'maintenance' => true,
  'memcache.local' => '\\\\OC\\\\Memcache\\\\APCu',
  'default_phone_region' => 'ES',
);
EOF
chown 1000:1000 "$CFG/config.php"; chmod 640 "$CFG/config.php"
rm -f "$CFG/CAN_INSTALL"
grep -vE "secret|salt|dbpassword" "$CFG/config.php"

echo "== 4. Parar y borrar los contenedores temporales"
docker stop ncschema mariadb-new >/dev/null 2>&1 || true
docker rm ncschema mariadb-new >/dev/null 2>&1 || true
docker network rm ncrec >/dev/null 2>&1 || true
rm -rf /mnt/hdd/_tmp-ncschema
echo "== FIN. Despliega el stack de Nextcloud en Portainer y luego ejecuta 04-post-deploy.sh"
