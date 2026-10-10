# Skull Darts 71

Plateforme full-stack du club de fléchettes — Symfony 7.4 LTS (`api/`) + Vue 3 (`web/`).
Voir [ROADMAP.md](ROADMAP.md).

## Démarrage

```bash
docker compose up -d --build        # API https://localhost, Mailpit http://localhost:8025
cd web && npm install && npm run dev   # http://localhost:5173, /api proxifié vers https://localhost (VITE_API_PROXY)
```

## Qualité

| Partie | Commandes |
| --- | --- |
| API | `vendor/bin/phpstan analyse` · `vendor/bin/php-cs-fixer fix` · `vendor/bin/phpunit` |
| Web | `npm run lint` · `npm run type-check` · `npm run test:unit` · `npm run test:e2e` |

La CI (GitHub Actions) bloque toute PR sous **Lighthouse 95** dans les 4 catégories.

## Notifications push (VAPID)

```bash
php api/bin/console app:push:generate-vapid-keys
```

Sous Windows (PHP natif), OpenSSL a besoin de sa configuration pour créer des clés EC :
`$env:OPENSSL_CONF = "C:\php\8.4.6\extras\ssl\openssl.cnf"` avant la commande.
Stocker la clé privée avec `bin/console secrets:set VAPID_PRIVATE_KEY`.

## Démo locale sans Docker

Pour naviguer sur le site avec des données de démonstration (SQLite, emails écrits dans `api/var/mails/`) :

1. `api/.env.dev.local` (non versionné) :
   ```
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/demo.db"
   MESSENGER_TRANSPORT_DSN=sync://
   MESSENGER_FAILED_TRANSPORT_DSN=in-memory://
   MAILER_DSN=null://null
   FRONTEND_URL=http://localhost:5173
   TRUSTED_ORIGINS=http://localhost:5173
   DEFAULT_URI=http://localhost:5173
   ```
2. `web/.env.development.local` : `VITE_API_PROXY=http://127.0.0.1:8000`
3. Lancer :
   ```bash
   php -d extension=gd api/bin/console app:demo:seed
   php -d extension=gd -S 127.0.0.1:8000 -t api/public
   npm --prefix web run dev
   ```
4. Ouvrir http://localhost:5173 — comptes `president@demo.local`, `tresorier@demo.local`, `secretaire@demo.local`, `alice@demo.local`, `bob@demo.local`, `chloe@demo.local`, mot de passe `SkullDarts71-demo!` (données fictives).

Sans hub Mercure, le temps réel est inactif : les notifications apparaissent au rechargement.

## Production

Serveur Docker (FrankenPHP/Caddy, PostgreSQL, Redis, Mercure), image publiée sur `ghcr.io` par GitHub Actions
et déployée automatiquement après chaque CI verte sur `main`. Guide pas à pas : [docs/deploiement.md](docs/deploiement.md).

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d      # sur le serveur, avec /opt/skull-darts-71/.env
./deploy/deploy.sh <sha>                                       # déploiement / retour arrière
./deploy/backup.sh                                             # sauvegarde chiffrée (age)
```
