# Directives du projet Darts Club

Monorepo : `api/` (Symfony 7.4 LTS, PHP 8.4) + `web/` (Vue 3, TypeScript). Feuille de route : [ROADMAP.md](ROADMAP.md).

## Programmation orientée objet

### Back (PHP)
- **Une classe = une responsabilité.** Les contrôleurs sont minces : ils valident (DTO + `#[MapRequestPayload]`), délèguent à un service, puis renvoient la réponse.
- **Entités riches, pas anémiques.** Pas de setters génériques pour l'état métier : préférer des méthodes d'intention (`markVerified()`, `pay()`, `cancel()`) qui protègent les invariants. Les champs obligatoires passent par le constructeur.
- **Objets valeur** immuables (`final readonly class`) pour les concepts métier (Email, Money, Season, Label…) et pour les DTO.
- **Classes `final` par défaut**, `private` par défaut, `readonly` dès que possible. L'héritage seulement pour un vrai « est-un » ; sinon composition.
- **Dépendre d'abstractions** (interfaces) aux frontières : notifications, paiement, stockage, horloge (`ClockInterface`).
- **Polymorphisme plutôt que `switch`** : stratégies taguées (`#[AutoconfigureTag]` + `#[AutowireIterator]`) pour les canaux de notification, les fournisseurs OAuth, les types de messages…
- **Enums PHP** pour les ensembles fermés (rôles, statuts de cotisation, labels de message).
- **Événements de domaine** pour les effets de bord (emails, push, journal) : le code métier publie, des listeners/handlers Messenger réagissent.
- Autorisations dans des **Voters**, jamais en `if` dans les contrôleurs.
- Organisation par domaine quand un domaine grossit : `src/Membership/`, `src/Event/`, `src/Messaging/`…

### Front (TypeScript / Vue)
- **Couche services en classes** : un client HTTP (`ApiClient`) injecté dans des services par domaine (`AuthService`, `EventService`…) ; les composants n'appellent jamais `fetch` directement.
- **Modèles typés** (classes ou interfaces + fonctions de mapping) ; les réponses API sont validées à la frontière.
- Logique réutilisable dans des **composables** ; état partagé dans des **stores Pinia** ; composants centrés sur l'affichage.
- `strict` TypeScript, pas de `any`.

## Responsive design (obligatoire)
- **Mobile-first** : styles de base pour ~360 px, puis `min-width` aux points de rupture `640 / 768 / 1024 / 1280 px`.
- **Aucun défilement horizontal** de 320 px à 1920 px. Mises en page en **Grid/Flexbox**, largeurs fluides (`%`, `fr`, `clamp()`, `min()`), jamais de largeurs fixes en px pour les conteneurs.
- **Typographie fluide** (`clamp()`), espacements en `rem`.
- **Cibles tactiles ≥ 44 × 44 px**, pas d'interaction réservée au survol.
- **Images responsives** : `srcset`/`sizes`, AVIF/WebP, `width`/`height` déclarés (CLS ≈ 0), `loading="lazy"` hors écran.
- Tableaux (trésorerie, membres) : vue en cartes sur mobile ou défilement contenu dans le tableau.
- Navigation : menu compact accessible au clavier sur mobile.
- Respect de `prefers-reduced-motion` et `prefers-color-scheme`.
- **Tests** : Playwright exécute les parcours en viewports mobile (390×844) et desktop (1280×800) ; Lighthouse ≥ 95 sur mobile.

## Qualité et sécurité (rappel)
- API : PHPStan niveau max, PHP-CS-Fixer, PHPUnit. Web : ESLint, oxlint, `vue-tsc`, Vitest, Playwright.
- Toute PR garde Lighthouse ≥ 95 dans les 4 catégories.
- Aucun secret commité (dépôt **public**) : `bin/console secrets:set` et variables d'environnement.
- Écritures protégées par le contrôle d'Origin ; nouvelles routes publiques à déclarer explicitement dans `security.yaml`.
- Commits Conventional Commits, une PR par étape.
