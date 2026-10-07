#!/bin/bash
# Étape 7 (compte coprotec) — fail2ban (SSH + identifiant de la préprod), signature d'Apache masquée, phpMyAdmin.
# Lancer : sudo bash 07-securite.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
confirmer() { read -r -p "$1 [o/N] " r; [[ "$r" =~ ^[oO]$ ]]; }

echo "== fail2ban"
apt-get install -y fail2ban
cat > /etc/fail2ban/jail.d/coprotec.local <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
# IP des bureaux COPROTEC : jamais bannies
ignoreip = 127.0.0.1/8 ::1 89.87.179.97 89.87.179.99

[sshd]
enabled = true
port    = 2268

[apache-auth]
enabled = true
EOF
systemctl enable fail2ban
systemctl restart fail2ban
sleep 2
fail2ban-client status

echo
echo "== Apache : ne plus annoncer la version ni le système"
sed -i -E 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
grep -E '^(ServerTokens|ServerSignature)' /etc/apache2/conf-available/security.conf

echo
echo "== phpMyAdmin (joignable aujourd'hui sur tous les sites : /phpmyadmin)"
if [ -e /etc/apache2/conf-enabled/phpmyadmin.conf ] && confirmer "Désactiver phpMyAdmin ? (sinon il reste accessible à tous)"; then
    a2disconf phpmyadmin
fi

apache2ctl configtest
systemctl reload apache2
echo "boutique-old : $(curl -s -o /dev/null -w '%{http_code}' https://app.coprotec.net/)"
echo
echo "Suite : sudo bash 08-docker.sh"
