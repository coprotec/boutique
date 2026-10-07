#!/bin/bash
# Étape 10 (compte coprotec) — Branche preprod.app.coprotec.net sur le conteneur et supprime l'ancienne préprod.
#   - modules proxy_http et headers ; identifiant TEMPORAIRE pour les testeurs hors des bureaux ;
#   - ancienne préprod : worker supervisor arrêté, vhosts désactivés, dossier archivé puis supprimé ;
#   - nouveau vhost (docker/apache-hote/), configtest avant rechargement, test du renouvellement du certificat.
# Lancer : sudo bash ~/vps/10-apache-preprod.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
confirmer() { read -r -p "$1 [o/N] " r; [[ "$r" =~ ^[oO]$ ]]; }

VHOST=/srv/preprod.app.coprotec.net/docker/apache-hote/preprod.app.coprotec.net.conf
[ -f "$VHOST" ] || { echo "Manque $VHOST : faire l'étape 9 d'abord." >&2; exit 1; }
[ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8095/)" = 200 ] || { echo "La préprod ne répond pas sur 127.0.0.1:8095 : abandon." >&2; exit 1; }

echo "== 1. Modules et identifiant temporaire"
a2enmod proxy_http headers >/dev/null
if [ ! -f /etc/apache2/.htpasswd-boutique ]; then
    read -r -p "Nom de l'identifiant des testeurs [demo] : " NOM
    htpasswd -c /etc/apache2/.htpasswd-boutique "${NOM:-demo}"
fi
chown root:www-data /etc/apache2/.htpasswd-boutique
chmod 640 /etc/apache2/.htpasswd-boutique

echo
echo "== 2. Ancienne préprod : worker et vhosts"
CONSUMER=/etc/supervisor/conf.d/preprod_boutique_messenger_consumer.conf
if [ -f "$CONSUMER" ]; then
    supervisorctl stop preprod_boutique_messenger_consumer || true
    mv "$CONSUMER" "$CONSUMER.off"
    supervisorctl reread && supervisorctl update
fi
for s in preprod-app.conf preprod-app-le-ssl.conf; do
    [ -e "/etc/apache2/sites-enabled/$s" ] && a2dissite "$s" >/dev/null
done

echo
echo "== 3. Nouveau vhost"
cp "$VHOST" /etc/apache2/sites-available/boutique-preprod.conf
a2ensite boutique-preprod.conf >/dev/null
if ! apache2ctl configtest; then
    a2dissite boutique-preprod.conf >/dev/null
    a2ensite preprod-app.conf preprod-app-le-ssl.conf >/dev/null || true
    echo "Configuration invalide : retour à l'ancienne préprod, Apache non rechargé." >&2
    exit 1
fi
systemctl reload apache2
sleep 1
echo "boutique-old : $(curl -s -o /dev/null -w '%{http_code}' https://app.coprotec.net/)   (attendu : 200)"
echo "Préprod vue du serveur (IP hors liste, identifiant exigé) : $(curl -s -o /dev/null -w '%{http_code}' https://preprod.app.coprotec.net/)   (attendu : 401)"
echo "Notification Monetico ouverte : $(curl -s -o /dev/null -w '%{http_code}' -X POST https://preprod.app.coprotec.net/paiement/monetico/notification)   (attendu : autre que 401)"

echo
echo "== 4. Renouvellement du certificat (test à blanc)"
certbot renew --dry-run --cert-name preprod.app.coprotec.net

echo
echo "== 5. Suppression de l'ancienne préprod"
ANCIEN=/var/www/preprod.app.coprotec.net
if [ -d "$ANCIEN" ] && confirmer "Archiver puis SUPPRIMER $ANCIEN et ses vhosts (retour arrière impossible ensuite) ?"; then
    ARCHIVE="/home/coprotec/archives/ancienne-preprod-$(date +%Y%m%d).tar.gz"
    install -d -o coprotec -g coprotec /home/coprotec/archives
    CONFS=()
    for f in /etc/apache2/sites-available/preprod-app.conf /etc/apache2/sites-available/preprod-app-le-ssl.conf "$CONSUMER.off"; do
        [ -f "$f" ] && CONFS+=("${f#/}")
    done
    # L'archive doit réussir AVANT toute suppression (set -e arrête le script sinon).
    tar czf "$ARCHIVE" --exclude='*/vendor' --exclude='*/node_modules' --exclude='*/var/cache' \
        -C / "var/www/preprod.app.coprotec.net" "${CONFS[@]}"
    chown coprotec:coprotec "$ARCHIVE"
    ls -lh "$ARCHIVE"
    rm -rf "$ANCIEN"
    rm -f /etc/apache2/sites-available/preprod-app.conf /etc/apache2/sites-available/preprod-app-le-ssl.conf "$CONSUMER.off"
    echo "Ancienne préprod supprimée. Sa base MySQL éventuelle (MySQL de l'hôte) reste à supprimer à la main."
fi

echo
echo "Ouvre https://preprod.app.coprotec.net (bureaux : accès direct ; ailleurs : identifiant)."
echo "Suite : bash ~/vps/11-github-actions.sh   (SANS sudo)"
