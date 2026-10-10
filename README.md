# Darts Club

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
