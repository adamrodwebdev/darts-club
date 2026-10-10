# Feuille de route — Plateforme du club de fléchettes

## 0. Stack
- **Back** : Symfony 7.4 LTS, PHP 8.4, API Platform 4, Doctrine/PostgreSQL 17, Redis, Messenger (async), Mercure (temps réel)
- **Front** : Vue 3.5 + Vite + TypeScript, Pinia, Vue Router, Tailwind 4, GSAP/Motion (`prefers-reduced-motion` respecté), PWA (Workbox)
- **Infra** : Docker Compose (FrankenPHP, Postgres, Redis, Mailpit), GitHub Actions, monorepo `/api` + `/web`

## Directives transverses
- **Programmation orientée objet** : entités riches, objets valeur, services à responsabilité unique, interfaces aux frontières, stratégies polymorphes, événements de domaine (détail dans [CLAUDE.md](CLAUDE.md))
- **Responsive design mobile-first** : 320 → 1920 px sans défilement horizontal, typographie fluide, cibles tactiles ≥ 44 px, tests Playwright mobile + desktop
- **Mode sombre** : thème clair / sombre / système, sélecteur dans l'interface (voir étape 10 bis)
- **Bilingue FR / EN** : aucun texte en dur, toute chaîne visible passe par les traductions (voir étape 10 bis)

## 1. Socle projet (S1)
- Dépôt GitHub public, branche `main` protégée, PR obligatoires, Conventional Commits, Dependabot, CodeQL
- CI : PHPStan (max), PHP-CS-Fixer, PHPUnit, ESLint, Vitest, Playwright, **Lighthouse CI (assert ≥ 95 sur les 4 catégories)**
- Environnements : dev / staging / prod, secrets via Symfony Secrets Vault

## 2. Sécurité (transverse, dès S1)
- Sessions cookie `HttpOnly; Secure; SameSite=Strict` (pas de JWT en localStorage), CSRF
- Hash Argon2id, Rate Limiter (login, inscription, messagerie), 2FA TOTP optionnel (scheb/2fa) — obligatoire pour le bureau
- CSP stricte (nonces), HSTS, headers via NelmioSecurityBundle, CORS restreint
- Voters par rôle : `ROLE_MEMBER`, `ROLE_SECRETARY`, `ROLE_TREASURER`, `ROLE_PRESIDENT`, `ROLE_ADMIN`
- Upload avatar : validation MIME, redimensionnement + ré-encodage (WebP/AVIF), stockage hors webroot
- RGPD : consentement, export/suppression des données, journal d'audit, politique de rétention

## 3. Comptes & authentification (S2)
- Inscription : email obligatoire, téléphone facultatif (E.164, libphonenumber)
- Vérification email obligatoire (SymfonyCasts VerifyEmailBundle) — compte inactif tant que non vérifié
- OAuth 2.0 optionnel (Google/Discord via KnpU OAuth2 Client), liaison à un compte existant
- Événements : `UserRegistered` → `SendVerificationEmail` (Messenger)

## 4. Architecture événementielle
- Événements de domaine (EventDispatcher) → handlers asynchrones (Messenger/Redis) :
  `UserRegistered`, `MembershipPaid`, `MembershipExpiring`, `EventScheduled`, `EventCancelled`, `MessageSent`, `AnnouncementPublished`
- Notifications centralisées : Symfony Notifier (email + Web Push VAPID) + préférences utilisateur
- Temps réel : Mercure (messagerie, canal d'infos, badges non-lus)

## 5. Espace adhérent (S3)
- Profil : infos, avatar, préférences de notification, sécurité (2FA, sessions actives)
- Messagerie membre ↔ membre et membre ↔ bureau (boîtes de rôle : secrétaire, trésorier, président)

## 6. Cotisations & trésorerie (S4)
- ✅ Suivi des cotisations (saison sept. → août, statut, échéance), relances auto hebdomadaires (Scheduler)
- ✅ Interface trésorier : encaissements, annulations motivées, export CSV, tableau de bord
- ✅ Badges : « Cotisant » (saison en cours payée), Président, Trésorier, Secrétaire
- À faire : export PDF (reçu de cotisation / attestation fiscale)
- À faire : paiement en ligne optionnel (HelloAsso ou Stripe) — webhooks signés, nécessite un compte de l'association

## 7. Interface président / administration (S5)
- ✅ Gestion des membres et rôles du bureau, modération (suspension réversible, sessions coupées), journal d'audit
- ✅ Messagerie présidentielle avec labels `[cotisation, événement, réunion, annulation]` → 1 / sélection / tous
- ✅ Canal d'informations (annonces épinglées, temps réel + push)

## 8. Calendrier & événements (S6)
- ✅ Planification, inscriptions (capacité), export iCal + abonnement personnel, rappels J-7 (tous) / J-1 (inscrits)
- ✅ Archives : résumé, galerie (WebP, vignettes, lazy, visionneuse), posts des adhérents ; recherche/filtre par saison
- À faire : liste d'attente quand l'événement est complet

## 9. Landing page « metal » (S7)
- ✅ Identité visuelle unique : titres chromés, cible SVG animée (fléchette qui se plante), apparitions au défilement, hymne original « Triple Vingt » + riff synthétisé (Web Audio)
- ✅ Section « Groupe ami » (Blind to Deaf) ; ambiances musicales reprises dans le design et l’hymne, sans citer d’artistes
- ✅ Pages mentions légales + confidentialité (à compléter par le bureau), Open Graph, JSON-LD
- ✅ En-têtes SEO de l'accueil générés en FR et EN (étape 10 bis) ; rendu statique complet reporté à l'étape 11

## 10. Performance (Lighthouse ≥ 95)
- ✅ Code-splitting par route, polices système (aucune police à télécharger), logo SVG optimisé (SVGO) et préchargé
- ✅ Images à dimensions fixes (CLS = 0), photos WebP + vignettes en lazy loading, animations transform/opacity uniquement
- ✅ Budget vérifié en CI : JS initial ≤ 150 Ko gzip (actuel ≈ 54 Ko), CSS ≤ 30 Ko, module à la demande ≤ 100 Ko
- ✅ Lighthouse CI sur 4 pages (accueil, connexion, inscription, confidentialité) : scores ≥ 95, LCP ≤ 2,5 s, CLS ≤ 0,1, TBT ≤ 200 ms
- ✅ Accessibilité WCAG 2.2 AA : audit axe-core automatisé (pages publiques + espace adhérent)
- ✅ Serveur de production unique (FrankenPHP/Caddy) : front + API + Mercure sur la même origine, compression zstd/Brotli/gzip, cache immuable des fichiers versionnés, CSP stricte, HSTS
- ✅ Production : OPcache + préchargement, cache et sessions dans Redis, image sans root, sans fichiers locaux ni secrets (vérifié en CI)
- ✅ Manifest d'application, icônes (dont iPhone et « maskable »), image de partage Open Graph
- ✅ URL absolue de l'image Open Graph et service worker hors ligne (étape 11)

## 10 bis. Thème & internationalisation
Livrée en trois PR : **a** (thème, socle i18n, pages publiques), **b** (espace adhérent et bureau), **c** (API).
- ✅ **Mode sombre** (a) : clair / sombre / système, bouton dans l'en-tête et choix détaillé dans « Mon compte », mémorisé dans le navigateur ; aucun flash grâce à `theme-init.js` exécuté avant le rendu (fichier externe : la CSP interdit l'inline) ; contrastes AA vérifiés par axe-core dans les deux thèmes
- ✅ **Socle de traduction** (a) : `vue-i18n` avec messages précompilés (aucun compilateur ni `eval` dans le bundle), `fr.json` embarqué, `en.json` chargé à la demande, clés vérifiées par TypeScript
- ✅ Langue : choix mémorisé, sinon langue du navigateur ; sélecteur dans l'en-tête et le compte ; `Accept-Language` envoyé à l'API
- ✅ SEO (a) : accueil FR sur `/`, EN sur `/en` (`en.html` généré au build avec titre, description, Open Graph et JSON-LD traduits), `lang` dynamique, `hreflang` + URL canonique
- ✅ Pages publiques traduites (a) : accueil, hymne (paroles chantées gardées en français), connexion, inscription, mentions légales et confidentialité (le français fait foi), 404
- ✅ Tests (a) : parité des clés FR/EN, des variables et des pluriels (Vitest, en CI) ; Playwright dans les deux thèmes et les deux langues
- ✅ Espace adhérent et bureau traduits (b) : compte, notifications, cotisation, événements, messagerie, trésorerie, présidence ; un fichier de traduction par domaine (`locales/<langue>/<domaine>.json`)
- ✅ Libellés des énumérations de l'API (rôles, badges, statuts, moyens de paiement, boîtes, catégories, journal) traduits côté front, repli sur le libellé de l'API (b)
- ✅ Dates, nombres et montants formatés selon la langue (`Intl`, module `i18n/format`) (b)
- ✅ Garde-fou CI (b) : règle ESLint `@intlify/vue-i18n/no-raw-text`, aucun texte en dur dans les gabarits ; parcours adhérent audité en anglais
- ✅ API traduite (c) : langue de la requête via `Accept-Language` (envoyé par le front), messages d'erreur et validations en français et en anglais, codes d'erreur stables pour le front (`email_not_verified`…)
- ✅ Langue et thème enregistrés sur le compte (c) : un choix fait sur l'appareil prime à la connexion, sinon celui du compte s'applique ; la langue est fixée à l'inscription
- ✅ Emails, notifications (centre, email, push), calendrier ICS et export CSV dans la langue de chaque destinataire (c)
- ✅ Contrôle en CI (c) : test PHPUnit qui vérifie que chaque message français a sa traduction anglaise et que les catalogues à clés sont complets
- ✅ `hreflang` en dur au build (étape 11) ; rendu complet du contenu au build (SSG) non retenu : l'en-tête traduit suffit au référencement

## 11. Livraison (S8)
- ✅ Hébergement retenu : VPS + Docker (Hetzner ou OVH), emails Brevo, sonde de disponibilité sans service de suivi d'erreurs
- ✅ Image de production publiée sur ghcr.io par GitHub Actions, déploiement SSH automatique après une CI verte sur `main` (environnement protégé), migrations et vérification `/api/health`, retour arrière par SHA
- ✅ `compose.prod.yaml` : secrets obligatoires depuis le `.env` du serveur, volume persistant des avatars et photos, redémarrage automatique, rotation des journaux
- ✅ Sauvegardes quotidiennes chiffrées (age) de la base et des fichiers, copie distante facultative (rclone), script de restauration
- ✅ Amorçage : commande `app:member:grant-role` pour nommer le premier président ; boutons OAuth affichés seulement s'ils sont configurés
- ✅ Audit OWASP ZAP (scan passif hebdomadaire de la production) et revue de sécurité du code avant la mise en ligne
- ✅ SEO : Open Graph, canonique, hreflang et sitemap en URL absolues au build ; robots.txt excluant les espaces adhérents
- ✅ Service worker : page hors ligne (navigation réseau d'abord, aucune donnée mise en cache)
- ✅ Guide de mise en production : [docs/deploiement.md](docs/deploiement.md)
- À faire (bureau) : choisir l'hébergeur et le domaine, créer les comptes Brevo / UptimeRobot, compléter les mentions légales, suivre le guide
