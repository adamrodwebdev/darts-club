#!/bin/sh
# Sauvegarde chiffrée : base PostgreSQL + avatars et photos, chiffrée avec age
# (clé publique BACKUP_AGE_RECIPIENT ; la clé privée reste hors du serveur).
# Planification quotidienne : minuteur systemd deploy/systemd/skull-darts-backup.timer (voir docs/deploiement.md § 10)
set -eu
cd "$(dirname "$0")/.."

set -a
. ./.env
set +a
: "${BACKUP_AGE_RECIPIENT:?définir BACKUP_AGE_RECIPIENT dans .env}"
target_dir="${BACKUP_DIR:-/var/backups/skull-darts-71}"
retention="${BACKUP_RETENTION_DAYS:-30}"

compose() { docker compose -f compose.yaml -f compose.prod.yaml "$@"; }

umask 077
mkdir -p "$target_dir"
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

compose exec -T database pg_dump --username="${POSTGRES_USER:-app}" --dbname="${POSTGRES_DB:-app}" --format=custom > "$work/database.dump"
compose exec -T php tar -C /app/var -cf - storage > "$work/storage.tar"

archive="$target_dir/skull-darts-71-$(date +%Y%m%d-%H%M%S).tar.gz.age"
tar -C "$work" -czf - database.dump storage.tar | age --recipient "$BACKUP_AGE_RECIPIENT" > "$archive"
echo "✓ $archive ($(du -h "$archive" | cut -f1))"

# Copie hors du serveur (facultatif) : remote rclone, ex. « storagebox:skull-darts-71 »
if [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
  rclone copy "$archive" "$BACKUP_RCLONE_REMOTE"
  echo "✓ copie envoyée vers $BACKUP_RCLONE_REMOTE"
fi

find "$target_dir" -name 'skull-darts-71-*.tar.gz.age' -mtime +"$retention" -delete
