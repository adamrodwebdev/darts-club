/**
 * Identifiants tirés sur l'appareil (ventes de l'accueil, commandes du bar) : renvoyée après une
 * coupure, une action garde le sien et l'API ne la compte qu'une fois. Injecté : déterministe en test.
 */
export interface IdGenerator {
  next(): string
}

export const randomIds: IdGenerator = { next: () => crypto.randomUUID() }
