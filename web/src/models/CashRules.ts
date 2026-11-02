import type { TokenKind } from './EventAccounts'

/**
 * Règles de caisse de l'accueil, en centimes entiers (jamais de virgule flottante sur l'argent).
 * L'appareil s'en sert pour l'affichage, hors ligne compris ; l'API recalcule chaque vente.
 */

/** « 10.00 » → 1 000 */
export function toCents(decimal: string): number {
  return Math.round(Number(decimal) * 100)
}

/** Montant d'une vente d'après les quantités choisies par nom de jeton */
export function saleCents(tokens: readonly TokenKind[], counts: Readonly<Record<string, number>>): number {
  return tokens.reduce((sum, token) => sum + toCents(token.value) * (counts[token.name] ?? 0), 0)
}

/** Monnaie à rendre sur un billet ; null quand le billet ne suffit pas */
export function changeCents(given: number, total: number): number | null {
  return given >= total ? given - total : null
}

/** Billets que l'accueil voit passer le plus souvent, en centimes */
export const BILLS: readonly number[] = [500, 1000, 2000, 5000]
