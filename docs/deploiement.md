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
| A | `www` | adresse IPv4 du serveur (redirigé vers le domaine principal) |

Le certificat HTTPS est obtenu automatiquement par Caddy au premier démarrage (ports 80 et 443 ouverts).

**Pas d'enregistrement AAAA (IPv6) tant que Docker n'a pas IPv6.** Par défaut, Docker relaie les connexions
IPv6 par un proxy : le site verrait alors tous les visiteurs IPv6 (la plupart des téléphones en 4G/5G) sous une
seule adresse interne. Ils partageraient les limites d'essais (5 connexions manquées bloqueraient tout le monde)
et le journal noterait une fausse adresse. Les visiteurs IPv6 passent très bien par l'IPv4 en attendant. Pour
ajouter l'AAAA plus tard : activer IPv6 dans Docker (`"ipv6": true` et un préfixe `fixed-cidr-v6` dans
`/etc/docker/daemon.json`, puis `enable_ipv6: true` sur le réseau `default` de `compose.prod.yaml`), vérifier
dans le journal d'administration qu'une connexion en 4G montre bien une adresse IPv6, puis créer l'AAAA.

## 3. Préparer le serveur (une fois)

Installer Debian 12 avec la clé SSH publique de l'administrateur (option proposée par l'hébergeur à
l'installation), copier le dossier [`deploy/`](../deploy) sur le serveur puis :

```bash
sudo sh deploy/server-setup.sh "ssh-ed25519 AAAA… clé publique de déploiement GitHub Actions"
```

Le script installe les mises à jour (et les rend automatiques, moteur Docker compris, avec un redémarrage
la nuit quand le noyau l'exige), Docker, le pare-feu (SSH, HTTP, HTTPS), fail2ban, crée le compte `deploy`
(clés SSH uniquement), interdit les mots de passe et la connexion root en SSH, et n'autorise la clé de
GitHub Actions qu'à déployer (voir § 6). Relancé sur un serveur existant, il applique les réglages ajoutés depuis.
Relance depuis l'ordinateur de l'administrateur, avec le compte qui a `sudo` (`debian` chez OVH) :

```powershell
ssh -t debian@ADRESSE_DU_SERVEUR sudo sh /opt/skull-darts-71/deploy/server-setup.sh
```

Elle installe d'abord les mises à jour en attente : si Docker en fait partie, les conteneurs redémarrent (une
trentaine de secondes de coupure), puis repartent seuls. Les clés de `deploy` ne sont copiées depuis celles de
`root` et `debian` qu'à la première préparation, sans les lignes à commande imposée : une clé retirée ensuite ne
revient pas à la relance.

**Changer la clé de l'administrateur.** Elle ouvre deux comptes, `deploy` (Docker) et `debian` (sudo) : une
ancienne clé oubliée sur `debian` garde l'accès root. Ajouter la nouvelle aux deux avec l'ancienne, puis, connecté
avec la nouvelle (preuve qu'elle ouvre les deux comptes), retirer l'ancienne des deux ; son empreinte :
`ssh-keygen -lf ANCIENNE_CLÉ.pub`.

```powershell
foreach ($account in 'deploy', 'debian') { Get-Content "$env:USERPROFILE\.ssh\id_ed25519.pub" | ssh -i ANCIENNE_CLÉ "$account@ADRESSE_DU_SERVEUR" "(echo; tr -d '\r') >> ~/.ssh/authorized_keys" }
ssh deploy@ADRESSE_DU_SERVEUR /opt/skull-darts-71/deploy/authorize-github-key.sh --remove SHA256:EMPREINTE_ANCIENNE
ssh -t debian@ADRESSE_DU_SERVEUR "sudo AUTHORIZED_KEYS=/home/debian/.ssh/authorized_keys sh /opt/skull-darts-71/deploy/authorize-github-key.sh --remove SHA256:EMPREINTE_ANCIENNE"
```

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

- `REDIS_PASSWORD` : créé par `deploy.sh` au premier déploiement qui en a besoin (64 caractères hexadécimaux
  tirés sur le serveur, jamais affichés). Redis garde les sessions et la file des messages, que l'API relit
  par `unserialize()` : pouvoir y écrire suffirait à exécuter du code, d'où le mot de passe même sur un réseau interne.
- Plafonds de mémoire (facultatif, selon la taille du serveur) : `WEB_MEMORY_LIMIT` (site, `2g` par défaut) et
  `WORKER_MEMORY_LIMIT` (worker, conversions ffmpeg comprises, `1536m` par défaut). Le conteneur qui les
  dépasse redémarre seul ; PostgreSQL, lui, n'est jamais tué faute de mémoire par un autre.
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
   Le DSN enregistré exige TLS (`require_tls=true`) : aucun email ne part en clair.
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

**Settings › Environments › New environment** `production`, puis :

1. *Deployment branches and tags* : **Selected branches**, règle `main` (aucune autre branche ne peut déployer).
2. *Environment secrets* (et non secrets du dépôt) : la clé de déploiement donne la main sur Docker, donc sur
   tout le serveur ; ainsi seule une exécution sur `main` dans cet environnement peut la lire.

| Type | Nom | Valeur |
|---|---|---|
| Secret d'environnement | `DEPLOY_SSH_KEY` | clé privée SSH **dédiée** (voir ci-dessous), autorisée sur le serveur pour le déploiement seulement |
| Secret d'environnement | `DEPLOY_KNOWN_HOSTS` | clés d'hôte du serveur, tirées du `known_hosts` de l'administrateur (voir ci-dessous) |

**Clé de déploiement, limitée au déploiement.** Le compte `deploy` pilote Docker, donc tout le serveur : la clé
de GitHub Actions ne doit rien pouvoir faire d'autre que déployer. Sur l'ordinateur de l'administrateur
(PowerShell), la clé privée n'allant que dans GitHub :

```powershell
ssh-keygen -t ed25519 -N '""' -C github-actions-deploy -f "$env:USERPROFILE\.ssh\skull-darts-github-deploy"
Get-Content "$env:USERPROFILE\.ssh\skull-darts-github-deploy.pub" | ssh deploy@ADRESSE_DU_SERVEUR /opt/skull-darts-71/deploy/authorize-github-key.sh
cmd /c "gh secret set DEPLOY_SSH_KEY --env production --repo adamrodwebdev/darts-club < %USERPROFILE%\.ssh\skull-darts-github-deploy"
ssh-keygen -F ADRESSE_DU_SERVEUR | gh secret set DEPLOY_KNOWN_HOSTS --env production --repo adamrodwebdev/darts-club
Remove-Item "$env:USERPROFILE\.ssh\skull-darts-github-deploy*"
```

- Les deux secrets vont directement des fichiers à GitHub : la clé privée ne passe pas par le presse-papiers, dont
  Windows garde l'historique.
- `-N '""'` donne une clé sans phrase secrète sous Windows PowerShell 5.1 ; sous PowerShell 7.3 et suivants, écrire
  `-N ""`.
- La clé d'hôte vient du `known_hosts` de l'administrateur, auquel il se fie depuis sa première connexion. Le
  `ssh-keyscan` livré avec Windows (OpenSSH 9.5) échoue face au serveur
  (`choose_kex: unsupported KEX method sntrup761x25519-sha512@openssh.com`) ; `gh` enregistre alors un secret vide
  sans rien signaler, et ce secret d'environnement l'emporte sur un secret du dépôt du même nom.
- Vérifier la date des deux secrets (**Settings › Environments › production**), puis lancer un déploiement d'essai
  (**Actions › Déploiement › Run workflow**) : le journal montre la copie de fichiers refusée (« Copie refusée »)
  et le déploiement passé.

[`deploy/authorize-github-key.sh`](../deploy/authorize-github-key.sh) écrit la ligne
`restrict,command="/opt/skull-darts-71/deploy/ssh-gate.sh" ssh-ed25519 AAAA…` : avec cette clé, le serveur
n'accepte que `deploy <tag>` ([`deploy/ssh-gate.sh`](../deploy/ssh-gate.sh)), ni session, ni copie de fichier, ni
tunnel ; il tire l'image publiée et les fichiers de déploiement qu'elle contient. Il affiche ensuite la liste des
clés et leur rôle (`--list` pour la revoir) ; une ancienne clé de déploiement à accès complet se retire avec
`--remove SHA256:…` (empreinte de votre propre clé : `ssh-keygen -lf "$env:USERPROFILE\.ssh\id_ed25519.pub"`).
Supprimer enfin les anciens secrets **du dépôt** du même nom, s'il y en a (*Repository secrets*).

*Required reviewers* reste facultatif : chaque mise en production, y compris celle du lundi, attendrait alors
une validation.

Protéger aussi `main` (**Settings › Rules › Rulesets › New branch ruleset**, cible `main`) : pull request
obligatoire, contrôles de la CI requis, ni force push ni suppression. Un jeton GitHub volé ne peut plus
alors pousser directement du code qui partirait en production.

Tant que `DEPLOY_HOST` est vide, le workflow « Déploiement » publie seulement l'image sur `ghcr.io`.
Le lancer une première fois à la main (**Actions › Déploiement › Run workflow**), puis dans
**Packages › darts-club › Package settings**, passer la visibilité en **Public** : le serveur peut alors
télécharger l'image sans identifiant (elle ne contient aucun secret, ce que la CI vérifie à chaque build).

## 7. Premier déploiement

1. Fusionner les PR dans `main` : la CI passe, puis « Déploiement » publie l'image (et déploie si le serveur est configuré).
2. Ou à la main, sur le serveur, avec le tag d'une image publiée (ses fichiers `compose.yaml`,
   `compose.prod.yaml` et `deploy/` sont copiés depuis l'image, comme le fait GitHub Actions) :
   ```bash
   /opt/skull-darts-71/deploy/ssh-gate.sh deploy 1a2b3c4d5e6f
   ```
   (`./deploy/deploy.sh latest` relance la version `latest` avec les fichiers déjà présents.)
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
3. Planifier la sauvegarde quotidienne (3 h 30, heure de Paris) avec un minuteur systemd de l'utilisateur
   `deploy` (Debian minimal : pas de `cron`, et aucun droit administrateur nécessaire) :
   ```bash
   loginctl enable-linger deploy              # le minuteur tourne même sans session ouverte
   mkdir -p ~/.config/systemd/user
   cp /opt/skull-darts-71/deploy/systemd/skull-darts-backup.* ~/.config/systemd/user/
   systemctl --user daemon-reload
   systemctl --user enable --now skull-darts-backup.timer
   systemctl --user list-timers               # prochaine exécution
   ```
   Journal : `/var/backups/skull-darts-71/backup.log`. Une exécution manquée (serveur éteint) est rattrapée au démarrage.
4. **Copie hors du serveur, gratuite** : l'ordinateur du bureau (Windows) récupère chaque jour les sauvegardes chiffrées.
   - Sur l'ordinateur, depuis le dépôt : `powershell -ExecutionPolicy Bypass -File deploy\windows\Install-BackupTask.ps1`
     (option `-Destination "D:\…"` ; par défaut `Documents\Sauvegardes Skull Darts 71`). Le script crée une clé SSH dédiée,
     installe la tâche planifiée « Skull Darts 71 - copie des sauvegardes » (12 h 30 et ouverture de session, rattrapée
     si l'ordinateur était éteint) et affiche la clé publique.
   - Sur le serveur, autoriser cette clé **en lecture des sauvegardes uniquement** (une ligne dans `~deploy/.ssh/authorized_keys`) :
     ```
     restrict,command="/opt/skull-darts-71/deploy/backup-share.sh" ssh-ed25519 AAAA… skull-darts-71-backup-pull
     ```
     La clé ne permet que `list` et `get <nom>` (`deploy/backup-share.sh`) : ni session, ni autre commande, ni fichier hors sauvegardes.
   - L'ordinateur garde les 30 dernières sauvegardes, toujours chiffrées ; journal : `copie.log` dans le dossier de destination.
     Choisir un dossier synchronisé (OneDrive, Google Drive) ajoute une copie dans le cloud.
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
- Correctifs de sécurité : chaque lundi à 4 h 30 (UTC), le workflow « Déploiement » reconstruit l'image sans
  cache sur la dernière image de base (PHP, Caddy) avec les derniers paquets Alpine (ffmpeg, OpenSSL…), installe
  les correctifs de PostgreSQL, Redis et Mercure, puis vérifie `/api/health`. Une faille urgente :
  **Actions › Déploiement › Run workflow** (branche `main`).
- Retour arrière : relancer avec l'étiquette (SHA court) de la version précédente, visible dans GitHub › Packages :
  `./deploy/deploy.sh 1a2b3c4d5e6f`. Les migrations de base ne sont pas annulées automatiquement.
- Mises à jour système : automatiques (unattended-upgrades), moteur Docker compris (dépôt de Docker ajouté par
  `server-setup.sh`) ; le serveur redémarre seul à 4 h 15 quand une mise à jour du noyau ou de la libc l'exige.
- Debian 12 est en support long terme (LTS) depuis juin 2026, jusqu'en juin 2028 : prévoir le passage à
  Debian 13 avant.

## 13. Vérifications après la mise en ligne

- [ ] [SSL Labs](https://www.ssllabs.com/ssltest/) : note A ou A+
- [ ] [securityheaders.com](https://securityheaders.com) : note A
- [ ] Workflow « Audit de sécurité (OWASP ZAP) » lancé à la main : vert
- [ ] Lighthouse mobile ≥ 95 sur l'accueil
- [ ] Inscription → email reçu (mail-tester ≥ 9/10) → connexion
- [ ] Notifications push : activées depuis Mon compte › Notifications, reçues sur un téléphone
- [ ] Temps réel : `dc exec php bin/console app:realtime:check` répond « abonnement accepté, publication reçue ». Sinon, il donne la raison du refus, et `dc logs mercure` la détaille (émetteur, audience, expiration). Puis, connecté dans deux navigateurs, une notification envoyée à l'un apparaît sans recharger la page.
- [ ] Mentions légales et confidentialité complétées par le bureau (hébergeur : nom, adresse, téléphone)
- [ ] Première sauvegarde présente dans `/var/backups/skull-darts-71`, restauration testée
