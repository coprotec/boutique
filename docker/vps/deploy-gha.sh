#!/bin/bash
# Commande forcée de la clé GitHub Actions (installée par 11-github-actions.sh dans ~coprotec/bin/).
# Quelle que soit la commande envoyée par GitHub, sshd exécute CE script ; il n'accepte que :
#     deploy preprod   →  cd /srv/preprod.app.coprotec.net && bash deploy.sh preprod
#     deploy prod      →  cd /srv/app.coprotec.net && bash deploy.sh prod
# Toute autre demande est refusée et journalisée (journalctl -t deploy-gha).
set -euo pipefail

DEMANDE="${SSH_ORIGINAL_COMMAND:-}"
case "$DEMANDE" in
    "deploy preprod") ENV_NAME=preprod; DOSSIER=/srv/preprod.app.coprotec.net ;;
    "deploy prod")    ENV_NAME=prod;    DOSSIER=/srv/app.coprotec.net ;;
    *)
        logger -t deploy-gha "REFUSÉ : « ${DEMANDE:0:200} »" || true
        echo "Commande refusée. Seules « deploy preprod » et « deploy prod » sont autorisées." >&2
        exit 1
        ;;
esac

logger -t deploy-gha "Déploiement $ENV_NAME demandé" || true
cd "$DOSSIER"
exec bash deploy.sh "$ENV_NAME"
