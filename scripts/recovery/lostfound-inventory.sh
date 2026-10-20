#!/bin/bash
# Inventario de /lost+found tras un fsck: tipo, tamaño, fecha y primeras entradas/líneas de cada elemento.
# Solo lee. Oculta líneas que parezcan secretos.
# Uso (en la Pi):  sudo bash lostfound-inventory.sh [/lost+found] > ~/lf-inventario.txt 2>&1
# Tarda: hace du de todo lo que haya (cientos de GB en un HDD por USB = varios minutos).
LF=${1:-/lost+found}

for e in "$LF"/*; do
  n=$(basename "$e")
  if [ -d "$e" ]; then
    echo "=== DIR $n | $(du -sh "$e" 2>/dev/null | cut -f1) | $(stat -c %y "$e" | cut -d. -f1) | $(ls "$e" | wc -l) entradas"
    ls -A "$e" | head -15 | sed "s/^/    /"
  else
    echo "=== FILE $n | $(stat -c %s "$e") B | $(stat -c %y "$e" | cut -d. -f1) | $(file -b "$e" | cut -c1-80)"
    if [ "$(stat -c %s "$e")" -lt 20000 ] && file -b "$e" | grep -qi text; then
      head -3 "$e" | grep -viE "pass|secret|token|salt|key" | cut -c1-120 | sed "s/^/    | /"
    fi
  fi
done
