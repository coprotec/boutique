#!/bin/bash
# Étape 9 (compte coprotec, SANS sudo) — Nouvelle préprod : clé de lecture GitHub, clonage, secrets, premier démarrage.
# La préprod écoute alors sur 127.0.0.1:8095 ; elle devient visible à l'étape 10 (vhost Apache).
# Lancer : bash ~/vps/09-preprod.sh
set -euo pipefail
[ "$(id -un)" = coprotec ] && [ "$(id -u)" -ne 0 ] || { echo "À lancer en coprotec, SANS sudo : bash $0" >&2; exit 1; }
docker info >/dev/null 2>&1 || { echo "Docker inaccessible : déconnecte-toi et reconnecte-toi après l'étape 8 (groupe docker)." >&2; exit 1; }

DEPOT=git@github.com:coprotec/boutique.git
DOSSIER=/srv/preprod.app.coprotec.net
CLE="$HOME/.ssh/github_boutique"

echo "== 1. Clé de lecture du dépôt coprotec/boutique"
if [ ! -f "$CLE" ]; then
    ssh-keygen -t ed25519 -C "coprotec@vps boutique (lecture)" -f "$CLE" -N ""
    touch "$HOME/.ssh/config"
    grep -q 'Host github.com' "$HOME/.ssh/config" || printf 'Host github.com\n    IdentityFile ~/.ssh/github_boutique\n    IdentitiesOnly yes\n' >> "$HOME/.ssh/config"
    chmod 600 "$HOME/.ssh/config"
fi
# « ssh -T git@github.com » sort toujours en erreur (GitHub refuse le shell), même authentifié :
# on teste donc le message, pas le code de retour.
github_ok() {
    local sortie
    sortie="$(ssh -o StrictHostKeyChecking=accept-new -T git@github.com 2>&1 || true)"
    echo "$sortie"
    grep -q 'successfully authenticated' <<<"$sortie"
}
if ! github_ok; then
    echo
    echo "Ajoute cette clé sur GitHub : dépôt coprotec/boutique → Settings → Deploy keys → Add deploy key"
    echo "(titre : vps-coprotec, NE PAS cocher « Allow write access ») :"
    echo "(« Key is already in use » = déjà ajoutée : continue simplement)"
    echo
    cat "$CLE.pub"
    echo
    read -r -p "Appuie sur Entrée une fois la clé ajoutée..."
    github_ok || { echo "Accès GitHub refusé." >&2; exit 1; }
fi

echo
echo "== 2. Code (branche preprod)"
if [ -d "$DOSSIER/.git" ]; then
    git -C "$DOSSIER" fetch origin
    git -C "$DOSSIER" switch preprod 2>/dev/null || git -C "$DOSSIER" switch -c preprod --track origin/preprod
    git -C "$DOSSIER" pull --ff-only origin preprod
else
    sudo install -d -o coprotec -g coprotec "$DOSSIER"
    git clone -b preprod "$DEPOT" "$DOSSIER"
fi
git -C "$DOSSIER" log -1 --format='Version : %h %ci %s'

echo
echo "== 3. Secrets (.env.preprod.local)"
if [ -f "$HOME/vps/.env.preprod.local" ]; then
    mv "$HOME/vps/.env.preprod.local" "$DOSSIER/.env.preprod.local"
fi
[ -f "$DOSSIER/.env.preprod.local" ] || { echo "Manque $DOSSIER/.env.preprod.local (scp depuis le poste)." >&2; exit 1; }
# Lisible par PHP dans le conteneur (www-data, gid 33) : 640 coprotec:www-data
sudo chown coprotec:www-data "$DOSSIER/.env.preprod.local"
sudo chmod 640 "$DOSSIER/.env.preprod.local"
ls -l "$DOSSIER/.env.preprod.local"

echo
echo "== 4. Construction et démarrage (quelques minutes la première fois)"
cd "$DOSSIER"
bash deploy.sh preprod

echo
echo "== 5. Vérifications"
docker compose -f docker-compose.preprod.yml ps
echo "Réponse locale : $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8095/)   (attendu : 200)"
ss -tln | grep 8095 || echo "ATTENTION : rien n'écoute sur 8095"
free -h
echo
echo "Suite : sudo bash ~/vps/10-apache-preprod.sh"
