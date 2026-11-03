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

# Mots de passe internes tirés ici, sur le serveur, la première fois (jamais affichés ni copiés ailleurs)
new_password() {
  grep -q "^$1=." .env && return 0
  umask 077
  [ -z "$(tail -c 1 .env)" ] || echo >> .env
  printf '%s=%s\n' "$1" "$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')" >> .env
  echo "→ $2 : mot de passe créé dans .env"
}
new_password REDIS_PASSWORD Redis
# Compte limité du site et du worker dans la base (deploy/database-role.sh)
new_password POSTGRES_APP_PASSWORD "Base, compte du site"

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
# Par le propriétaire des tables, le temps de la commande : le site et le worker n'ont que le compte limité.
# URL passée par l'environnement (-e sans valeur), jamais dans la liste des processus
(
  export DATABASE_URL="postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-app}?serverVersion=17&charset=utf8"
  compose run --rm --no-deps -e DATABASE_URL php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
)
deploy/database-role.sh

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
