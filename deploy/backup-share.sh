#!/bin/sh
# Accès en lecture seule aux sauvegardes chiffrées, pour la copie vers l'ordinateur du bureau.
# Commande imposée par la clé SSH dédiée (~deploy/.ssh/authorized_keys) :
#   restrict,command="/opt/skull-darts-71/deploy/backup-share.sh" ssh-ed25519 AAAA… skull-darts-71-backup-pull
# Cette clé ne peut ni ouvrir de session, ni lancer une autre commande : seulement
#   list        → noms des sauvegardes disponibles
#   get <nom>   → contenu chiffré d'une sauvegarde
set -eu

dir="${BACKUP_DIR:-/var/backups/skull-darts-71}"
pattern='^skull-darts-71-[0-9]{8}-[0-9]{6}\.tar\.gz\.age$'

set -- ${SSH_ORIGINAL_COMMAND:-}
case "${1:-}" in
  list)
    [ $# -eq 1 ] || { echo "usage : list" >&2; exit 2; }
    ls -1 "$dir" | grep -E "$pattern" | sort
    ;;
  get)
    [ $# -eq 2 ] || { echo "usage : get <nom>" >&2; exit 2; }
    # Nom strictement contrôlé : aucun chemin, aucun autre fichier
    printf '%s\n' "$2" | grep -Eq "$pattern" || { echo "nom refusé" >&2; exit 2; }
    [ -f "$dir/$2" ] || { echo "sauvegarde introuvable" >&2; exit 1; }
    cat "$dir/$2"
    ;;
  *)
    echo "commandes autorisées : list, get <nom>" >&2
    exit 2
    ;;
esac
