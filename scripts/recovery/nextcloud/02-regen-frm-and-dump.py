#!/usr/bin/env python3
"""Nextcloud / MariaDB — paso 2 de 4 (ver docs/disaster-recovery.md)

Regenera los .frm perdidos (tablas con .ibd pero sin .frm) y vuelca nextcloud_db COMPLETA desde la BD antigua.

1. Arranca la BD antigua (mariadb-old, con skip-grant-tables porque mysql.* suele quedar dañada)
   y lee su diccionario InnoDB: columnas, índices (con sus campos) y collations.
2. En mariadb-new crea nc_frm.<tabla> = nc_schema.<tabla> ajustada para tener EXACTAMENTE los índices antiguos.
   Se para sin tocar nada si las columnas o collations no coinciden.
3. Copia esos .frm junto a los .ibd antiguos, rearranca la BD antigua y hace COUNT + CHECK TABLE de cada una.
   MariaDB valida al abrir la tabla que el .frm encaja con su diccionario.
4. Vuelca nextcloud_db completa (sin rutinas: mysql.proc suele estar dañada y Nextcloud no las usa).

Requiere 01-schema-reference.sh (mariadb-new con nc_schema en marcha).
Uso:  sudo python3 02-regen-frm-and-dump.py 2>&1 | tee ~/nc02.log
"""
import os, shutil, subprocess, sys, time

OLD_DIR = os.environ.get('OLD_BASE', '/mnt/hdd/mariadb-viejo') + '/config'
NEW_DIR = os.environ.get('LIVE_BASE', '/mnt/hdd/mariadb') + '/config'
STATE = '/root/recuperacion'
DUMP = 'nextcloud_db-completa.sql'
DB_DIR = f'{OLD_DIR}/databases/nextcloud_db'


def sh(cmd, check=True):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if check and r.returncode:
        print(r.stdout, r.stderr)
        sys.exit(f'ERROR ejecutando: {cmd}')
    return r.stdout


def sql(cont, q, check=True):
    r = subprocess.run(['docker', 'exec', '-i', cont, 'mariadb', '-uroot', '-N', '-B'],
                       input=q, capture_output=True, text=True)
    if r.returncode and check:
        print(r.stderr)
        sys.exit(f'ERROR SQL en {cont}')
    return [l.split('\t') for l in r.stdout.splitlines()]


def start_old():
    sh('docker rm -f mariadb-old', check=False)
    cnf = f'{OLD_DIR}/custom.cnf'
    shutil.copy2(f'{OLD_DIR}/custom.cnf.orig', cnf)
    with open(cnf, 'a') as f:
        f.write('\n[mysqld]\nskip-grant-tables\n')
    sh(f'docker run -d --name mariadb-old -e PUID=1000 -e PGID=1000 -e TZ=Europe/Madrid '
       f'-v {OLD_DIR}:/config linuxserver/mariadb')
    for _ in range(90):
        r = subprocess.run(['docker', 'exec', 'mariadb-old', 'mariadb', '-uroot', '-e', 'SELECT 1'],
                           capture_output=True)
        if r.returncode == 0:
            return
        time.sleep(2)
    print(sh(f'tail -40 {OLD_DIR}/log/mysql/mariadb-error.log', check=False))
    sys.exit('mariadb-old no arranca (mira el log de arriba)')


def stop_old():
    sh('docker stop mariadb-old', check=False)
    sh('docker rm mariadb-old', check=False)
    shutil.copy2(f'{OLD_DIR}/custom.cnf.orig', f'{OLD_DIR}/custom.cnf')


os.makedirs(STATE, exist_ok=True)
files = os.listdir(DB_DIR)
T = sorted(f[:-4] for f in files if f.endswith('.ibd') and f[:-4] + '.frm' not in files)
print(f'Tablas con .ibd pero sin .frm: {len(T)}')
print('  ' + ' '.join(T))

print('== 1. Diccionario InnoDB antiguo')
start_old()
with open(f'{STATE}/old_tables.txt', 'w') as f:
    for (n,) in sql('mariadb-old', "SELECT REPLACE(NAME,'nextcloud_db/','') FROM information_schema.INNODB_SYS_TABLES "
                                   "WHERE NAME LIKE 'nextcloud_db/%' ORDER BY 1"):
        f.write(n + '\n')
with open(f'{STATE}/old_apps.tsv', 'w') as f:
    for row in sql('mariadb-old', "SELECT appid, configkey, configvalue FROM nextcloud_db.oc_appconfig "
                                  "WHERE configkey IN ('installed_version','enabled') ORDER BY appid, configkey"):
        f.write('\t'.join(row) + '\n')
for row in sql('mariadb-old', "SELECT * FROM nextcloud_db.oc_twofactor_providers", check=False):
    print('  2FA: ' + ' '.join(row) + '   (1 = activada)')
in_list = ','.join(f"'nextcloud_db/{t}'" for t in T) or "''"
old_cols = {}
for t, name, coll in sql('mariadb-old', f"""
    SELECT REPLACE(t.NAME,'nextcloud_db/',''), c.NAME, (c.PRTYPE >> 16) & 32767
    FROM information_schema.INNODB_SYS_COLUMNS c JOIN information_schema.INNODB_SYS_TABLES t USING (TABLE_ID)
    WHERE t.NAME IN ({in_list}) ORDER BY 1, c.POS"""):
    old_cols.setdefault(t, []).append((name, int(coll)))
old_idx = {}   # tabla -> {indice: [tipo, [campos]]}
for t, iname, itype, fname in sql('mariadb-old', f"""
    SELECT REPLACE(t.NAME,'nextcloud_db/',''), i.NAME, i.TYPE, IFNULL(f.NAME,'')
    FROM information_schema.INNODB_SYS_INDEXES i JOIN information_schema.INNODB_SYS_TABLES t USING (TABLE_ID)
    LEFT JOIN information_schema.INNODB_SYS_FIELDS f USING (INDEX_ID)
    WHERE t.NAME IN ({in_list}) ORDER BY 1, i.INDEX_ID, f.POS"""):
    ent = old_idx.setdefault(t, {}).setdefault(iname, [int(itype), []])
    if fname:
        ent[1].append(fname)
stop_old()

print('== 2. Comparar con el esquema de referencia (nc_schema) y preparar índices')
new_cols, new_idx = {}, {}
for t, name, coll in sql('mariadb-new', """
    SELECT c.TABLE_NAME, c.COLUMN_NAME, IFNULL(co.ID,0) FROM information_schema.COLUMNS c
    LEFT JOIN information_schema.COLLATIONS co ON co.COLLATION_NAME = c.COLLATION_NAME
    WHERE c.TABLE_SCHEMA='nc_schema' ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"""):
    new_cols.setdefault(t, []).append((name, int(coll)))
for t, iname, col in sql('mariadb-new', """
    SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA='nc_schema' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"""):
    new_idx.setdefault(t, {}).setdefault(iname, []).append(col)

problemas, alters = [], {}
for t in T:
    oc, nc = old_cols.get(t, []), new_cols.get(t, [])
    if not nc:
        problemas.append(f'{t}: no existe en nc_schema (¿tabla de una app que no viene de serie?)')
        continue
    if [c[0] for c in oc] != [c[0] for c in nc]:
        problemas.append(f'{t}: columnas distintas\n      antes: {[c[0] for c in oc]}\n      ahora: {[c[0] for c in nc]}')
        continue
    for (n, c1), (_, c2) in zip(oc, nc):
        if c1 not in (0, 63) and c2 and c1 != c2:
            problemas.append(f'{t}.{n}: collation antigua id={c1} nueva id={c2}')
    oi = {k: v for k, v in old_idx[t].items() if k not in ('GEN_CLUST_INDEX', 'FTS_DOC_ID_INDEX')}
    ni = new_idx.get(t, {})
    ops = [f'DROP INDEX `{k}`' for k in sorted(set(ni) - set(oi))]
    for k in sorted(set(oi) - set(ni)):
        itype, fields = oi[k]
        if itype not in (0, 2):
            problemas.append(f'{t}.{k}: tipo de índice {itype} no soportado')
            continue
        ops.append(f"ADD {'UNIQUE ' if itype == 2 else ''}INDEX `{k}` (" + ','.join(f'`{f}`' for f in fields) + ')')
    for k in sorted(set(oi) & set(ni)):
        if oi[k][1] != ni[k]:
            problemas.append(f'{t}.{k}: campos antiguos {oi[k][1]} nuevos {ni[k]}')
    alters[t] = ops
    print(f'  {t}: ' + ('; '.join(ops) if ops else 'igual'))

if problemas:
    print('\n!! PROBLEMAS, no sigo:')
    print('\n'.join('   ' + p for p in problemas))
    sys.exit(1)

print('== 3. Crear nc_frm con los índices antiguos y copiar sus .frm a la BD antigua')
q = 'DROP DATABASE IF EXISTS nc_frm; CREATE DATABASE nc_frm;\n'
for t in T:
    q += f'CREATE TABLE nc_frm.`{t}` LIKE nc_schema.`{t}`;\n'
    if alters[t]:
        q += f'ALTER TABLE nc_frm.`{t}` ' + ', '.join(alters[t]) + ';\n'
sql('mariadb-new', q)
for t in T:
    dst = f'{DB_DIR}/{t}.frm'
    shutil.copy2(f'{NEW_DIR}/databases/nc_frm/{t}.frm', dst)
    os.chown(dst, 1000, 1000)
    os.chmod(dst, 0o660)
sql('mariadb-new', 'DROP DATABASE nc_frm;')

print('== 4. Comprobar que se abren')
start_old()
mal = []
for t in T:
    n = sql('mariadb-old', f'SELECT COUNT(*) FROM nextcloud_db.`{t}`', check=False)
    chk = sql('mariadb-old', f'CHECK TABLE nextcloud_db.`{t}`', check=False)
    estado = chk[-1][-1] if chk else 'ERROR'
    filas = n[0][0] if n else 'ERROR'
    print(f'  {t:40} filas={filas:>8}  check={estado}')
    if estado != 'OK' or filas == 'ERROR':
        mal.append(t)
if mal:
    stop_old()
    sys.exit(f'!! Tablas con problemas: {mal} — no vuelco nada')

print('== 5. Datos útiles para el config.php')
for row in sql('mariadb-old', r"""
    SELECT 'instanceid', REPLACE(SUBSTRING_INDEX(path,'/',1),'appdata_','') FROM nextcloud_db.oc_filecache WHERE path LIKE 'appdata\_%' GROUP BY 2;
    SELECT 'storage', numeric_id, id FROM nextcloud_db.oc_storages;
    SELECT 'usuarios', GROUP_CONCAT(uid) FROM nextcloud_db.oc_users;
    SELECT 'ficheros', COUNT(*) FROM nextcloud_db.oc_filecache;""", check=False):
    print('  ' + '  '.join(row))

print('== 6. Volcado COMPLETO de nextcloud_db (sin rutinas ni triggers)')
sh(f"docker exec mariadb-old sh -c 'mariadb-dump --single-transaction --quick --skip-routines --skip-triggers "
   f"--default-character-set=utf8mb4 nextcloud_db > /config/{DUMP}'")
stop_old()
print(sh(f'ls -la {OLD_DIR}/{DUMP}'))
if 'Dump completed' not in sh(f'tail -c 300 {OLD_DIR}/{DUMP}'):
    sys.exit('!! El volcado no está completo')
print('== FIN. Volcado OK. Siguiente: 03-import-and-config.sh')
