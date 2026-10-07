# Mise en place du VPS — nouvelle boutique (préprod puis prod)

Procédure pour le VPS qui héberge aujourd'hui **`boutique-old`** (prod sur `app.coprotec.net`, plus aucun déploiement prévu dessus).
Les deux boutiques **cohabitent** jusqu'à la bascule. Rien ici n'interrompt `boutique-old`, sauf le redémarrage de l'étape 1, à planifier.

Chaque étape correspond à un script de `docker/vps/`. Les scripts sont commentés et vérifient leurs prérequis. Ils demandent confirmation avant les actions irréversibles. **Lis chaque script avant de le lancer.**

But final, comme sur un serveur installé proprement :

| Compte | Rôle | Connexion |
|---|---|---|
| `coprotec` | administration (sudo), propriétaire des projets, déploiement de la nouvelle boutique (à la main et via GitHub Actions) | **clé SSH uniquement**, port 2268 ; mot de passe réservé à `sudo` |
| `ubuntu` | compte créé par l'hébergeur : fichiers transférés à `coprotec`, puis **désactivé** | aucune |
| `root` | — | aucune |

## Le serveur (diagnostics du 2026-10-07)

| Élément | État constaté | Étape |
|---|---|---|
| Système | Ubuntu 22.04.5, 81 mises à jour, **redémarrage requis** | 1 |
| Mémoire | **2 Go, pas de swap** ; MySQL de l'hôte ≈ 400 Mo | 2 (+ MySQL des conteneurs bridés à 128 Mo) |
| Comptes | `ubuntu` seul (sudo `NOPASSWD` via cloud-init), propriétaire de `/var/www/app` ; ses clés privées (RSA perso, GitHub) sur le serveur | 3 à 6 |
| SSH | port 2268, **mot de passe autorisé**, root par clé, X11 | 5 |
| Git de `boutique-old` | tire depuis `coprotec/boutique`, **qui est désormais le nouveau projet** | 1 (corrigé vers `coprotec/boutique-old`) |
| Pare-feu | UFW : 2268, 80, 443 | déjà correct |
| fail2ban, signature Apache, phpMyAdmin | absent ; version affichée ; phpMyAdmin ouvert à tous | 7 |
| Web | Apache de l'hôte sur 80/443, sans Docker ni Nginx | 8 (Docker), 10 (reverse proxy) |
| Ancienne préprod | `/var/www/preprod.app.coprotec.net`, vhosts `preprod-app*.conf`, worker supervisor | 10 (supprimée) |
| Certificats | certbot snap, méthode `apache` ; celui de `preprod.app.coprotec.net` est réutilisé | 10 |

Nouvelle boutique : ports **127.0.0.1:8095** (préprod) et **127.0.0.1:8096** (prod) uniquement. IP des bureaux : **89.87.179.97** et **89.87.179.99**.

---

## Avant de commencer

1. Vérifie que tu as accès à la **console de secours** de l'hébergeur (KVM/VNC). C'est le seul recours en cas d'erreur de configuration SSH.
2. Fais un **snapshot** du VPS si l'offre le permet.
3. **Le code doit être poussé** sur `coprotec/boutique` (branche `main`) avant l'étape 9.
4. Sur ton poste (PowerShell), crée ta clé SSH, avec une phrase de passe :
   ```powershell
   ssh-keygen -t ed25519 -C "q.beck@coprotec.net - VPS boutique" -f $HOME\.ssh\coprotec_vps
   ```
   Ajoute ensuite dans `$HOME\.ssh\config` :
   ```
   Host boutique-vps
       HostName <IP_VPS>
       Port 2268
       User coprotec
       IdentityFile ~/.ssh/coprotec_vps
       IdentitiesOnly yes
   ```
5. Copie les scripts, ta clé publique et le fichier de secrets de la préprod (à la racine du projet, non versionné) chez `ubuntu` :
   ```powershell
   scp -P 2268 docker/vps/*.sh .env.preprod.local $HOME\.ssh\coprotec_vps.pub ubuntu@<IP_VPS>:~/
   ```

---

## Étapes en compte `ubuntu`

### Étape 1 — Adresse git de boutique-old, mises à jour, redémarrage

```bash
sudo bash ~/01-mise-a-jour.sh
```

Ce script :
- fait pointer `/var/www/app` (et l'ancienne préprod) vers `coprotec/boutique-old` ;
- vérifie qu'Apache et MySQL redémarrent seuls ;
- installe les mises à jour, puis propose le redémarrage. **`boutique-old` est coupée 1 à 3 minutes.**

Après reconnexion :

```bash
systemctl is-active apache2 mysql && curl -sI https://app.coprotec.net | head -1
```

Sur ton poste, corrige aussi ton clone local de boutique-old :

```powershell
git -C ..\boutique-old remote set-url origin git@github.com:coprotec/boutique-old.git
```

### Étape 2 — Swap

```bash
sudo bash ~/02-swap.sh
```

Tant que la préprod, la prod et `boutique-old` tournent ensemble, **passer le VPS à 4 Go** reste la solution durable.

### Étape 3 — Compte `coprotec`

```bash
sudo bash ~/03-compte-coprotec.sh ~/coprotec_vps.pub
```

Le script crée le compte. Le mot de passe demandé sert **uniquement à `sudo`**. Il installe aussi ta clé, puis recopie les scripts et `.env.preprod.local` dans `~coprotec/vps/`.

**Test dans un second terminal, en gardant la session `ubuntu` ouverte :** `ssh boutique-vps`, puis `sudo -v`. Les deux doivent marcher.

---

## Étapes en compte `coprotec` (`ssh boutique-vps`, puis `cd ~/vps`)

### Étape 4 — Reprise de ce qui appartenait à `ubuntu`

```bash
sudo bash 04-transfert-ubuntu.sh
```

Ce script :
- donne `/var/www/app` à `coprotec` ; les fichiers en `www-data` ne bougent pas ;
- copie les archives de prod de juillet dans `~/archives` ;
- **supprime les clés privées de `ubuntu`** : ta clé RSA personnelle et la clé GitHub de `boutique-old`.

Ensuite, sur GitHub :
- retire la deploy key `deploy-boutique-vps` du dépôt `boutique-old` ;
- désactive son workflow de déploiement (*Actions → le workflow → Disable workflow*), puisque plus rien n'est déployé dessus.

> Si tu utilises encore ta clé RSA `q.beck@coprotec.net` ailleurs, considère-la comme exposée (sa partie privée était sur le serveur) et remplace-la.

### Étape 5 — SSH : clé obligatoire, seul `coprotec` autorisé

```bash
sudo bash 05-ssh.sh
```

**Ne ferme pas la session.** Lance les tests affichés par le script depuis un second terminal : `coprotec` doit être accepté ; `ubuntu`, `root` et toute connexion par mot de passe doivent être refusés. Le script indique aussi comment annuler depuis la session ouverte.

### Étape 6 — Désactivation de `ubuntu`

```bash
sudo bash 06-desactiver-ubuntu.sh
```

Le compte est désactivé, pas supprimé :
- mot de passe verrouillé, plus de shell ;
- plus de sudo, y compris les deux lignes `NOPASSWD` de cloud-init (le fichier est vérifié par `visudo` et restauré en cas d'erreur) ;
- plus de clé SSH.

Les sauvegardes sont dans `/root/sauvegarde-ubuntu-*`.

### Étape 7 — fail2ban, Apache, phpMyAdmin

```bash
sudo bash 07-securite.sh
```

- **fail2ban** surveille SSH et l'identifiant de la préprod. Les IP des bureaux ne sont jamais bannies.
- **Apache** n'affiche plus sa version.
- **phpMyAdmin** : le script propose de le désactiver.

### Étape 8 — Docker

```bash
sudo bash 08-docker.sh
```

Ensuite, **déconnecte-toi et reconnecte-toi** : c'est nécessaire pour que le groupe `docker` soit pris en compte.

> Docker contourne UFW pour les ports publiés. La boutique ne publie que sur `127.0.0.1` et ne publie pas MySQL. Il faut garder cette règle.

### Étape 9 — Nouvelle préprod (sans sudo)

```bash
bash ~/vps/09-preprod.sh
```

Le script :
- crée la clé de lecture GitHub et l'affiche. Ajoute-la dans le dépôt `coprotec/boutique` (*Settings → Deploy keys*, lecture seule), puis appuie sur Entrée ;
- clone dans `/srv/preprod.app.coprotec.net` et met `.env.preprod.local` en place (`coprotec:www-data`, `640`, pour que PHP le lise dans le conteneur) ;
- lance `deploy.sh preprod` et vérifie la réponse sur `127.0.0.1:8095`.

### Étape 10 — Vhost Apache et suppression de l'ancienne préprod

```bash
sudo bash ~/vps/10-apache-preprod.sh
```

Le script :
- active `proxy_http` et `headers`, et crée l'**identifiant temporaire** des testeurs ;
- arrête le worker de l'ancienne préprod et désactive ses vhosts ;
- installe `boutique-preprod.conf`, lance `configtest` (et revient à l'ancienne config en cas d'erreur), recharge Apache, puis teste le renouvellement du certificat ;
- **archive puis supprime** l'ancienne préprod, après confirmation.

Sa base MySQL éventuelle, sur le MySQL de l'hôte, reste à supprimer à la main.

Ouvre ensuite `https://preprod.app.coprotec.net` :
- depuis les bureaux, l'accès est direct ;
- ailleurs, l'identifiant est demandé ;
- le bandeau « Version de démonstration » doit s'afficher ;
- fais un parcours complet : réservation, CB simulée, puis virement.

Pour **ajouter un testeur** : `sudo htpasswd /etc/apache2/.htpasswd-boutique <nom>`, **sans `-c`**.

Pour **retirer l'identifiant** à la fin des tests, il ne reste alors que l'accès par IP :
1. dans `/etc/apache2/sites-available/boutique-preprod.conf`, supprime les trois lignes `Auth…` et la ligne `Require valid-user` ;
2. lance :
   ```bash
   sudo apache2ctl configtest && sudo systemctl reload apache2 && sudo rm /etc/apache2/.htpasswd-boutique
   ```
3. pense à reporter cette modification dans `docker/apache-hote/preprod.app.coprotec.net.conf` du dépôt.

### Étape 11 — Déploiement automatique (sans sudo)

```bash
bash ~/vps/11-github-actions.sh
```

**La clé de GitHub ne peut lancer qu'une seule commande.** Le script :
- installe `~/bin/deploy-gha.sh`. Ce script n'accepte que `deploy preprod` ou `deploy prod` ; toute autre demande est refusée et journalisée (pour voir le journal : `journalctl -t deploy-gha`) ;
- génère la clé de GitHub et l'autorise pour `coprotec` avec `restrict,command="…/deploy-gha.sh"`. Quoi qu'envoie GitHub, c'est ce script qui s'exécute, sans terminal ni redirection ;
- affiche les valeurs à enregistrer dans le dépôt `coprotec/boutique` (*Settings → Secrets and variables → Actions*) :
  - `REMOTE_HOST` ;
  - `REMOTE_PORT` = `2268` ;
  - `REMOTE_USER` = `coprotec` ;
  - `REMOTE_KNOWN_HOSTS` : l'empreinte du serveur, pour que GitHub refuse de se connecter à un imposteur ;
  - `SSH_PRIVATE_KEY`.

La clé privée est effacée du serveur à la fin du script.

Dans *Settings → Environments*, crée `preprod` et `prod`. Pour `prod`, active *Required reviewers*.

Dans chaque environnement, ouvre aussi *Deployment branches and tags* : choisis *Selected branches* et autorise uniquement la branche du même nom (`preprod` pour l'environnement `preprod`, `prod` pour `prod`). Ainsi, aucune autre branche ne peut utiliser ces secrets pour déployer.

### Une branche par environnement

| Branche | Effet d'un push | Dossier sur le serveur |
|---|---|---|
| `main` | tests uniquement | — |
| `preprod` | tests puis déploiement de la préprod | `/srv/preprod.app.coprotec.net` (suit `preprod`) |
| `prod` | tests, **ta validation**, puis déploiement de la prod | `/srv/app.coprotec.net` (suit `prod`) |

`deploy.sh` refuse de déployer si le dossier n'est pas sur la branche de son environnement.

Mise en place des branches, une seule fois, sur ton poste :

```powershell
git switch main; git pull
git branch preprod; git push -u origin preprod      # déclenche le 1er déploiement automatique de la préprod
git branch prod;    git push -u origin prod         # le déploiement attend ta validation : la REFUSER tant que la prod n'est pas installée
git switch main
```

Sur le serveur, si la préprod a été clonée sur `main` (étape 9 lancée avant la création des branches) :

```bash
cd /srv/preprod.app.coprotec.net && git fetch origin && git switch preprod && git branch -D main
```

Ensuite, au quotidien :

```powershell
# préprod : amener main dans preprod
git switch preprod; git pull; git merge --ff-only main; git push; git switch main
# prod (après validation en préprod) : amener preprod dans prod
git switch prod; git pull; git merge --ff-only preprod; git push; git switch main
```

Pour relancer un déploiement sans nouveau code : *Actions → CI / déploiement → Run workflow*, en choisissant la branche `preprod` ou `prod`. À la main, depuis le serveur : `cd /srv/preprod.app.coprotec.net && bash deploy.sh preprod`.

---

## Plus tard : prod et bascule depuis `boutique-old`

### Préparer la prod à côté de `boutique-old`, sans l'exposer

```bash
sudo install -d -o coprotec -g coprotec /srv/app.coprotec.net
git clone -b prod git@github.com:coprotec/boutique.git /srv/app.coprotec.net
# .env.prod.local : vrais identifiants SmartOF / Monetico / Brevo, SMARTOF_FAKE=0, MONETICO_SIMULE=0, MONETICO_TEST=0
sudo chown coprotec:www-data /srv/app.coprotec.net/.env.prod.local && sudo chmod 640 /srv/app.coprotec.net/.env.prod.local
cd /srv/app.coprotec.net && bash deploy.sh prod        # écoute sur 127.0.0.1:8096
```

Pour la tester avant la bascule, ouvre un tunnel depuis ton poste (`ssh -L 8096:127.0.0.1:8096 boutique-vps`), puis va sur `http://localhost:8096`.

### Le jour de la bascule (QE-34)

1. **Données** : reprendre l'historique utile de `boutique-old`, si besoin (QE-25).
2. **Monetico** : remplacer l'URL de notification (CGI2) par `https://app.coprotec.net/paiement/monetico/notification`.
3. **Apache** :
   - sauvegarder `app.conf` et `app-le-ssl.conf` ;
   - créer `boutique-prod.conf` sur le modèle de la préprod, avec le port **8096**, **sans** bloc d'authentification ni `X-Robots-Tag`, et le certificat `app.coprotec.net` ;
   - lancer `a2dissite app.conf app-le-ssl.conf`, `a2ensite boutique-prod.conf`, `configtest`, puis `reload`.
4. **Cron de `boutique-old`** (root, `insertion_evenements.php`) : le désactiver.
5. **Retour arrière** : réactiver `app.conf` et `app-le-ssl.conf`, puis recharger Apache.

### Après la bascule (quelques semaines plus tard)

- Archiver `boutique-old` hors du VPS : dump de sa base et archive de `/var/www/app`.
- Arrêter le MySQL, PHP-FPM et phpMyAdmin de l'hôte s'ils ne servent plus.
- Supprimer le compte `ubuntu` : `sudo deluser --remove-home ubuntu`. Vérifie d'abord que `sudo find / -xdev -user ubuntu -not -path '/home/ubuntu/*' 2>/dev/null` ne renvoie rien.
- Mettre en place la **sauvegarde quotidienne** du MySQL de la prod (dump chiffré hors du VPS ; rétention : QE-26) et une **surveillance** de disponibilité.

---

## Vérifications de sécurité

```bash
sudo sshd -T | grep -Ei '^(port|permitrootlogin|passwordauthentication|allowusers)'   # 2268 / no / no / coprotec
sudo passwd -S ubuntu                       # « L » = verrouillé
sudo ufw status verbose                     # 2268, 80, 443
sudo fail2ban-client status                 # sshd, apache-auth
sudo ss -tlnp                               # en 0.0.0.0 : seulement 2268, 80, 443
docker ps --format 'table {{.Names}}\t{{.Ports}}'   # 127.0.0.1:8095 / 8096 uniquement
ls -l /srv/*/.env.*.local                   # -rw-r----- coprotec www-data
free -h
```

Les scripts de diagnostic (`diag-vps.sh`, `diag-apache.sh`, `diag-ubuntu.sh`) peuvent être relancés à tout moment : ils ne modifient rien.
