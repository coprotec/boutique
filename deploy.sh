#!/bin/bash
# deploy.sh — Mise à jour de la boutique (build, migrations, cache, catalogue). Modèle : /coplanif.
#
# Usage : bash deploy.sh <preprod|prod>
# À lancer depuis la racine du checkout : /srv/preprod.app.coprotec.net (branche preprod)
# ou /srv/app.coprotec.net (branche main).
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

# Branche suivie : la préprod suit « preprod », la prod suit « main ».
ATTENDUE="$([ "$ENV_NAME" = prod ] && echo main || echo preprod)"
BRANCHE="$(git rev-parse --abbrev-ref HEAD)"
if [ "$BRANCHE" != "$ATTENDUE" ]; then
    echo "[ERREUR] Ce dossier est sur la branche « $BRANCHE », la $ENV_NAME doit suivre « $ATTENDUE »." >&2
    echo "         Corriger : git fetch origin && git switch $ATTENDUE" >&2
    exit 1
fi

log "Mise à jour du code (branche $ATTENDUE)"
git pull --ff-only origin "$ATTENDUE"

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
