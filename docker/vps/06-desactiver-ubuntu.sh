#!/bin/bash
# Étape 6 (compte coprotec) — Désactive le compte ubuntu (sans le supprimer) :
# mot de passe verrouillé, plus de shell, plus de sudo (y compris la règle NOPASSWD de cloud-init), plus de clé SSH.
# Lancer : sudo bash 06-desactiver-ubuntu.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
[ "${SUDO_USER:-}" = coprotec ] || { echo "À lancer connecté en coprotec." >&2; exit 1; }
sudo -l -U coprotec | grep -q '(ALL' || { echo "coprotec n'a pas les droits sudo : abandon." >&2; exit 1; }

SAUVEGARDE="/root/sauvegarde-ubuntu-$(date +%Y%m%d-%H%M)"
install -d -m 700 "$SAUVEGARDE"

# Règle sudo de cloud-init : « ubuntu ALL=(ALL) NOPASSWD:ALL » (présente deux fois)
F=/etc/sudoers.d/90-cloud-init-users
if [ -f "$F" ]; then
    cp -a "$F" "$SAUVEGARDE/"
    sed -i -E 's/^(ubuntu[[:space:]]+ALL=)/# désactivé (DEPLOIEMENT_VPS.md) : \1/' "$F"
    if ! visudo -c >/dev/null; then
        cp -a "$SAUVEGARDE/90-cloud-init-users" "$F"
        echo "sudoers invalide : fichier restauré." >&2
        exit 1
    fi
fi
gpasswd -d ubuntu sudo 2>/dev/null || true
passwd -l ubuntu
usermod -s /usr/sbin/nologin ubuntu
[ -f /home/ubuntu/.ssh/authorized_keys ] && mv /home/ubuntu/.ssh/authorized_keys "$SAUVEGARDE/"

echo
passwd -S ubuntu
id ubuntu
grep -n ubuntu "$F" 2>/dev/null || true
echo "Sauvegardes : $SAUVEGARDE"
echo "Sessions ubuntu encore ouvertes (à fermer) :"; who | grep '^ubuntu' || echo "aucune"
echo
echo "Suite : sudo bash 07-securite.sh"
