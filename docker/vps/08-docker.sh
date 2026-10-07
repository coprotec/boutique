#!/bin/bash
# Étape 8 (compte coprotec) — Docker (dépôt officiel), rotation des journaux, coprotec dans le groupe docker.
# Rappel : Docker contourne UFW pour les ports publiés ; la boutique ne publie que sur 127.0.0.1.
# Lancer : sudo bash 08-docker.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }

if ! command -v docker >/dev/null; then
    apt-get install -y ca-certificates curl
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu jammy stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

if [ ! -f /etc/docker/daemon.json ]; then
    cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
EOF
    systemctl restart docker
fi
systemctl enable docker
usermod -aG docker coprotec

docker --version
docker compose version
echo
echo "IMPORTANT : déconnecte-toi puis reconnecte-toi (groupe docker), puis : bash ~/vps/09-preprod.sh   (SANS sudo)"
