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
- ✅ Identité visuelle unique : titres chromés, cible SVG animée (fléchette qui se plante), animations au défilement, hymne original « Triple Vingt » + riff synthétisé (Web Audio)
- ✅ Section « Groupe ami » (Blind to Deaf) ; ambiances musicales reprises dans le design et l’hymne, sans citer d’artistes
- ✅ Pages mentions légales + confidentialité (à compléter par le bureau), Open Graph, JSON-LD
- ✅ En-têtes SEO de l'accueil générés en FR et EN (étape 10 bis) ; rendu statique complet reporté à l'étape 11
- ✅ Accueil « manche de guitare » : on gratte de haut en bas d'une corde (section) à l'autre et on glisse de côté sur chacune (cartes du club, rendez-vous, onze parties de l'hymne) ; entrées en alternance gauche / droite liées au défilement. Accueil ramené de 6,1 à 5,2 écrans sur téléphone et de 5,6 à 4,2 sur ordinateur, rendez-vous passés compris
- ✅ Défilement fluide : plus d'accrochage vertical (à la molette comme au doigt, il ramenait la page au début des sections), main du pied de page qui n'allonge plus la page, carrousel en 2D (incliné, il retenait le geste du doigt) ; les animations se rejouent à chaque retour à l'écran, dans les deux sens
- ✅ Carrousel 3D des rendez-vous ouverts à tous, du prochain au plus ancien (passés compris, annulés exclus) ; main « cornes » qui surgit au pied de page ; tout reste immobile si les animations sont coupées

## 10. Performance (Lighthouse 100)
- ✅ Seuil Lighthouse relevé (2026-10-23) : 100 en accessibilité, bonnes pratiques et SEO sur le pire des 3 passages ; la CI mesure les pages publiques avec un bouchon d'API (réponses de la production pour un visiteur, plus d'erreur 502 en console)
- ✅ Performance 100 : écran titre peint depuis le HTML (logo et nom, style en ligne autorisé par son empreinte CSP), site chargé une fois le logo signalé comme plus grand élément peint (`build/deferredBoot.ts`), effacé en fondu dès la première page prête ; démarrage allégé (une seule langue, aucune mise en page forcée, cordes de l'accueil rendues une par tâche, animations continues de la cible confiées au compositeur, code de l'accueil à part, pied de page une tâche après la page, cartes de paroles rendues par paquets) ; l'aperçu de test applique la CSP de production ; démarrage découpé en tâches courtes (modules, services et routeur, extensions, en-tête, page) ; en CI, trois passages de préchauffage non comptés sur l'accueil (démarrage à froid de la machine) et trois sur une page sonde fixe, puis un sur chaque page mesurée, au ralentissement du processeur calé sur deux horloges de la machine, la plus douce l'emportant (2026-11-09) : l'indice JavaScript de Lighthouse (4× pour un indice de 2450) et le travail du navigateur sur la page sonde (4× pour 33,6 ms, première estimation à affiner avec les journaux de la CI), pour ne jamais simuler un téléphone plus lent que la cible ; le détail de chaque passage et des deux horloges dans le journal
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
- ✅ **Socle de traduction** (a) : `vue-i18n` avec messages précompilés (aucun compilateur ni `eval` dans le bundle), chaque langue chargée à la demande (seule celle de la page est téléchargée et évaluée, depuis l'étape « performance 100 »), clés vérifiées par TypeScript
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

## 12. Outils événements du bureau (S9)
Validés avec la maquette le 24 octobre 2026. Livrés en trois PR : **a** (affiche QR), **b** (bilan d'une soirée), **c** (documents pré-remplis).
- ✅ **Affiche QR** (a) : page du bureau qui imprime une affiche A5 ou A4, fond blanc (économe en encre) ou noir, vers le site ou la page publique d'une soirée ; QR code dessiné par l'API (`endroid/qr-code`, correction haute pour le logo au centre), aucun service en ligne ; seule l'affiche sort à l'impression, au format du papier (pages nommées CSS) ; QR code téléchargeable en SVG
- ✅ **Bilan d'une soirée** (b) : articles de la buvette (prévu, acheté, rendu au fournisseur, vendu, reste ; prix d'achat et de vente), autres dépenses et recettes, résultat face au prévisionnel ; modifiable par la secrétaire et le trésorier, lu par la présidence, chaque modification au journal ; clôture (le trésorier rouvre) ; justificatifs (photos, PDF) privés, conservés 6 ans ; export CSV et impression ; compteurs de l'année (5 buvettes temporaires, 6 manifestations exonérées)
- ✅ **Documents pré-remplis** (c) : fiche privée de l'association (nom officiel, RNA, SIRET, siège, noms du bureau pour les courriers, assureur, attestation RC en PDF) ; page « Démarches » de chaque soirée (destinataire, date conseillée comptée depuis la soirée, retard, avancement : à faire, prête, envoyée, reçue, pas pour cette soirée) ; huit documents rendus par l'API en français : demandes de buvette (rang de la demande dans l'année), de salle, d'occupation du domaine public (renvoi vers les Cerfa 14023 et 14024), de fermeture tardive, d'attestation d'assurance, fiche SACEM, budget tiré du bilan, billets d'entrée numérotés avec souches (12 par page A4) ; champs pré-remplis (seuls les changements sont gardés, le reste suit la soirée), passages réécrits ou retirés directement sur la feuille, impression A4 ; préparés par le secrétariat et la présidence, lus par le bureau, chaque enregistrement au journal. La tombola (Cerfa 11823*03) est laissée de côté pour l'instant, à la demande du bureau
- Plus tard (d) : rappels des échéances, PDF générés par le serveur, résultats des soirées dans la trésorerie
- À faire (bureau) : remplir la fiche de l'association ; demander à la mairie de Bellevesvre si elle a ses propres formulaires ; demander à la sous-préfecture de Louhans la démarche pour une fermeture tardive ponctuelle

## 13. Soirées : photos et vidéos, buvette, commandes et recettes (S10)
Validés avec la [maquette](https://claude.ai/artifact/NcZQKthK6iDZ7Sm6bvgpWN) le 1er novembre 2026. Décisions du bureau : équipes par soirée ; consommations payées en jetons achetés à l'accueil, en espèces ou par carte (jeton vert 1 €, jeton jaune 0,50 €, sachet 10 €) ; le bilan devient la carte. Quatre PR vers `dev` : **a** (photos et vidéos), **b** (carte et équipes), **c** (commandes), **d** (recettes en direct) ; **a** est indépendante, **b** → **c** → **d** se suivent.
- ✅ **Photos et vidéos** (a) : la présidence et l'admin publient photos et vidéos à tout moment (avant, pendant et après la soirée) ; les membres gardent l'ajout de photos dès le début de la soirée ; tout reste réservé aux membres validés. Vidéos de 60 s au plus (durée lue sur l'appareil avant l'envoi, revérifiée par le serveur), 500 Mo au plus à l'envoi, envoi par morceaux qui reprend après une coupure ; conversion en tâche de fond (ffmpeg dans le worker), dans les formats les plus légers (demande du bureau du 1er novembre) : MP4 H.264 720p lisible partout, puis AV1 environ deux fois plus léger, proposé en premier aux navigateurs qui le décodent ; aperçus et photos en AVIF (20 à 40 % plus légers que le WebP, servis selon l'en-tête Accept), WebP pour les autres ; original supprimé ; lecture par l'API avec reprise (plages d'octets, exigées par iOS) et `preload="none"` ; 20 vidéos par soirée ; sûreté : signature du fichier vérifiée avant tout décodage, démultiplexeur imposé et fichiers locaux seuls pour ffmpeg, métadonnées (lieu GPS) retirées, seuls des fichiers réencodés servis (détail dans docs/securite.md) ; « Demander le retrait » : le média est masqué tout de suite, la présidence décide ; chaque ajout et chaque retrait au journal
- ✅ **Galerie en carrousel** (demande du 3 novembre 2026, validée avec la [maquette](https://claude.ai/artifact/EgijamBehjJ16J9EQgVE2L)) : la grille et la fenêtre de la visionneuse laissent place à un carrousel, une photo ou une vidéo à la fois, aussi grande que l'écran le permet ; au doigt, au pavé tactile, à la souris, aux flèches, aux vignettes ou au clavier ; transition « pile » liée au défilement (la suivante glisse par-dessus, l'autre s'enfonce dans l'ombre), sans bibliothèque, la même technique que le carrousel de l'accueil ; titre, filtres, photo, boutons et vignettes tiennent dans un écran de téléphone (vérifié à 390 × 844 et 393 × 659) ; plein écran au toucher, fermé par son bouton ; grandes images chargées pour le média à l'écran et ses voisins seulement, vidéos jamais lancées seules et arrêtées quand on passe à la suivante ; supprimer passe désormais, comme la demande de retrait, par une confirmation
- ✅ **Carte et équipes** (b) : les articles de la buvette du bilan deviennent la carte : servi par le bar ou la cuisine ; vendu au litre, à l'unité ou à la portion ; contenant et contenance (fût de 30 L, pack de 24), prévu, acheté, rendu, prix du contenant ; formats et prix en euros, payés en jetons (verre, pichet… ; contenance facultative : sans elle, on compte les verres et les pichets vendus, et le reste en litres s'affiche dès qu'elle est connue) ; garnitures des plats ; boisson alcoolisée (groupe 3 seulement, rappel de la règle des buvettes temporaires). Paramètres de la soirée : jetons et sachets vendus à l'accueil, chacun avec sa valeur (jeton vert 1 €, jeton jaune 0,50 €, sachet 10 €) ; fond de caisse de l'accueil. Équipes de la soirée (accueil, bar, cuisine) choisies par le bureau parmi les adhérents. Bilan en trois parties (carte et stock, dépenses et recettes, justificatifs) : tout le bureau et l'admin préparent la carte, les jetons et les équipes, la secrétaire et le trésorier gardent les comptes. Les bilans déjà clôturés ne changent pas
- ✅ **Commandes** (c, livrée en deux PR : **c1** accueil, **c2** bar et cuisine) : écrans des équipes ouverts le jour de la soirée seulement (le bureau et l'admin gardent l'accès à tout) ; valeurs des jetons figées dès la première vente ; *accueil* : vente de sachets et de jetons verts ou jaunes (espèces ou carte, aide au rendu de monnaie), annulation inscrite au journal, jamais effacée ; *bar* : prise de commande (tuiles, formats, garnitures, repère facultatif, numéro de commande), total en euros et jetons à encaisser (« 21 verts + 1 jaune »), boissons servies sur place, plats envoyés en cuisine « en attente », vue « Prêtes » avec son et vibration, « Remise » qui verrouille la commande ; *cuisine* (tablette) : file par ancienneté, minuteur, bouton « Prête », articles et garnitures « épuisés » cachés au bar, écran gardé allumé. Le serveur impose l'ordre des états (en attente → prête → remise, ou annulée tant qu'elle attend) : une étape ne se valide qu'une fois, et le second appareil lit « déjà confirmée ». Temps réel par Mercure (passage préalable en v1, qui remplace la #85) ; bandeau hors ligne et renvoi automatique sans doublon (identifiant de commande tiré sur l'appareil). Stock décompté à chaque commande, article épuisé à zéro
- ✅ **Recettes en direct** (d) : pour le bureau et l'admin (4ᵉ partie du bilan), recalculées depuis la base à chaque commande et à chaque vente (aucun compteur qui dérive ; montants entiers en centimes), relues par le temps réel et toutes les 15 s en secours. Accueil : argent encaissé, en espèces et par carte, détaillé par sachets et jetons ; attendu en caisse = fond de caisse + espèces. Bar et cuisine : valeur des jetons dépensés, au prix figé dans chaque ligne de commande ; une commande annulée rend ses plats (ses boissons sont servies), et le bar lit les jetons à rendre au client. Jetons en circulation (en euros) = vendus − dépensés. Ventes et reste par article (litres convertis en verres). Dès que l'accueil a vendu des jetons, le bilan, son export CSV et le budget des démarches comptent l'argent de l'accueil à la place des ventes de la buvette ; export CSV des recettes
- ✅ **Mercure 1.x** (préalable à c, PR à part, remplace la #85) : hub `dunglas/mercure:v1.1` ; jetons d'accès RFC 9068 émis par l'API (émetteur de confiance, audience fixée sur l'URL publique du hub, expiration) ; cookie `__Secure-mercure_access_token` ; abonnements par `match` et droits en motifs d'URL ; reconnexion avec un nouveau cookie quand le hub refuse (les écrans d'une soirée restent ouverts des heures) ; flux temps réel jamais compressé. Corrige le temps réel en production : l'API parlait déjà le protocole 1.0 à un hub 0.21, les notifications n'arrivaient qu'au rechargement
- Fini quand : tests API, front et parcours (mobile et ordinateur) ; balayage 320 → 2560 px ; thèmes clair et sombre ; Lighthouse 100 inchangé sur les pages publiques ; répétition à blanc avec le bureau sur une soirée d'essai avant la première vraie soirée
- Plus tard : consommations payées sans jetons, entrées payantes à l'accueil, ticket de cuisine imprimé, statistiques d'une soirée à l'autre, fonctionnement sans réseau (serveur local)
- À faire (bureau) : vérifier la 4G dans la salle de Bellevesvre (sinon un routeur 4G ou un partage de connexion) ; prévoir une tablette pour la cuisine
