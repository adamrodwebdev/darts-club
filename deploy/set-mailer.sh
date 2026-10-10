#!/bin/sh
# Enregistre la clé SMTP Brevo dans .env sans l'afficher, après l'avoir testée auprès
# de Brevo, puis redéploie. Rien n'est modifié si la clé est refusée.
# Usage (depuis n'importe quel terminal) : ssh -t deploy@<serveur> /opt/skull-darts-71/deploy/set-mailer.sh [--visible]
#   --visible : affiche la clé pendant la saisie (sinon saisie invisible et aperçu masqué)
set -eu
cd "$(dirname "$0")/.."

login="${BREVO_SMTP_LOGIN:-bd8ffb001@smtp-brevo.com}"

if [ "${1:-}" = "--visible" ]; then
  printf 'Clé SMTP Brevo (affichée) : '
  IFS= read -r raw
else
  printf 'Clé SMTP Brevo, collée une seule fois (la saisie reste invisible) : '
  stty -echo
  IFS= read -r raw
  stty echo
  printf '\n'
fi

# Nettoyage du collage (espaces, retour chariot, marqueurs de collage, clé collée deux fois),
# test d'authentification SMTP, puis DSN encodé pour l'URL : la clé n'apparaît jamais à l'écran.
dsn=$(KEY="$raw" LOGIN="$login" python3 - <<'PY'
import os, re, smtplib, ssl, sys
from urllib.parse import quote

key = re.sub(r'\x1b\[20[01]~', '', os.environ['KEY']).strip()
if not key:
    sys.exit('Clé vide : rien n\'a été modifié.')
if any(ord(c) < 32 for c in key):
    sys.exit('Caractères de contrôle dans la clé : recopie-la depuis Brevo.')
def preview(value):
    """Aperçu masqué : début et fin seulement, pour vérifier le collage sans exposer la clé."""
    return f'{value[:9]}…{value[-4:]} ({len(value)} caractères)' if len(value) > 16 else f'({len(value)} caractères)'

print(f'Reçu : {preview(key)}', file=sys.stderr)
half = len(key) // 2
if len(key) % 2 == 0 and key[:half] == key[half:]:
    key = key[:half]
    print(f'Clé collée deux fois : une seule copie est conservée → {preview(key)}', file=sys.stderr)

login = os.environ['LOGIN']
try:
    with smtplib.SMTP('smtp-relay.brevo.com', 587, timeout=15) as smtp:
        smtp.starttls(context=ssl.create_default_context())
        smtp.login(login, key)
except smtplib.SMTPAuthenticationError:
    sys.exit(f'Brevo refuse cette clé ({len(key)} caractères) : rien n\'a été modifié. '
             'Génère une nouvelle clé SMTP et colle-la une seule fois.')
print('✓ Clé acceptée par Brevo', file=sys.stderr)
print(f"smtp://{quote(login, safe='')}:{quote(key, safe='')}@smtp-relay.brevo.com:587")
PY
)

umask 077
tmp=$(mktemp)
grep -v '^MAILER_DSN=' .env > "$tmp"
printf 'MAILER_DSN=%s\n' "$dsn" >> "$tmp"
cat "$tmp" > .env
rm -f "$tmp"
echo "✓ Clé enregistrée"

./deploy/deploy.sh
