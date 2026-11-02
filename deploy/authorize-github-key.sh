#!/bin/sh
# Clés SSH du compte deploy : autorise la clé de GitHub Actions pour le déploiement seulement
# (commande imposée deploy/ssh-gate.sh), liste les clés, ou en retire une. À lancer depuis l'ordinateur
# de l'administrateur (PowerShell), la clé privée restant sur cet ordinateur puis dans GitHub :
#   Get-Content <clé>.pub | ssh deploy@<serveur> /opt/skull-darts-71/deploy/authorize-github-key.sh
#   ssh deploy@<serveur> /opt/skull-darts-71/deploy/authorize-github-key.sh --list
#   ssh deploy@<serveur> /opt/skull-darts-71/deploy/authorize-github-key.sh --remove SHA256:…
# Une copie datée de authorized_keys est gardée avant chaque modification. Retirer la dernière clé
# sans restriction est refusé : l'administrateur ne peut pas s'enfermer dehors.
set -eu

keys="${AUTHORIZED_KEYS:-$HOME/.ssh/authorized_keys}"
gate=/opt/skull-darts-71/deploy/ssh-gate.sh
restriction="restrict,command=\"$gate\""

fingerprint() {
  printf '%s\n' "$1" | ssh-keygen -lf - 2> /dev/null | awk '{print $2}'
}

# Rôle d'une ligne : commande imposée lue dans ses options
role() {
  case "$1" in
    *"command=\"$gate\""*) echo "déploiement seulement" ;;
    *backup-share.sh*) echo "sauvegardes seulement" ;;
    *command=*) echo "commande imposée" ;;
    *) echo "accès complet" ;;
  esac
}

backup() {
  cp -p "$keys" "$keys.bak-$(date -u +%Y%m%dT%H%M%SZ)"
}

# Réécrit authorized_keys sans changer ses droits ni son propriétaire
replace_with() {
  cat "$1" > "$keys"
  rm -f "$1"
}

list() {
  n=0
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in '' | '#'*) continue ;; esac
    n=$((n + 1))
    fp=$(fingerprint "$line")
    comment=$(printf '%s\n' "$line" | awk '{print $NF}')
    printf '%2d  %s  %s  [%s]\n' "$n" "${fp:-illisible}" "$comment" "$(role "$line")"
  done < "$keys"
}

unrestricted_count() {
  count=0
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in '' | '#'*) continue ;; esac
    [ "$(role "$line")" = "accès complet" ] && count=$((count + 1))
  done < "$keys"
  echo "$count"
}

remove() {
  target=$1
  case "$target" in SHA256:?*) ;; *) echo "empreinte attendue : SHA256:…" >&2; exit 2 ;; esac
  umask 077
  tmp=$(mktemp)
  found=0
  while IFS= read -r line || [ -n "$line" ]; do
    if [ "$found" = 0 ] && [ "$(fingerprint "$line")" = "$target" ]; then
      found=1
      if [ "$(role "$line")" = "accès complet" ] && [ "$(unrestricted_count)" -le 1 ]; then
        rm -f "$tmp"
        echo "refusé : c'est la dernière clé à accès complet (vous ne pourriez plus vous connecter)" >&2
        exit 1
      fi
      continue
    fi
    printf '%s\n' "$line" >> "$tmp"
  done < "$keys"
  if [ "$found" = 0 ]; then
    rm -f "$tmp"
    echo "aucune clé n'a l'empreinte $target" >&2
    exit 1
  fi
  backup
  replace_with "$tmp"
  echo "✓ clé $target retirée"
}

authorize() {
  IFS= read -r key || [ -n "$key" ] || { echo "clé publique attendue sur l'entrée standard" >&2; exit 2; }
  key=$(printf '%s' "$key" | tr -d '\r')
  # Une clé publique nue (type, données, commentaire), sans options : les options sont les nôtres
  printf '%s\n' "$key" | grep -Eq '^(ssh-ed25519|ecdsa-sha2-nistp(256|384|521)|ssh-rsa) [A-Za-z0-9+/]+=*( [^"]*)?$' \
    || { echo "clé publique illisible (attendu : ssh-ed25519 AAAA… commentaire)" >&2; exit 2; }
  fp=$(fingerprint "$key")
  [ -n "$fp" ] || { echo "clé publique invalide" >&2; exit 2; }

  umask 077
  tmp=$(mktemp)
  # La même clé déjà présente (avec ou sans restriction) est remplacée par la ligne restreinte
  while IFS= read -r line || [ -n "$line" ]; do
    [ -n "$line" ] && [ "$(fingerprint "$line")" = "$fp" ] && continue
    printf '%s\n' "$line" >> "$tmp"
  done < "$keys"
  printf '%s %s\n' "$restriction" "$key" >> "$tmp"
  backup
  replace_with "$tmp"
  echo "✓ clé $fp autorisée pour le déploiement seulement"
  echo
  list
}

[ -f "$keys" ] || { echo "$keys introuvable" >&2; exit 1; }
case "${1:-}" in
  --list) list ;;
  --remove) [ $# -eq 2 ] || { echo "usage : --remove SHA256:…" >&2; exit 2; }; remove "$2" ;;
  '') authorize ;;
  *) echo "usage : (clé publique sur l'entrée standard) | --list | --remove SHA256:…" >&2; exit 2 ;;
esac
