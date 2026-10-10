# Feuille de route — Plateforme du club de fléchettes

## 0. Stack
- **Back** : Symfony 7.4 LTS, PHP 8.4, API Platform 4, Doctrine/PostgreSQL 17, Redis, Messenger (async), Mercure (temps réel)
- **Front** : Vue 3.5 + Vite + TypeScript, Pinia, Vue Router, Tailwind 4, GSAP/Motion (`prefers-reduced-motion` respecté), PWA (Workbox)
- **Infra** : Docker Compose (FrankenPHP, Postgres, Redis, Mailpit), GitHub Actions, monorepo `/api` + `/web`

## 1. Socle projet (S1)
- Dépôt GitHub privé, branche `main` protégée, PR obligatoires, Conventional Commits, Dependabot, CodeQL
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
- Suivi des cotisations (saison, statut, échéances), relances auto (`MembershipExpiring`, Scheduler)
- Interface trésorier : encaissements, export CSV/PDF, tableau de bord
- Paiement en ligne optionnel (Stripe/HelloAsso) — webhooks signés

## 7. Interface président / administration (S5)
- Gestion des membres et rôles, modération
- Messagerie présidentielle avec labels `[cotisation, événement, réunion, annulation]` → 1 / sélection / tous
- Canal d'informations (annonces épinglées, temps réel + push)

## 8. Calendrier & événements (S6)
- Planification, inscriptions, export iCal, rappels J-7 / J-1 (mail + push)
- Archives : résumé, galerie (images optimisées, lazy), textes, posts ; recherche/filtre par saison

## 9. Landing page « metal » (S7)
- Identité visuelle unique : typographie display, textures, cible de fléchettes animée (SVG/Canvas léger)
- Références musicales légales : noms de groupes + liens officiels (Bandcamp/site), embeds en **façade** (chargés au clic), aucune image/paroles protégées
- Pré-rendu statique (SSG) pour la perf et le SEO

## 10. Performance (Lighthouse ≥ 95)
- SSG landing, code-splitting par route, preload des polices (subset, `font-display: swap`)
- Images AVIF/WebP `srcset`, dimensions fixes (CLS ≈ 0), animations sur `transform/opacity` uniquement
- Cache HTTP + Brotli, budget JS < 150 Ko gz initial, accessibilité WCAG 2.2 AA

## 11. Livraison (S8)
- Tests E2E complets, audit sécurité (OWASP ZAP), pentest léger
- Déploiement (VPS Docker ou PaaS), sauvegardes chiffrées, monitoring (Sentry), uptime
