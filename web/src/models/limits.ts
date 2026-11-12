/**
 * Longueurs maximales des champs de saisie, en caractères (règle du 2026-11-09). Ce sont celles
 * de l'API : ses contraintes Length et les constantes du domaine nommées en regard. L'attribut
 * maxlength aide la saisie ; la vérification qui protège reste celle de l'API.
 */
export const TEXT_LIMITS = {
  /** Money::DECIMAL_TEXT_MAX : « 999 999,99 » */
  amount: 12,
  /** Une quantité de stock ou de ventes (ItemQuantities::MAX = 100 000, litres décimaux compris) */
  quantity: 10,
  /** ItemServing : 8 garnitures de 40 caractères au plus, séparées par des virgules */
  garnishes: 340,
  /** ArchiveSearch::QUERY_MAX : une recherche */
  search: 80,
  /** Un numéro de téléphone tel qu'on l'écrit */
  phone: 30,
  /** EmailAddress::MAX */
  email: 180,
  /** PasswordPolicy : Length(max: 4096) */
  password: 4096,
  /** AccountDeletion : le mot SUPPRIMER */
  confirmation: 20,
  /** EventDetails : de 1 à 1000 places */
  capacity: 4,
  /** PublicEventController::HONEYPOT_MAX : le champ piège des robots */
  honeypot: 500,
  /** PressDetails::OUTLET_MAX : le journal d'une coupure de presse */
  pressOutlet: 80,
  /** PressDetails::TITLE_MAX : le titre de l'article */
  pressTitle: 200,
  /** PressDetails::CREDIT_MAX : « Texte et photo : … » */
  pressCredit: 120,
} as const
