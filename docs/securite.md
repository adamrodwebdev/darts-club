# Sécurité — bilan avant la mise en production

Revue de code (« pentest léger ») menée avant la première mise en ligne : chaque route a été
suivie du contrôleur au voter, au service et à la requête, ainsi que le pare-feu, les écouteurs
et la chaîne de déploiement. Complétée par un scan OWASP ZAP hebdomadaire de la production
(workflow « Audit de sécurité ») et les vérifications de la [check-list de mise en ligne](deploiement.md#13-vérifications-après-la-mise-en-ligne).

Signaler une vulnérabilité : voir [SECURITY.md](../SECURITY.md).

## Problèmes trouvés et corrigés

| Gravité | Problème | Correction | Test |
|---|---|---|---|
| Critique | Le workflow de déploiement, déclenché après la CI, pouvait construire et déployer le code d'une PR venant d'un fork dont la branche s'appelle `main` | Déploiement limité aux `push` sur `main` du dépôt lui-même | — (condition du workflow) |
| Moyenne | Prise de contrôle préalable : un tiers inscrit l'adresse d'une victime avec son propre mot de passe ; la liaison Google/Discord de la victime confirmait ce compte en gardant ce mot de passe | Un compte non confirmé perd son mot de passe quand un fournisseur OAuth prouve l'adresse | `SecurityHardeningTest` |
| Moyenne | Essais de code 2FA remis à zéro à chaque nouvelle connexion (limite par session et par IP) | Plafond supplémentaire par compte (10 codes faux par heure, toutes sessions confondues) ; au-delà, session fermée | `SecurityHardeningTest` |
| Faible | Énumération : « adresse non vérifiée » ou « compte suspendu » répondu même avec un mauvais mot de passe ; inscription plus rapide pour un email existant | État du compte vérifié après le mot de passe ; hachage systématique à l'inscription | `SecurityHardeningTest` |
| Faible | Un président pouvait réactiver un administrateur suspendu | Réactivation d'un administrateur réservée aux administrateurs | `SecurityHardeningTest` |
| Faible | Un membre du bureau sans 2FA accédait à `/api/members` (préfixe `/api/me` trop large) | Correspondance exacte `/api/me` ou `/api/me/…` | `SecurityHardeningTest` |

## Contrôles vérifiés

- **Pare-feu** : seules la connexion, l'inscription, la confirmation d'email, OAuth, la sonde `/api/health`, le flux iCal à jeton et la déconnexion sont publics ; le reste exige une connexion complète (2FA comprise).
- **Double authentification** : obligatoire pour le bureau (fonctions bloquées sans elle), couvre aussi les connexions OAuth ; secret TOTP chiffré en base, jamais en session ; codes de secours à usage unique.
- **Accès aux données (IDOR)** : conversations, messages, notifications, abonnements push, photos et posts contrôlés par des voters et des requêtes limitées à l'utilisateur ; trésorerie, présidence et organisation des événements réservées aux rôles concernés.
- **Sessions** : cookie `__Host-sid` HttpOnly, Secure, SameSite=Lax, stocké dans Redis, renouvelé à la connexion ; déconnexion en POST avec effacement des données du site ; sessions d'un compte suspendu coupées immédiatement ; changement de mot de passe déconnecte les autres sessions.
- **CSRF** : contrôle d'origine sur toute requête modifiant l'état (y compris les envois de fichiers) ; aucun GET à effet de bord hormis le lien signé de confirmation ; paramètre `state` OAuth ; CORS sans identifiants.
- **Injections** : requêtes paramétrées partout ; noms de fichiers téléchargés constants ou validés ; cellules CSV protégées contre les formules ; texte iCal échappé ; aucun `v-html` dans le front ; liens de notification limités aux chemins internes.
- **Fichiers** : taille limitée, type vérifié sur le contenu, nombre de pixels plafonné, réencodage WebP (métadonnées et position GPS supprimées), identifiants aléatoires validés, fichiers servis hors du dossier public avec `nosniff`.
- **Limites de débit** : connexion (5 / 15 min), inscription, renvoi de confirmation, actions sensibles, messages, photos, codes 2FA (par session et par compte).
- **Temps réel (Mercure)** : publications privées, jeton d'abonnement limité aux sujets de l'utilisateur, hub non exposé directement.
- **Production** : mode debug désactivé, aucun outil de développement, en-têtes HSTS / CSP stricte sans script inline / `frame-ancestors 'none'`, secrets obligatoires au démarrage, aucune base exposée sur Internet, image Docker sans fichier local ni secret (vérifié en CI), actions GitHub tierces épinglées par empreinte, sauvegardes chiffrées.
