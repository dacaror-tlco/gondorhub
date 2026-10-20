#!/bin/bash
# Nextcloud / MariaDB — paso 4 de 4 (ver docs/disaster-recovery.md)
# Tras desplegar el stack: upgrade/repair, índices, reinstalar apps de la tienda sin código
# (con reinstalación limpia de Memories, cuyas tablas suelen perderse) y reescanear ficheros.
# Uso:  sudo bash 04-post-deploy.sh 2>&1 | tee ~/nc04.log
set -uo pipefail
STATE=/root/recuperacion
NC=${NC_BASE:-/mnt/hdd/nextcloud}
WWW=$NC/config/www/nextcloud
occ() { docker exec nextcloud occ "$@"; }
db()  { docker exec -i mariadb mariadb -uroot nextcloud_db -N -B -e "$1"; }

echo "== 0. Esperar a nextcloud y mariadb"
for _ in $(seq 1 600); do docker logs nextcloud 2>&1 | grep -q "ls.io-init\] done" && break; sleep 1; done
for _ in $(seq 1 60); do db "SELECT 1" >/dev/null 2>&1 && break; sleep 2; done
occ status || { echo "!! occ no responde"; docker logs --tail 40 nextcloud; exit 1; }

echo "== 1. upgrade + repair"
occ upgrade
occ maintenance:repair --include-expensive
# con el modo mantenimiento activo occ no deja usar app:* ni db:*
occ maintenance:mode --off

echo "== 2. Índices / columnas / claves que falten"
occ db:add-missing-indices
occ db:add-missing-columns
occ db:add-missing-primary-keys

echo "== 3. Memories: reinstalación limpia si sus tablas se perdieron"
if [ -z "$(db "SHOW TABLES LIKE 'oc_memories'")" ]; then
  db "DELETE FROM oc_migrations WHERE app='memories';
      DELETE FROM oc_appconfig WHERE appid='memories' AND configkey IN ('installed_version','enabled','types');"
  db "ALTER TABLE oc_filecache DROP INDEX memories_parent_mimetype" 2>/dev/null || true
  db "ALTER TABLE oc_systemtag_object_mapping DROP INDEX memories_type_tagid" 2>/dev/null || true
fi

echo "== 4. Reinstalar apps que estaban instaladas y ya no tienen código"
faltan=""
while IFS=$'\t' read -r app key _; do
  [ "$key" = installed_version ] || continue
  [ -d "$WWW/apps/$app" ] || [ -d "$WWW/custom_apps/$app" ] || faltan="$faltan $app"
done < "$STATE/old_apps.tsv"
echo "Sin código:${faltan:- ninguna}"
for app in $faltan; do
  echo "--- $app"
  occ app:install "$app" || occ app:enable "$app" || echo "!! $app no se pudo instalar"
done

echo "== 5. Reescanear los ficheros de los usuarios (tarda)"
occ files:scan --all
# files:scan-app-data (miniaturas) es opcional y muy lento por USB: lánzalo aparte en segundo plano
#   docker exec -d nextcloud occ files:scan-app-data

echo "== 6. Estado final"
occ background:cron
occ status
occ app:list --shipped=false
echo "== FIN. Los clientes (móvil/escritorio) tendrán que volver a iniciar sesión (secret nuevo)."
