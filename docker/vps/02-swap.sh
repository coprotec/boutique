#!/bin/bash
# Étape 2 (compte ubuntu) — 2 Go de swap : le VPS n'a que 2 Go de RAM pour boutique-old + la nouvelle boutique.
# Lancer : sudo bash 02-swap.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }

if swapon --show | grep -q /swapfile; then
    echo "Swap déjà actif :"
else
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-swap.conf
    sysctl --system >/dev/null
fi
free -h
