#!/bin/bash
# deploy.sh — Mise à jour de la boutique (build, migrations, cache, catalogue). Modèle : /coplanif.
#
# Usage : bash deploy.sh <preprod|prod>
# À lancer depuis la racine du checkout : /srv/preprod.app.coprotec.net (branche preprod)
# ou /srv/app.coprotec.net (branche prod).
# Sans interaction (appelable par GitHub Actions via SSH), code de sortie non nul en cas d'échec.
set -euo pipefail

ENV_NAME="${1:-}"
case "$ENV_NAME" in
    preprod|prod) ;;
    *) echo "[ERREUR] Usage : bash deploy.sh <preprod|prod>" >&2; exit 1 ;;
esac

COMPOSE="docker compose -f docker-compose.$ENV_NAME.yml"
ENV_FILE=".env.$ENV_NAME.local"
log() { echo "[$(date '+%H:%M:%S')] $*"; }

[ -f "$ENV_FILE" ] || { echo "[ERREUR] $ENV_FILE manquant (secrets, cf. docker-compose.$ENV_NAME.yml)" >&2; exit 1; }

# Une branche par environnement : le dossier de préprod suit « preprod », celui de prod suit « prod ».
BRANCHE="$(git rev-parse --abbrev-ref HEAD)"
if [ "$BRANCHE" != "$ENV_NAME" ]; then
    echo "[ERREUR] Ce dossier est sur la branche « $BRANCHE », pas « $ENV_NAME »." >&2
    echo "         Corriger : git fetch origin && git switch $ENV_NAME" >&2
    exit 1
fi

log "Mise à jour du code (branche $ENV_NAME)"
git pull --ff-only origin "$ENV_NAME"

log "Construction des images"
$COMPOSE build --pull

log "Démarrage"
$COMPOSE up -d --remove-orphans

log "Attente de MySQL"
for i in $(seq 1 30); do
    $COMPOSE exec -T mysql mysqladmin ping -h 127.0.0.1 --silent >/dev/null 2>&1 && break
    sleep 2
done

log "Migrations"
$COMPOSE exec -T app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

log "Cache"
$COMPOSE exec -T app php bin/console cache:clear --no-warmup
$COMPOSE exec -T app php bin/console cache:warmup
$COMPOSE exec -T app chown -R www-data:www-data var

log "Synchro du catalogue SmartOF"
$COMPOSE exec -T -u www-data app php bin/console app:catalog:sync --no-interaction || log "⚠ synchro en échec (voir var/log), le déploiement continue"

log "Déploiement $ENV_NAME terminé"
