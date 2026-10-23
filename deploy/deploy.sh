#!/bin/sh
# Déploiement d'une version sur le serveur (appelé par GitHub Actions, ou à la main).
# Usage : deploy/deploy.sh [tag]   — tag = SHA de commit publié sur ghcr.io (défaut : IMAGE_TAG du .env)
# Retour arrière : relancer avec le SHA de la version précédente.
set -eu
cd "$(dirname "$0")/.."

# Relais SMTP : TLS exigé, sinon un intermédiaire pourrait retirer STARTTLS et lire la clé.
# Ajouté une seule fois aux .env plus anciens que set-mailer.sh (qui l'écrit déjà), avant leur lecture
if grep -q '^MAILER_DSN=smtp://[^?[:space:]]*$' .env; then
  sed -i 's/^MAILER_DSN=smtp:\/\/[^?[:space:]]*$/&?require_tls=true/' .env
  echo "→ Emails : TLS désormais exigé vers le relais SMTP"
fi

set -a
. ./.env
set +a
if [ -n "${1:-}" ]; then IMAGE_TAG="$1"; export IMAGE_TAG; fi

compose() { docker compose -f compose.yaml -f compose.prod.yaml "$@"; }

echo "→ Images : application ${IMAGE}:${IMAGE_TAG}, et correctifs de la base, de Redis et de Mercure"
compose pull

echo "→ Services de données"
compose up -d --wait database redis mercure

echo "→ Migrations de la base"
compose run --rm --no-deps php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Application et worker"
compose up -d --remove-orphans

echo "→ Vérification"
for attempt in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS --max-time 5 "https://${SERVER_NAME}/api/health" > /dev/null; then
    echo "✓ ${SERVER_NAME} répond (version ${IMAGE_TAG})"
    docker image prune -f > /dev/null
    exit 0
  fi
  sleep 3
done

echo "✗ La sonde /api/health ne répond pas : voir « docker compose logs php »" >&2
exit 1
