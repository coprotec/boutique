#!/bin/bash
# Étape 1 (compte ubuntu) — DEPLOIEMENT_VPS.md
#   1. Sécurise l'adresse git de boutique-old : « coprotec/boutique » est désormais le NOUVEAU projet.
#   2. Applique les mises à jour système, puis propose le redémarrage (coupure de boutique-old : 1 à 3 min).
# Lancer : sudo bash 01-mise-a-jour.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
confirmer() { read -r -p "$1 [o/N] " r; [[ "$r" =~ ^[oO]$ ]]; }

echo "== 1. Adresse du dépôt de boutique-old"
for d in /var/www/app /var/www/preprod.app.coprotec.net/httpdocs; do
    [ -d "$d/.git" ] || continue
    proprio="$(stat -c %U "$d")"
    sudo -H -u "$proprio" git -C "$d" remote set-url origin git@github.com:coprotec/boutique-old.git
    echo "$d → $(sudo -H -u "$proprio" git -C "$d" remote get-url origin)"
done

echo
echo "== 2. Services de boutique-old au démarrage"
for s in apache2 mysql; do
    etat="$(systemctl is-enabled "$s" 2>&1 || true)"
    echo "$s : $etat"
    [ "$etat" = enabled ] || { echo "ATTENTION : $s ne redémarrera pas seul. Corriger avant de continuer." >&2; exit 1; }
done

echo
echo "== 3. Mises à jour"
apt-get update
apt list --upgradable 2>/dev/null | tail -n +2
confirmer "Installer ces mises à jour (les fichiers de configuration locaux sont conservés) ?" || exit 0
DEBIAN_FRONTEND=noninteractive apt-get -y \
    -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    --with-new-pkgs upgrade
apt-get -y autoremove --purge

echo
if [ -f /var/run/reboot-required ]; then
    echo "Redémarrage requis. boutique-old sera indisponible 1 à 3 minutes."
    if confirmer "Redémarrer maintenant ?"; then
        echo "Après reconnexion, vérifier : systemctl is-active apache2 mysql ; curl -sI https://app.coprotec.net | head -1"
        sleep 3
        reboot
    fi
else
    echo "Pas de redémarrage nécessaire."
fi
