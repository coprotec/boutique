#!/bin/bash
# Ce qui dépend du compte « ubuntu », avant de transférer l'administration et les projets au compte « coprotec »
# (DEPLOIEMENT_VPS.md §4). LECTURE SEULE : ne modifie rien.
#
# Depuis le poste :  scp -P 2268 docker/vps/diag-ubuntu.sh ubuntu@<IP_VPS>:~/
# Sur le VPS      :  sudo bash ~/diag-ubuntu.sh
# Résultat        :  ~/diag-ubuntu.txt

if [ "$(id -u)" -ne 0 ]; then
    echo "À lancer avec sudo : sudo bash $0" >&2
    exit 1
fi

COMPTE=ubuntu
UTILISATEUR="${SUDO_USER:-root}"
SORTIE="$(getent passwd "$UTILISATEUR" | cut -d: -f6)/diag-ubuntu.txt"

section() {
    echo
    echo "===== $1"
}

{
    section "COMPTE $COMPTE"
    id "$COMPTE"
    getent passwd "$COMPTE"
    passwd -S "$COMPTE"
    echo "-- sudoers propres au compte :"
    grep -RHn "$COMPTE" /etc/sudoers /etc/sudoers.d/ 2>/dev/null

    section "PROCESSUS QUI TOURNENT SOUS $COMPTE"
    ps -u "$COMPTE" -o pid,etime,cmd --no-headers 2>/dev/null | grep -vE 'sshd:|-bash$|bash$|ps -u|systemd --user|\(sd-pam\)' || echo "aucun (hors session SSH)"

    section "SERVICES / SUPERVISOR / PHP-FPM LANCÉS SOUS $COMPTE"
    grep -RHnE "^\s*(User|user)\s*=\s*$COMPTE" /etc/systemd/system /lib/systemd/system /etc/supervisor /etc/php/*/fpm/pool.d 2>/dev/null || echo "aucun"
    echo "-- programmes supervisor :"
    ls -l /etc/supervisor/conf.d/ 2>/dev/null
    for f in /etc/supervisor/conf.d/*.conf; do
        [ -f "$f" ] || continue
        echo "   $f :"
        grep -nE '^\s*(\[program|command|user|directory)' "$f" | sed 's/^/      /'
    done

    section "TÂCHES PLANIFIÉES"
    crontab -l -u "$COMPTE" 2>&1
    grep -RHn "$COMPTE" /etc/cron* 2>/dev/null

    section "FICHIERS APPARTENANT À $COMPTE HORS DE SON DOSSIER PERSONNEL (dossiers de 1er niveau)"
    find / -xdev \( -path /proc -o -path /sys -o -path /home/$COMPTE -o -path /snap -o -path /var/lib/docker \) -prune -o -user "$COMPTE" -print 2>/dev/null \
        | awk -F/ '{print "/"$2"/"$3"/"$4}' | sort | uniq -c | sort -rn | head -30

    section "DROITS DES DOSSIERS WEB (propriétaire:groupe, 2 niveaux)"
    for d in /var/www/app /var/www/preprod.app.coprotec.net; do
        [ -d "$d" ] || continue
        echo "-- $d"
        find "$d" -maxdepth 2 -not -path '*/vendor/*' -not -path '*/.git/*' -printf '%u:%g %m %p\n' 2>/dev/null | awk '{print $1" "$2}' | sort | uniq -c | sort -rn | head -10
        echo "   fichiers/dossiers dont le groupe est www-data : $(find "$d" -group www-data 2>/dev/null | wc -l)"
        echo "   fichiers/dossiers dont le propriétaire est www-data : $(find "$d" -user www-data 2>/dev/null | wc -l)"
    done

    section "DÉPÔTS GIT DES PROJETS (remote, méthode d'accès)"
    for d in /var/www/app /var/www/preprod.app.coprotec.net/httpdocs; do
        [ -d "$d/.git" ] || continue
        echo "-- $d"
        git -C "$d" remote -v 2>/dev/null | sed -E 's#(https://)[^@/]+@#\1***@#'
        git -C "$d" config --list --show-origin 2>/dev/null | grep -iE 'safe.directory|credential|user\.' | sed 's/^/   /'
    done

    section "CLÉS ET IDENTIFIANTS DANS LE DOSSIER DE $COMPTE"
    H="$(getent passwd "$COMPTE" | cut -d: -f6)"
    ls -la "$H/.ssh/" 2>&1
    [ -f "$H/.ssh/config" ] && { echo "-- $H/.ssh/config :"; cat "$H/.ssh/config"; }
    for k in "$H"/.ssh/*.pub; do
        [ -f "$k" ] && echo "clé : $k ($(awk '{print $1" "$NF}' "$k"))"
    done
    echo "-- .gitconfig :"
    cat "$H/.gitconfig" 2>/dev/null || echo "(absent)"
    echo "-- .git-credentials : $([ -f "$H/.git-credentials" ] && echo PRÉSENT || echo absent)"
    echo "-- known_hosts : $(wc -l < "$H/.ssh/known_hosts" 2>/dev/null || echo 0) entrée(s)"
    echo "-- outils dans le dossier : composer=$([ -d "$H/.composer" ] || [ -d "$H/.config/composer" ] && echo oui || echo non) nvm=$([ -d "$H/.nvm" ] && echo oui || echo non)"
    echo "-- contenu du dossier personnel :"
    ls -la "$H" | head -40

    section "COMPTE coprotec (existe déjà ?)"
    id coprotec 2>&1
} > "$SORTIE" 2>&1

chown "$UTILISATEUR" "$SORTIE"
echo "Terminé : $(wc -l < "$SORTIE") lignes dans $SORTIE"
