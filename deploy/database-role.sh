#!/bin/sh
# Compte de la base du site et du worker (POSTGRES_APP_USER, « app_runtime » par défaut) : lecture et écriture
# des lignes des tables du club, rien d'autre. Ni superutilisateur, ni création ou modification de table, ni
# commande du système (COPY … PROGRAM), ni table des migrations. Migrations, sauvegardes et restaurations passent
# par le propriétaire des tables (POSTGRES_USER), dont le mot de passe n'entre jamais dans les conteneurs du site.
# Créé ou remis à jour par deploy.sh après les migrations et par restore.sh ; sans danger à relancer.
set -eu
cd "$(dirname "$0")/.."
if [ -f .env ]; then
  set -a
  . ./.env
  set +a
fi

owner="${POSTGRES_USER:-app}"
role="${POSTGRES_APP_USER:-app_runtime}"
database="${POSTGRES_DB:-app}"
password="${POSTGRES_APP_PASSWORD:?définir POSTGRES_APP_PASSWORD (créé par deploy.sh)}"
# Valeurs recopiées dans le script SQL : lettres, chiffres et _ seulement
for value in "$owner" "$role" "$database" "$password"; do
  case "$value" in '' | *[!A-Za-z0-9_]*) echo "nom ou mot de passe de la base illisible (lettres, chiffres et _ seulement)" >&2; exit 2 ;; esac
done

# Le mot de passe passe par l'entrée de psql, jamais par la liste des processus. Connexion locale du
# conteneur de la base, en propriétaire
{
  printf '\\set owner %s\n\\set role %s\n\\set database %s\n\\set password %s\n' "$owner" "$role" "$database" "$password"
  cat <<'SQL'
SELECT format('CREATE ROLE %I', :'role') WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'role') \gexec
ALTER ROLE :"role" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD :'password';
REVOKE ALL ON DATABASE :"database" FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE :"database" TO :"role";
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO :"role";
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"role";
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO :"role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO :"role";
SELECT format('REVOKE ALL ON public.doctrine_migration_versions FROM %I', :'role')
  WHERE to_regclass('public.doctrine_migration_versions') IS NOT NULL \gexec
SQL
} | docker compose -f compose.yaml -f compose.prod.yaml exec -T database \
  psql --no-psqlrc --quiet --set ON_ERROR_STOP=1 --username="$owner" --dbname="$database"
echo "→ Base : compte « $role » limité aux lignes des tables du club"
