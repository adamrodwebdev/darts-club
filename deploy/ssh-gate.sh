#!/bin/sh
# Commande imposée à la clé SSH de GitHub Actions (~deploy/.ssh/authorized_keys, ligne écrite par
# deploy/authorize-github-key.sh) :
#   restrict,command="/opt/skull-darts-71/deploy/ssh-gate.sh" ssh-ed25519 AAAA… github-actions-deploy
# Le compte deploy pilote Docker, donc tout le serveur. Avec cette commande, la clé ne peut que déployer
# une version publiée : ni session, ni autre commande, ni copie de fichier, ni tunnel.
#   deploy <tag>   tag = 12 premiers caractères du SHA d'un commit de main, image publiée sur ghcr.io
# Les fichiers de déploiement (compose.yaml, compose.prod.yaml, deploy/) viennent de cette image, construite
# par la CI depuis main : la clé ne peut pas en apporter d'autres.
set -eu

APP_DIR=/opt/skull-darts-71

refuse() {
  echo "commande autorisée : deploy <tag>" >&2
  exit 2
}

image_name() {
  name=$(sed -n 's/^IMAGE=//p' "$APP_DIR/.env" | tail -n 1)
  case "${name:=ghcr.io/adamrodwebdev/darts-club}" in
    *[!a-z0-9./_-]*) echo "IMAGE illisible dans .env" >&2; exit 1 ;;
  esac
  printf '%s\n' "$name"
}

main() {
  # Commande imposée : la demande arrive dans SSH_ORIGINAL_COMMAND ; appel direct : arguments
  if [ -n "${SSH_ORIGINAL_COMMAND:-}" ]; then
    set -f
    # shellcheck disable=SC2086 # découpage en mots voulu, jokers désactivés (set -f)
    set -- $SSH_ORIGINAL_COMMAND
    set +f
  fi
  case "${1:-}" in */ssh-gate.sh | ssh-gate.sh) shift ;; esac

  [ "${1:-}" = deploy ] && [ $# -eq 2 ] || refuse
  tag=$2
  case "$tag" in '' | *[!0-9a-f]*) refuse ;; esac
  [ ${#tag} -eq 12 ] || refuse

  image="$(image_name):$tag"
  docker pull --quiet "$image" > /dev/null
  # Fichiers de déploiement de cette version, tirés de l'image elle-même (ce script compris : il est
  # remplacé pendant qu'il tourne, d'où le tout dans cette fonction, déjà lue, puis exec)
  container=$(docker create "$image")
  copied=0
  docker cp "$container:/app/ops/." "$APP_DIR/" && copied=1
  docker rm "$container" > /dev/null
  [ "$copied" = 1 ] || { echo "image sans fichiers de déploiement (/app/ops)" >&2; exit 1; }
  exec "$APP_DIR/deploy/deploy.sh" "$tag"
}

main "$@"; exit $?
