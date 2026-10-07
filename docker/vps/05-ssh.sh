#!/bin/bash
# Étape 5 (compte coprotec) — SSH : connexion par clé uniquement, root interdit, seul « coprotec » autorisé, port 2268.
# GARDER CETTE SESSION OUVERTE et tester dans un second terminal avant de la fermer.
# Lancer : sudo bash 05-ssh.sh
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Lancer avec : sudo bash $0" >&2; exit 1; }
[ "${SUDO_USER:-}" = coprotec ] || { echo "À lancer connecté en coprotec : sinon tu risques de te bloquer dehors." >&2; exit 1; }
[ -s /home/coprotec/.ssh/authorized_keys ] || { echo "Aucune clé dans /home/coprotec/.ssh/authorized_keys : abandon." >&2; exit 1; }

cat > /etc/ssh/sshd_config.d/00-durcissement.conf <<'EOF'
# Connexion par clé uniquement, compte nominatif unique, port unique (DEPLOIEMENT_VPS.md étape 5).
Port 2268
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
AllowUsers coprotec
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
AllowAgentForwarding no
EOF

if ! sshd -t; then
    rm -f /etc/ssh/sshd_config.d/00-durcissement.conf
    echo "Configuration invalide : fichier retiré, rien n'a changé." >&2
    exit 1
fi
systemctl restart ssh
sshd -T | grep -Ei '^(port|permitrootlogin|passwordauthentication|allowusers|x11forwarding)'

cat <<'EOF'

TESTS depuis ton poste, dans un SECOND terminal (ne ferme pas celui-ci avant) :
    ssh boutique-vps                                       → doit marcher
    ssh -p 2268 ubuntu@<IP_VPS>                            → doit être REFUSÉ
    ssh -p 2268 root@<IP_VPS>                              → doit être REFUSÉ
    ssh -p 2268 -o PubkeyAuthentication=no coprotec@<IP_VPS>   → doit être REFUSÉ
En cas de problème, depuis CETTE session :
    sudo rm /etc/ssh/sshd_config.d/00-durcissement.conf && sudo systemctl restart ssh
Suite : sudo bash 06-desactiver-ubuntu.sh
EOF
