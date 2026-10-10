#!/bin/sh
# Restauration d'une sauvegarde (remplace la base et les fichiers actuels !).
# Usage : deploy/restore.sh <archive.tar.gz.age> <clé-privée-age.txt>
set -eu
cd "$(dirname "$0")/.."
archive="${1:?archive .tar.gz.age attendue}"
identity="${2:?fichier de clé privée age attendu}"

set -a
. ./.env
set +a

compose() { docker compose -f compose.yaml -f compose.prod.yaml "$@"; }

printf 'Remplacer les données de %s par %s ? Taper OUI : ' "$SERVER_NAME" "$archive"
read -r answer
[ "$answer" = "OUI" ] || { echo "Abandon."; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
age --decrypt --identity "$identity" "$archive" | tar -C "$work" -xzf -

compose stop php worker
compose exec -T database pg_restore --clean --if-exists --no-owner --username="${POSTGRES_USER:-app}" --dbname="${POSTGRES_DB:-app}" < "$work/database.dump"
compose run --rm --no-deps -T php sh -c 'rm -rf /app/var/storage/* && tar -C /app/var -xf -' < "$work/storage.tar"
compose up -d
echo "✓ Données restaurées depuis $archive"
