# Mise en production — Skull Darts 71

Ce guide installe le site sur un petit serveur (VPS) avec Docker. Une fois en place,
chaque fusion sur `main` avec une CI verte déploie automatiquement la nouvelle version.

> Toutes les commandes `docker compose …` se lancent sur le serveur, dans `/opt/skull-darts-71`.
> Raccourci utilisé ci-dessous : `alias dc='docker compose -f compose.yaml -f compose.prod.yaml'`

## 1. Ce qu'il faut

| Élément | Recommandation | Coût |
|---|---|---|
| Serveur | Hetzner CX22 (2 vCPU, 4 Go, Allemagne/Finlande) ou OVH VPS-1 (France), Debian 12 | ~4 à 6 €/mois |
| Domaine | `skulldarts71.fr` (à réserver chez OVH, Gandi… si ce n’est pas déjà fait) | ~7 €/an |
| Emails | Compte Brevo gratuit (300 emails/jour) | 0 € |
| Sonde | GitHub Actions (`uptime.yml`, toutes les 15 min ; UptimeRobot en option) | 0 € |

## 2. Domaine (DNS)

Chez le registraire, créer :

| Type | Nom | Valeur |
|---|---|---|
| A | `@` | adresse IPv4 du serveur |
| AAAA | `@` | adresse IPv6 du serveur |
| A | `www` | adresse IPv4 du serveur (redirigé vers le domaine principal) |

Le certificat HTTPS est obtenu automatiquement par Caddy au premier démarrage (ports 80 et 443 ouverts).

## 3. Préparer le serveur (une fois)

Installer Debian 12 avec la clé SSH publique de l'administrateur (option proposée par l'hébergeur à
l'installation), copier [`deploy/server-setup.sh`](../deploy/server-setup.sh) sur le serveur puis :

```bash
sudo sh server-setup.sh "ssh-ed25519 AAAA… clé publique de déploiement GitHub Actions"
```

Le script installe les mises à jour (et les rend automatiques), Docker, le pare-feu (SSH, HTTP, HTTPS),
fail2ban, crée le compte `deploy` (clés SSH uniquement) et interdit les mots de passe et la connexion root en SSH.

## 4. Secrets du serveur (`.env`)

Prérequis : l'image a été publiée une première fois (§ 6, workflow « Déploiement » lancé à la main).
Copier sur le serveur `compose.yaml`, `compose.prod.yaml` et le dossier `deploy/` du dépôt
(`scp -r compose.yaml compose.prod.yaml deploy deploy@SERVEUR:/opt/skull-darts-71/`),
puis le modèle [`deploy/.env.example`](../deploy/.env.example) vers `/opt/skull-darts-71/.env` et `chmod 600 .env`.
Les valeurs se génèrent sur le serveur (rien ne transite ailleurs) :

```bash
cd /opt/skull-darts-71
img=ghcr.io/adamrodwebdev/darts-club:latest
docker run --rm $img php -r 'echo "APP_SECRET=", bin2hex(random_bytes(32)), PHP_EOL;'
docker run --rm $img php -r 'echo "TWO_FACTOR_ENCRYPTION_KEY=", base64_encode(random_bytes(32)), PHP_EOL;'
docker run --rm $img php -r 'echo "MERCURE_JWT_SECRET=", bin2hex(random_bytes(32)), PHP_EOL;'
docker run --rm $img php -r 'echo "POSTGRES_PASSWORD=", bin2hex(random_bytes(24)), PHP_EOL;'
```

- Clés des notifications push (VAPID) : une fois l'application en service, une seule commande
  depuis l'ordinateur du bureau les génère sur le serveur, les enregistre sans les afficher et redéploie :
  `ssh deploy@SERVEUR /opt/skull-darts-71/deploy/set-vapid.sh`
  (sans effet si des clés existent déjà ; `--force` les remplace, mais chaque appareil devra se réabonner).

- Ces clés de **production** sont distinctes de celles de développement (`api/.env.local`).
- `TWO_FACTOR_ENCRYPTION_KEY` chiffre les secrets de double authentification : **ne jamais la changer**
  (sinon chaque membre du bureau doit reconfigurer sa 2FA). La garder aussi dans le gestionnaire de mots de passe du bureau.
- Ne jamais coller ces valeurs dans un ticket, un message ou un commit.

## 5. Emails avec Brevo

1. Créer le compte Brevo de l'association, puis **Expéditeurs, domaines → Domaines → Ajouter** `skulldarts71.fr`.
2. Ajouter chez le registraire les enregistrements DNS proposés (code Brevo, **DKIM**, **DMARC**) ; attendre la validation (« Authentifié »).
3. **SMTP et API → Clés SMTP → Générer** une clé (elle n'est affichée qu'une fois).
4. Sur un terminal de l'ordinateur du bureau :
   ```bash
   ssh -t deploy@SERVEUR /opt/skull-darts-71/deploy/set-mailer.sh --visible
   ```
   Le script nettoie le collage (clé collée deux fois, espaces), **teste la clé auprès de Brevo**,
   ne modifie rien si elle est refusée, sinon l'enregistre dans `.env` et redéploie.
   Sans `--visible`, la saisie est invisible et un aperçu masqué permet de vérifier le collage.
   Effacer ensuite l'écran du terminal (`cls` / `clear`).
   `MAILER_FROM=no-reply@skulldarts71.fr` (expéditeur à déclarer et vérifier dans Brevo).
5. **Sécurité › IP autorisées** : autoriser l'IPv4 et l'IPv6 du serveur, puis activer le blocage pour les clés SMTP.
6. Vérifier la délivrabilité après le premier déploiement avec [mail-tester.com](https://www.mail-tester.com) (viser ≥ 9/10).

## 6. GitHub : publication de l'image et déploiement automatique

Dans **Settings › Secrets and variables › Actions** du dépôt :

| Type | Nom | Valeur |
|---|---|---|
| Variable | `SERVER_NAME` | `skulldarts71.fr` |
| Variable | `DEPLOY_HOST` | adresse IP ou nom du serveur |
| Variable | `DEPLOY_USER` | `deploy` |
| Secret | `DEPLOY_SSH_KEY` | clé privée SSH **dédiée** (générée par `ssh-keygen -t ed25519 -f deploy_key`), sa clé publique ajoutée à `/home/deploy/.ssh/authorized_keys` |
| Secret | `DEPLOY_KNOWN_HOSTS` | résultat de `ssh-keyscan -t ed25519 ADRESSE_DU_SERVEUR` |

Puis **Settings › Environments › New environment** `production` : cocher *Required reviewers* (le président)
pour valider chaque mise en production, et limiter l'environnement à la branche `main`.

Tant que `DEPLOY_HOST` est vide, le workflow « Déploiement » publie seulement l'image sur `ghcr.io`.
Le lancer une première fois à la main (**Actions › Déploiement › Run workflow**), puis dans
**Packages › darts-club › Package settings**, passer la visibilité en **Public** : le serveur peut alors
télécharger l'image sans identifiant (elle ne contient aucun secret, ce que la CI vérifie à chaque build).

## 7. Premier déploiement

1. Fusionner les PR dans `main` : la CI passe, puis « Déploiement » publie l'image (et déploie si le serveur est configuré).
2. Ou à la main, sur le serveur (fichiers copiés depuis le dépôt : `compose.yaml`, `compose.prod.yaml`, `deploy/`) :
   ```bash
   cd /opt/skull-darts-71 && ./deploy/deploy.sh latest
   ```
3. Ouvrir `https://skulldarts71.fr` : le cadenas HTTPS doit apparaître.

## 8. Nommer le premier président

1. S'inscrire sur le site avec l'adresse du président et cliquer sur le lien reçu par email.
2. Sur le serveur : `dc exec php php bin/console app:member:grant-role president@exemple.fr president`
3. À la connexion suivante, le site impose la double authentification. Le président nomme ensuite
   le trésorier et le secrétaire depuis **Présidence › Membres**.

## 9. Connexion Google / Discord (facultatif)

Les boutons n'apparaissent que si les identifiants sont renseignés.

- Google Cloud Console › Identifiants › ID client OAuth (application Web) :
  URI de redirection `https://skulldarts71.fr/api/auth/oauth/google/check`
- Discord Developer Portal › OAuth2 : redirection `https://skulldarts71.fr/api/auth/oauth/discord/check`

Renseigner `OAUTH_*` dans `.env`, puis `./deploy/deploy.sh`.

## 10. Sauvegardes chiffrées

1. Sur l'ordinateur du bureau (pas sur le serveur) : `age-keygen -o skull-darts-71-backup.key`.
   Conserver ce fichier en lieu sûr (deux copies) : sans lui, les sauvegardes sont illisibles.
2. Copier la ligne `public key: age1…` dans `.env` : `BACKUP_AGE_RECIPIENT=age1…`
3. Planifier la sauvegarde quotidienne (`crontab -e` en tant que `deploy`) :
   ```
   30 3 * * * /opt/skull-darts-71/deploy/backup.sh >> /var/backups/skull-darts-71/backup.log 2>&1
   ```
4. Copie hors du serveur (recommandé) : installer `rclone`, configurer un stockage (Hetzner Storage Box, OVH Object Storage…)
   et renseigner `BACKUP_RCLONE_REMOTE`.
5. **Tester une restauration** une fois par trimestre : `./deploy/restore.sh archive.tar.gz.age skull-darts-71-backup.key`.

La sauvegarde contient la base (comptes, cotisations, messages, événements) et les avatars/photos.

## 11. Disponibilité

Le workflow [`uptime.yml`](../.github/workflows/uptime.yml) sonde le site toutes les 15 minutes :
`/api/health` (répond `{"status":"ok"}` si l'application et la base fonctionnent, 3 essais), la page d'accueil,
et la validité du certificat HTTPS (alerte à moins de 14 jours). En cas d'échec, GitHub envoie un email
(Settings → Notifications → Actions du compte GitHub). Lancement manuel : onglet Actions → « Disponibilité » → Run workflow.

- GitHub désactive les tâches planifiées d'un dépôt public après 60 jours sans activité : un email prévient,
  il suffit de cliquer « Enable workflow ».
- En complément (alerte plus rapide, SMS), un moniteur [UptimeRobot](https://uptimerobot.com) gratuit sur la même
  URL, intervalle 5 min.

Journaux : `dc logs -f php worker` (rotation automatique, 50 Mo max par service).

## 12. Mises à jour et retour arrière

- Mise à jour : fusionner sur `main` ; le déploiement applique les migrations puis vérifie `/api/health`.
- Retour arrière : relancer avec l'étiquette (SHA court) de la version précédente, visible dans GitHub › Packages :
  `./deploy/deploy.sh 1a2b3c4d5e6f`. Les migrations de base ne sont pas annulées automatiquement.
- Mises à jour système : automatiques (unattended-upgrades) ; redémarrer le serveur quand le noyau est mis à jour.

## 13. Vérifications après la mise en ligne

- [ ] [SSL Labs](https://www.ssllabs.com/ssltest/) : note A ou A+
- [ ] [securityheaders.com](https://securityheaders.com) : note A
- [ ] Workflow « Audit de sécurité (OWASP ZAP) » lancé à la main : vert
- [ ] Lighthouse mobile ≥ 95 sur l'accueil
- [ ] Inscription → email reçu (mail-tester ≥ 9/10) → connexion
- [ ] Notifications push : activées depuis Mon compte › Notifications, reçues sur un téléphone
- [ ] Mentions légales et confidentialité complétées par le bureau (hébergeur : nom, adresse, téléphone)
- [ ] Première sauvegarde présente dans `/var/backups/skull-darts-71`, restauration testée
