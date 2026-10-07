#!/bin/bash
# Diagnostic d'Apache et de MySQL de l'hôte (boutique-old) avant d'y brancher la nouvelle boutique.
# LECTURE SEULE : ne modifie rien.
#
# Depuis le poste :  scp -P 2268 docker/vps/diag-apache.sh ubuntu@<IP_VPS>:~/
# Sur le VPS      :  sudo bash ~/diag-apache.sh
# Résultat        :  ~/diag-apache.txt

if [ "$(id -u)" -ne 0 ]; then
    echo "À lancer avec sudo : sudo bash $0" >&2
    exit 1
fi

UTILISATEUR="${SUDO_USER:-root}"
SORTIE="$(getent passwd "$UTILISATEUR" | cut -d: -f6)/diag-apache.txt"

section() {
    echo
    echo "===== $1"
}

{
    section "APACHE : version et vhosts actifs"
    apache2 -v 2>&1
    apache2ctl -S 2>&1

    section "APACHE : sites-available / sites-enabled"
    ls -l /etc/apache2/sites-available/ /etc/apache2/sites-enabled/

    section "APACHE : directives clés de chaque vhost actif"
    for f in /etc/apache2/sites-enabled/*; do
        echo "-- $f"
        grep -nEi '^\s*(<VirtualHost|ServerName|ServerAlias|DocumentRoot|SSLCertificate(Key)?File|Include|Proxy|Redirect|RewriteRule|Auth|Require|Alias)' "$f"
    done

    section "APACHE : modules utiles au reverse proxy"
    apache2ctl -M 2>/dev/null | grep -E 'proxy|headers|ssl|rewrite|remoteip|php|http2'

    section "APACHE : services au démarrage"
    echo "apache2 : $(systemctl is-enabled apache2 2>&1) / $(systemctl is-active apache2 2>&1)"
    echo "mysql   : $(systemctl is-enabled mysql 2>&1) / $(systemctl is-active mysql 2>&1)"

    section "APACHE : configuration globale personnalisée"
    ls -l /etc/apache2/conf-enabled/
    grep -RhEi '^\s*(ServerTokens|ServerSignature|Header|Protocols)' /etc/apache2/conf-enabled/ /etc/apache2/apache2.conf 2>/dev/null

    section "FICHIERS D'AUTHENTIFICATION APACHE"
    ls -l /etc/apache2/.htpasswd* /etc/apache2/*.htpasswd 2>&1

    section "RACINES WEB (/var/www)"
    ls -la /var/www/
    for d in /var/www/*/; do
        echo "-- $d"
        ls "$d" | head -15
        [ -d "$d/.git" ] && git -C "$d" log -1 --format='   dernier commit : %h %ci %s' 2>/dev/null
    done

    section "PHP DE L'HÔTE"
    php -v 2>&1 | head -1
    ls /etc/php/ 2>&1

    section "CRON DE L'HÔTE (boutique-old)"
    ls -l /etc/cron.d/ 2>&1
    for u in root ubuntu www-data; do
        echo "-- crontab $u :"
        crontab -l -u "$u" 2>&1 | grep -v '^\s*#' | grep -v '^\s*$'
    done

    section "MYSQL DE L'HÔTE"
    mysql --version 2>&1
    mysql -e "SELECT table_schema AS base, ROUND(SUM(data_length+index_length)/1024/1024,1) AS Mo, COUNT(*) AS tables FROM information_schema.tables WHERE table_schema NOT IN ('mysql','sys','performance_schema','information_schema') GROUP BY table_schema;" 2>&1
    mysql -e "SELECT user, host FROM mysql.user;" 2>&1
    mysql -e "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size','max_connections','bind_address');" 2>&1

    section "CERTBOT : méthode d'obtention des certificats"
    for f in /etc/letsencrypt/renewal/*.conf; do
        echo "-- $f"
        grep -E '^(authenticator|installer|webroot_path)' "$f"
    done
    ls /etc/letsencrypt/options-ssl-apache.conf 2>&1

    section "MEMOIRE PAR PROCESSUS (top 10)"
    ps -eo rss,comm --sort=-rss | head -11 | awk 'NR==1 {print "Mo     processus"; next} {printf "%-6d %s\n", $1/1024, $2}'
} > "$SORTIE" 2>&1

chown "$UTILISATEUR" "$SORTIE"
echo "Terminé : $(wc -l < "$SORTIE") lignes dans $SORTIE"
