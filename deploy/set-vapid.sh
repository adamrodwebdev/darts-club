#!/bin/sh
# Génère les clés VAPID de production (notifications push) sur le serveur, les
# enregistre dans .env sans les afficher, puis redéploie.
# Usage : ssh deploy@<serveur> /opt/skull-darts-71/deploy/set-vapid.sh [--force]
#   --force : remplace des clés existantes. À éviter : chaque appareil abonné aux
#             notifications devra se réabonner (Mon compte → Notifications).
set -eu
cd "$(dirname "$0")/.."

if grep -q '^VAPID_PRIVATE_KEY=.' .env && [ "${1:-}" != "--force" ]; then
  echo "Des clés VAPID existent déjà : rien n'a été modifié (--force pour les remplacer)."
  exit 0
fi

compose="docker compose -f compose.yaml -f compose.prod.yaml"
out=$($compose exec -T php php bin/console app:push:generate-vapid-keys --no-ansi)
public=$(printf '%s\n' "$out" | sed -n 's/^VAPID_PUBLIC_KEY=//p' | tr -d '\r ')
private=$(printf '%s\n' "$out" | sed -n 's/^VAPID_PRIVATE_KEY=//p' | tr -d '\r ')
unset out

# Clés en base64url : tout autre contenu signale une sortie inattendue
case "$public" in '' | *[!A-Za-z0-9_-]*) echo "Clé publique illisible : rien n'a été modifié." >&2; exit 1 ;; esac
case "$private" in '' | *[!A-Za-z0-9_-]*) echo "Clé privée illisible : rien n'a été modifié." >&2; exit 1 ;; esac

umask 077
cp .env ".env.bak-$(date -u +%Y%m%dT%H%M%SZ)"
tmp=$(mktemp)
grep -v -e '^VAPID_PUBLIC_KEY=' -e '^VAPID_PRIVATE_KEY=' .env > "$tmp"
printf 'VAPID_PUBLIC_KEY=%s\nVAPID_PRIVATE_KEY=%s\n' "$public" "$private" >> "$tmp"
cat "$tmp" > .env
rm -f "$tmp"
unset public private
echo "✓ Clés VAPID enregistrées (copie de l'ancien .env : .env.bak-…, à supprimer une fois vérifié)"

# Même version qu'en service, relancée avec les nouvelles clés
running=$(docker inspect --format '{{.Config.Image}}' "$($compose ps -q php)")
./deploy/deploy.sh "${running##*:}"
