#!/bin/bash
# Diagnostic du VPS avant configuration (DEPLOIEMENT_VPS.md §1). LECTURE SEULE : ne modifie rien.
#
# Depuis le poste :  scp -P <PORT_SSH> docker/vps/diag-vps.sh <ADMIN>@<IP_VPS>:~/
# Sur le VPS      :  sudo bash ~/diag-vps.sh
# Résultat        :  ~/diag-vps.txt (à transmettre ; masquer l'IP publique si besoin)

if [ "$(id -u)" -ne 0 ]; then
    echo "À lancer avec sudo : sudo bash $0" >&2
    exit 1
fi

UTILISATEUR="${SUDO_USER:-root}"
SORTIE="$(getent passwd "$UTILISATEUR" | cut -d: -f6)/diag-vps.txt"

section() {
    echo
    echo "===== $1"
}

{
    section "DATE"
    date

    section "SYSTEME"
    grep -E '^(PRETTY_NAME|VERSION_ID)' /etc/os-release
    uname -r
    uptime

    section "REDEMARRAGE REQUIS"
    if [ -f /var/run/reboot-required ]; then
        echo "OUI"
        head -20 /var/run/reboot-required.pkgs 2>/dev/null
    else
        echo "non"
    fi

    section "MISES A JOUR EN ATTENTE"
    apt list --upgradable 2>/dev/null | grep -c upgradable
    apt list --upgradable 2>/dev/null | grep -iE 'openssh|docker|nginx|linux-image|containerd|certbot'

    section "DISQUE / MEMOIRE"
    df -h / /var/lib/docker 2>/dev/null
    free -h

    section "COMPTES"
    echo "connecté via sudo : $UTILISATEUR"
    id "$UTILISATEUR"
    echo "-- comptes humains (uid >= 1000) et shell :"
    getent passwd | awk -F: '$3>=1000 && $3<65534 {print $1" "$7}'
    echo "-- groupes sudo et docker :"
    getent group sudo docker

    section "CLES SSH AUTORISEES"
    for h in /root /home/*; do
        f="$h/.ssh/authorized_keys"
        if [ -f "$f" ]; then
            echo "$f : $(grep -c . "$f") clé(s)"
            awk '{print "   type=" $1 "  commentaire=" $NF}' "$f"
        fi
    done

    section "SSHD (configuration effective)"
    sshd -T 2>&1 | grep -Ei '^(port|listenaddress|permitrootlogin|passwordauthentication|kbdinteractiveauthentication|pubkeyauthentication|authenticationmethods|allowusers|allowgroups|maxauthtries|x11forwarding)'

    section "SSHD (fichiers)"
    grep -i '^Include' /etc/ssh/sshd_config
    ls -l /etc/ssh/sshd_config.d/ 2>&1
    for f in /etc/ssh/sshd_config.d/*.conf; do
        [ -f "$f" ] || continue
        echo "-- $f"
        grep -v '^\s*#' "$f" | grep -v '^\s*$'
    done

    section "SSH : socket ou service"
    echo "ssh.socket  : $(systemctl is-active ssh.socket 2>&1)"
    echo "ssh.service : $(systemctl is-active ssh.service 2>&1)"
    systemctl cat ssh.socket 2>/dev/null | grep -i ListenStream

    section "PORTS EN ECOUTE"
    ss -tlnp

    section "PARE-FEU UFW"
    ufw status verbose 2>&1

    section "IPTABLES INPUT"
    iptables -S INPUT 2>/dev/null | head -30

    section "FAIL2BAN / MISES A JOUR AUTOMATIQUES"
    echo "fail2ban            : $(systemctl is-active fail2ban 2>&1)"
    echo "unattended-upgrades : $(systemctl is-active unattended-upgrades 2>&1)"
    fail2ban-client status 2>/dev/null

    section "DOCKER"
    docker version --format 'client {{.Client.Version}} / serveur {{.Server.Version}}' 2>&1
    docker compose version 2>&1
    echo "-- /etc/docker/daemon.json :"
    cat /etc/docker/daemon.json 2>/dev/null || echo "(absent)"

    section "CONTENEURS"
    docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1

    section "POLITIQUE DE REDEMARRAGE DES CONTENEURS"
    for c in $(docker ps -aq 2>/dev/null); do
        docker inspect --format '{{.Name}} : {{.HostConfig.RestartPolicy.Name}}' "$c"
    done

    section "PROJETS COMPOSE"
    docker compose ls 2>&1

    section "ESPACE DOCKER"
    docker system df 2>&1

    section "NGINX"
    nginx -v 2>&1
    echo "-- sites-enabled :"
    ls -l /etc/nginx/sites-enabled/ 2>&1
    echo "-- snippets :"
    ls -l /etc/nginx/snippets/ 2>&1
    echo "-- fichiers htpasswd :"
    ls -l /etc/nginx/.htpasswd* 2>&1
    echo "-- server_name et proxy_pass déclarés :"
    grep -RhE '^\s*(server_name|proxy_pass|listen)' /etc/nginx/sites-enabled/ 2>/dev/null | sed 's/^\s*//' | sort | uniq -c

    section "CERTIFICATS LET'S ENCRYPT"
    certbot certificates 2>&1 | grep -E 'Certificate Name|Domains|Expiry'
    systemctl list-timers 2>/dev/null | grep -i certbot

    section "/srv"
    ls -la /srv 2>&1

    section "FUSEAU HORAIRE"
    timedatectl | grep -E 'Time zone|synchronized'
} > "$SORTIE" 2>&1

chown "$UTILISATEUR" "$SORTIE"
echo "Terminé : $(wc -l < "$SORTIE") lignes dans $SORTIE"
