#!/bin/bash
# Étape 4 (compte coprotec) — Transfère à coprotec ce qui appartient à ubuntu, sans arrêter boutique-old :
#   - propriétaire des fichiers de /var/www/app (les fichiers www-data ne bougent pas) ;
#   - archives de la prod laissées dans /home/ubuntu (patch, statut, fichiers non suivis) ;
#   - suppression des clés privées de ubuntu (clé personnelle RSA et clé GitHub de boutique-old, plus utilisées).
# L'ancienne préprod (/var/www/preprod.app.coprotec.net) n'est pas transférée : elle est supprimée à l'étape 10.
# Lancer : sudo bash 04-transfert-ubuntu.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
[ "${SUDO_USER:-}" = coprotec ] || { echo "À lancer connecté en coprotec (sudo bash $0)." >&2; exit 1; }
confirmer() { read -r -p "$1 [o/N] " r; [[ "$r" =~ ^[oO]$ ]]; }

echo "== 1. Fichiers de boutique-old (/var/www/app)"
chown -R --from=ubuntu coprotec /var/www/app
chown -R --from=:ubuntu :coprotec /var/www/app
reste="$(find /var/www/app -user ubuntu -o -group ubuntu | wc -l)"
echo "Fichiers encore à ubuntu : $reste"
echo "boutique-old : $(curl -s -o /dev/null -w '%{http_code}' https://app.coprotec.net/)"

echo
echo "== 2. Archives de la prod laissées par ubuntu"
install -d -o coprotec -g coprotec /home/coprotec/archives
for f in prod-local-changes.patch prod-status.txt prod-untracked.tar.gz; do
    [ -f "/home/ubuntu/$f" ] && cp -a "/home/ubuntu/$f" /home/coprotec/archives/
done
chown -R coprotec:coprotec /home/coprotec/archives
ls -l /home/coprotec/archives

echo
echo "== 3. Clés privées de ubuntu"
ls -l /home/ubuntu/.ssh/
if confirmer "Supprimer id_rsa, id_rsa.pub, github_boutique, github_boutique.pub et config de /home/ubuntu/.ssh ?"; then
    for f in id_rsa id_rsa.pub github_boutique github_boutique.pub config; do
        [ -f "/home/ubuntu/.ssh/$f" ] && shred -u "/home/ubuntu/.ssh/$f" 2>/dev/null || rm -f "/home/ubuntu/.ssh/$f"
    done
    ls -l /home/ubuntu/.ssh/
    echo "Pense à retirer la deploy key « deploy-boutique-vps » du dépôt GitHub boutique-old (Settings → Deploy keys)."
fi

echo
echo "Suite : sudo bash 05-ssh.sh"
