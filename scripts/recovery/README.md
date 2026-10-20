# scripts/recovery

Herramientas usadas para recuperar la Pi tras una corrupción de ext4 que vació `/mnt/hdd` en `/lost+found` (2026-10-20). El procedimiento completo, con el porqué de cada paso, está en [`docs/disaster-recovery.md`](../../docs/disaster-recovery.md).

| Script | Qué hace |
|---|---|
| `lostfound-inventory.sh` | Inventario de solo lectura de `/lost+found`: tipo, tamaño, fecha y contenido de cada `#inodo` |
| `nextcloud/01-schema-reference.sh` | Aparta la MariaDB dañada, crea una limpia e instala un Nextcloud temporal para tener el esquema exacto |
| `nextcloud/02-regen-frm-and-dump.py` | Regenera los `.frm` perdidos (tablas con `.ibd` sin `.frm`), valida con `CHECK TABLE` y vuelca la BD completa |
| `nextcloud/03-import-and-config.sh` | Importa el volcado, crea vacías las tablas perdidas y reconstruye `config.php` |
| `nextcloud/04-post-deploy.sh` | Tras desplegar el stack: `upgrade`/`repair`, índices, reinstalar apps de la tienda y reescanear |

Credenciales y dominio: en `/root/recuperacion/env` en la Pi (nunca en el repo). Ver el formato en el runbook.

Uso típico, desde Windows:

```powershell
scp scripts\recovery\nextcloud\01-schema-reference.sh usuario@pi:~/
```

```bash
sed -i 's/\r$//' ~/01-schema-reference.sh
sudo bash ~/01-schema-reference.sh 2>&1 | tee ~/nc01.log
```
