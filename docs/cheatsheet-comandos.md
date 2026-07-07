# Chuleta de comandos (Raspberry Pi / Linux)

Comandos de referencia rápida usados en el mantenimiento del homelab.

## Comprobaciones básicas

```bash
sudo raspi-config       # configuración general de la Raspberry Pi
lsusb                   # dispositivos USB conectados / puertos
docker ps               # contenedores en ejecución
```

## ZeroTier

Unir el nodo a una red ZeroTier existente:

```bash
docker exec zerotier zerotier-cli join ${ZEROTIER_NETWORK_ID}
```

## Ampliar almacenamiento (añadir/formatear un disco nuevo)

```bash
lsblk                                  # identificar el dispositivo (ej. /dev/sda)
sudo umount /media/usuario/HDD
sudo mkfs.ext4 /dev/sda1
sudo mkdir /mnt/SSD
sudo mount /dev/sda /mnt/SSD
df -h
sudo blkid /dev/sda                    # obtener el UUID del disco
sudo nano /etc/fstab
```

Línea a añadir en `/etc/fstab` (sustituye por el UUID real que te dé `blkid`):

```
UUID=${DISK_UUID} /mnt/SSD ext4 defaults 0 0
```

## Samba

```bash
mkdir -p /mnt/HDD/samba
cd /mnt/HDD
sudo chmod 777 samba
```

> Nota: `chmod 777` es una solución rápida para que el contenedor de Samba pueda
> escribir sin problemas de permisos. Ver `docs/troubleshooting.md` para el
> caso equivalente en discos FAT32/NTFS (donde `chown` no aplica).

## Linux — referencia general

Navegación y archivos:

```bash
pwd; cd; cd ..; cd -
pushd; popd
tree -d
ls -la
ln file1 file2          # hard link
ln -s file1 file3        # symlink
cat / tac / less / head / tail / wc
touch [-t] myfile
mkdir / rmdir
rm -rf / rm -f / rm -i
mv / cp
find . -name "file"
find / -size +10M -exec command {} \;
```

Procesos:

```bash
ps aux
pstree
kill -SIGKILL <pid>
sudo renice <priority> <pid>
jobs -l; fg; bg
```

Montaje y discos:

```bash
sudo mount /dev/sda5 /home
sudo umount /home
df -Th
cat /proc/mounts
```

Comparación y parcheo:

```bash
diff file1 file2
diff -Nur originalfile newfile > patchfile
patch originalfile patchfile
```

Sincronización y compresión:

```bash
rsync -r project-X archive-machine:archives/project-X
tar zcvf mydir.tar.gz mydir
tar xvf mydir.tar.gz
zip -r backup.zip ~
unzip backup.zip
```

Edición rápida:

```bash
echo "línea" > myfile
echo "línea" >> myfile
cat << EOF > myfile
vi myfile
```

Usuarios y grupos:

```bash
whoami; who; who -a
sudo useradd nombre
sudo usermod -aG grupo usuario
groups usuario
```

Red:

```bash
ip addr show
ip route show
ping -c 4 host
traceroute <destino>
dig / nslookup
wget <url>
curl -o archivo <url>
```
