#!/bin/bash
# Étape 3 (compte ubuntu) — Crée « coprotec », futur compte d'administration et de déploiement.
#   - mot de passe : uniquement pour sudo (la connexion SSH se fera par clé seulement, étape 5) ;
#   - groupes : sudo, adm (journaux), www-data (fichiers web) ; docker sera ajouté à l'étape 8 ;
#   - installe ta clé publique SSH et recopie les scripts dans ~coprotec/vps/.
# Lancer : sudo bash 03-compte-coprotec.sh ~/coprotec_vps.pub
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0 <clé publique>" >&2; exit 1; }

CLE="${1:-}"
[ -f "$CLE" ] && grep -qE '^ssh-(ed25519|rsa) ' "$CLE" || { echo "Usage : sudo bash $0 <fichier .pub de ta clé SSH>" >&2; exit 1; }

if id coprotec >/dev/null 2>&1; then
    echo "Le compte coprotec existe déjà."
else
    adduser --gecos "COPROTEC" coprotec      # demande le mot de passe (sudo uniquement)
fi
usermod -aG sudo,adm,www-data coprotec

install -d -m 700 -o coprotec -g coprotec /home/coprotec/.ssh
touch /home/coprotec/.ssh/authorized_keys
grep -qxF "$(cat "$CLE")" /home/coprotec/.ssh/authorized_keys || cat "$CLE" >> /home/coprotec/.ssh/authorized_keys
chown coprotec:coprotec /home/coprotec/.ssh/authorized_keys
chmod 600 /home/coprotec/.ssh/authorized_keys

# Scripts et fichier de secrets de la préprod (s'ils ont été copiés à côté de ce script)
ICI="$(cd "$(dirname "$0")" && pwd)"
install -d -m 700 -o coprotec -g coprotec /home/coprotec/vps
cp "$ICI"/[0-9][0-9]-*.sh /home/coprotec/vps/
[ -f "$ICI/.env.preprod.local" ] && cp "$ICI/.env.preprod.local" /home/coprotec/vps/
chown -R coprotec:coprotec /home/coprotec/vps
chmod 600 /home/coprotec/vps/.env.preprod.local 2>/dev/null || true

echo
id coprotec
echo "Clés autorisées : $(grep -c . /home/coprotec/.ssh/authorized_keys)"
ls -la /home/coprotec/vps
cat <<'EOF'

SUITE — depuis ton poste, dans un SECOND terminal (garde celui-ci ouvert) :
    ssh boutique-vps          (Host boutique-vps : User coprotec, Port 2268, IdentityFile ta clé)
    sudo -v                   (mot de passe de coprotec)
Si les deux marchent, continue en coprotec : cd ~/vps && sudo bash 04-transfert-ubuntu.sh
EOF
