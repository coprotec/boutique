#!/bin/bash
# Étape 11 (compte coprotec, SANS sudo) — Clé dédiée au déploiement automatique (GitHub Actions → VPS),
# LIMITÉE À UNE SEULE COMMANDE : quoi que GitHub envoie, sshd exécute ~/bin/deploy-gha.sh, qui n'accepte que
# « deploy preprod » ou « deploy prod ». Option « restrict » : ni terminal, ni redirection de port, ni agent.
# La clé est générée sur le serveur, la partie privée est affichée pour le secret GitHub puis effacée.
# Lancer : bash ~/vps/11-github-actions.sh
set -euo pipefail
[ "$(id -un)" = coprotec ] && [ "$(id -u)" -ne 0 ] || { echo "À lancer en coprotec, SANS sudo : bash $0" >&2; exit 1; }

ICI="$(cd "$(dirname "$0")" && pwd)"
SOURCE=/srv/preprod.app.coprotec.net/docker/vps/deploy-gha.sh
[ -f "$SOURCE" ] || SOURCE="$ICI/deploy-gha.sh"
[ -f "$SOURCE" ] || { echo "deploy-gha.sh introuvable (étape 9 faite ?)." >&2; exit 1; }

echo "== 1. Commande forcée"
install -d -m 700 "$HOME/bin"
install -m 700 "$SOURCE" "$HOME/bin/deploy-gha.sh"
ls -l "$HOME/bin/deploy-gha.sh"

echo
echo "== 2. Clé (remplace une éventuelle clé « github-actions boutique » précédente)"
TMP="$(mktemp -d)"
trap 'shred -u "$TMP"/gha "$TMP"/gha.pub 2>/dev/null; rm -rf "$TMP"' EXIT
ssh-keygen -q -t ed25519 -C "github-actions boutique" -f "$TMP/gha" -N ""
AK="$HOME/.ssh/authorized_keys"
cp -a "$AK" "$AK.avant-gha"
grep -v 'github-actions boutique' "$AK.avant-gha" > "$AK" || true
echo "restrict,command=\"$HOME/bin/deploy-gha.sh\" $(cat "$TMP/gha.pub")" >> "$AK"
chmod 600 "$AK"
grep -c . "$AK" | xargs echo "Clés autorisées :"

echo
echo "== 3. Empreinte du serveur (secret REMOTE_KNOWN_HOSTS, protège contre l'usurpation du serveur)"
KNOWN="$(ssh-keyscan -p 2268 -t ed25519 127.0.0.1 2>/dev/null | sed 's/^\[127.0.0.1\]:2268/__HOTE__/')"

IP="$(curl -s -4 ifconfig.me || echo '<IP_VPS>')"
cat <<EOF

Sur GitHub, dépôt coprotec/boutique → Settings :
  1. Environments : créer « preprod » et « prod » (pour prod : Required reviewers = toi).
  2. Secrets and variables → Actions → New repository secret :
       REMOTE_HOST         = $IP
       REMOTE_PORT         = 2268
       REMOTE_USER         = coprotec
       REMOTE_KNOWN_HOSTS  = ${KNOWN/__HOTE__/[$IP]:2268}
       SSH_PRIVATE_KEY     = tout le bloc ci-dessous, lignes BEGIN et END comprises :

EOF
cat "$TMP/gha"
cat <<'EOF'

La clé privée est effacée du serveur à la fin de ce script : copie-la MAINTENANT.
Test (depuis le serveur, doit être REFUSÉ puis journalisé) : journalctl -t deploy-gha -n 20
Révoquer : supprimer la ligne « github-actions boutique » de ~/.ssh/authorized_keys.
EOF
read -r -p "Appuie sur Entrée une fois les secrets enregistrés..."
