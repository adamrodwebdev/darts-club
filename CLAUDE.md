# Directives du projet Skull Darts 71

Monorepo : `api/` (Symfony 7.4 LTS, PHP 8.4) + `web/` (Vue 3, TypeScript). Feuille de route : [ROADMAP.md](ROADMAP.md).

Nom officiel de l’association : **Skull Darts 71** (logo : `web/public/logo.svg`, Facebook dans `web/src/content/landing.ts`). Les groupes de musique qui inspirent le design et l’hymne ne sont jamais cités sur le site.

## Programmation orientée objet

### Back (PHP)
- **Une classe = une responsabilité.** Les contrôleurs sont minces : ils valident (DTO + `#[MapRequestPayload]`), délèguent à un service, puis renvoient la réponse.
- **Entités riches, pas anémiques.** Pas de setters génériques pour l'état métier : préférer des méthodes d'intention (`markVerified()`, `pay()`, `cancel()`) qui protègent les invariants. Les champs obligatoires passent par le constructeur.
- **Objets valeur** immuables (`final readonly class`) pour les concepts métier (Email, Money, Season, Label…) et pour les DTO. Les valeurs qui voyagent ensemble en forment un (`PaymentDetails`, `NewAccount`, `MailboxTopic`, `PushEndpoint`) : **trois paramètres au plus** par constructeur ou méthode, sinon un objet valeur ou des arguments nommés.
- **Commande ou question, pas les deux** : une méthode qui modifie l'état ne renvoie rien (sauf une fabrique, qui renvoie l'objet créé) ; l'appelant lit l'état avant d'agir (`$previous = $event->poster(); $event->attachPoster($poster);`).
- **Classes `final` par défaut**, `private` par défaut, `readonly` dès que possible. L'héritage seulement pour un vrai « est-un » ; sinon composition.
- **Dépendre d'abstractions** (interfaces) aux frontières : notifications, paiement, stockage, horloge (`ClockInterface`), hasard (`TokenGenerator`).
- **Ni heure ni hasard cachés** : jamais de `new \DateTimeImmutable()` ni de `random_bytes()` dans le code métier. Les entités reçoivent `$now` (et les jetons déjà tirés) du service qui tient l'horloge et le `TokenGenerator` ; les tests figent l'horloge. Limitation de débit : `RequestThrottle`.
- **Polymorphisme plutôt que `switch`** : stratégies taguées (`#[AutoconfigureTag]` + `#[AutowireIterator]`) pour les canaux de notification, les fournisseurs OAuth, les types de messages…
- **Enums PHP** pour les ensembles fermés (rôles, statuts de cotisation, labels de message).
- **Traductions** : un message d'erreur s'écrit en français, ce texte sert de clé (domaine `errors`, version anglaise dans `translations/errors.en.yaml`) ; paramètres `%nom%` via `InvalidValue` / `UnprocessableException`. Textes rédigés par l'application (emails, notifications, exports) : domaines à clés, fichiers `fr` et `en`. Tout ce qui part vers un adhérent (email, notification) est rendu dans **sa** langue (`User::language()`).
- **Événements de domaine** pour les effets de bord (emails, push, journal) : le code métier publie, des listeners/handlers Messenger réagissent.
- Autorisations dans des **Voters**, jamais en `if` dans les contrôleurs.
- Organisation par domaine quand un domaine grossit : `src/Membership/`, `src/Event/`, `src/Messaging/`…

### Front (TypeScript / Vue)
- **Couche services en classes** : un client HTTP (`ApiClient`) injecté dans des services par domaine (`AuthService`, `EventService`…) ; les composants n'appellent jamais `fetch` directement.
- **Aucun singleton ni localisateur de services** : `main.ts` est la racine de composition (`createServices()`, `createPreferenceControllers(window)`, `createAppRouter(services)`) et fournit les dépendances à l'application ; composants, composables et stores les reçoivent par `useServices()` / `usePreferenceControllers()`. Les tests fournissent leurs propres instances.
- **Heure injectée** : l'horloge (`Clock`, `services.clock`) donne la date du jour ; jamais de `new Date()` / `Date.now()` dans la logique.
- **Modèles typés** (classes ou interfaces + fonctions de mapping) ; les réponses API sont validées à la frontière. Au-delà de trois paramètres, un constructeur prend un **objet d'options** (`new EventSummary({ id, title, … })`) ; la construction depuis l'API passe par une fabrique nommée (`EventSummary.parse()`). Pas de champ public modifiable : une méthode d'intention (`notification.markRead()`).
- **Pas de condition répétée sur un type** : une table ou une stratégie (ex. `ATTENDANCE_LABELS` pour les libellés des réponses).
- Logique réutilisable dans des **composables** ; état partagé dans des **stores Pinia** ; composants centrés sur l'affichage.
- `strict` TypeScript, pas de `any`.
- **Aucun texte en dur** : toute chaîne visible passe par `vue-i18n` (`src/i18n/locales/fr.json` + `en.json`, mêmes clés) ; dates, nombres et montants via `Intl` dans la langue courante. Couleurs uniquement via les jetons CSS (thèmes clair et sombre).

## Responsive design (obligatoire)
- **Mobile-first** : styles de base pour ~360 px, puis des paliers `width >=` en `rem` (`40 / 48 / 64 / 80rem` = 640 / 768 / 1024 / 1280 px à 16 px), en syntaxe d'intervalle (`@media (width >= 48rem)`), placés là où le contenu casse. Jamais de requête en px : les paliers suivent la taille de texte de l'utilisateur.
- **Intrinsèque d'abord** : grilles `repeat(auto-fit, minmax(min(100%, 16rem), 1fr))`, flex qui passe à la ligne, `min()` / `clamp()`. Les **composants** s'adaptent à leur emplacement par **requêtes de conteneur** (nommées : `container: event-card / inline-size`) ; les requêtes média ne servent qu'au squelette de page et aux préférences (`prefers-*`, `pointer`, hauteur).
- **Aucun défilement horizontal** de 320 px à 2560 px, vérifié automatiquement (`e2e/responsive.spec.ts`, 17 largeurs, éléments en cause nommés). Largeurs fluides (`%`, `fr`, `clamp()`, `min()`), jamais de largeur fixe en px pour un conteneur, `100%` au lieu de `100vw`.
- **Hauteur d'écran** : `svh` (stable) au lieu de `vh` ; `dvh` seulement pour une coque d'application (messagerie, dialogue).
- **Typographie fluide** : `clamp(rem, rem + vw, rem)`, jamais de `vw` seul (le texte doit grossir avec le zoom), maximum ≤ 2,5 × minimum ; `text-wrap: balance` sur les titres. Espacements en `rem`. Champs de formulaire à 16 px au moins (sinon iOS zoome).
- **En-tête collant** seulement si la hauteur le permet (`@media (height >= 40rem)`) et `scroll-padding-block-start` : le focus n'est jamais caché (WCAG 2.4.11), la page se réorganise à 320 × 256 (zoom 400 %, WCAG 1.4.10).
- **Cibles tactiles ≥ 44 × 44 px**, pas d'interaction réservée au survol.
- **Images responsives** : `srcset` avec un `sizes` exact (déduit du CSS), AVIF/WebP, `width`/`height` déclarés (CLS ≈ 0), `loading="lazy"` hors écran, jamais sur l'image principale.
- Tableaux (trésorerie, membres) : vue en cartes sur mobile ou défilement contenu dans le tableau.
- Navigation : menu compact accessible au clavier sur mobile.
- Respect de `prefers-reduced-motion` et `prefers-color-scheme`.
- **Défilement libre** : jamais d'accrochage vertical de la page (`scroll-snap` seulement dans les bandes horizontales), aucune transformation 3D sur un parent de bande qui défile (elle retient le geste du doigt).
- **Animations** : `transform` et opacité seulement, jamais une propriété de mise en page. Celles d'entrée se rejouent à chaque retour à l'écran (`v-replay`) ; celles liées au défilement jouent dans les deux sens (plages `entry` et `exit`).
- **Tests** : Playwright exécute les parcours en viewports mobile (390×844) et desktop (1280×800), le balayage 320 → 2560 px et le test de réorganisation à 320 × 256 ; Lighthouse sur mobile, pire des 3 passages (voir ci-dessous).

## Qualité et sécurité (rappel)
- API : PHPStan niveau max, PHP-CS-Fixer, PHPUnit. Web : ESLint, oxlint, `vue-tsc`, Vitest, Playwright.
- Toute PR garde Lighthouse à **100** en accessibilité, bonnes pratiques et SEO (pire des 3 passages, contrôlé en CI) ; performance ≥ 95 pour l'instant, objectif 100. Corriger la cause, jamais masquer un audit.
- Aucun secret commité (dépôt **public**) : `bin/console secrets:set` et variables d'environnement.
- Écritures protégées par le contrôle d'Origin ; nouvelles routes publiques à déclarer explicitement dans `security.yaml`.
- Commits Conventional Commits, une PR par étape.
