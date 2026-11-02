import type { ItemSales } from './LiveIncome'

/**
 * Règles d'affichage des recettes en direct, pures : ce qu'un poste a vendu en peu de mots, la part
 * du stock qui reste, les verres qu'il permet encore. L'API reste seule à compter.
 */

/** Sous cette part du stock, le reste d'un article est signalé */
export const LOW_STOCK_SHARE = 0.3

export interface SoldCount {
  count: number
  name: string
}

/**
 * Ventes d'un poste dans l'ordre de la carte (« 124 × Verre · 30 × Eau ») : un article à un seul
 * format porte son nom, sinon chaque format porte le sien.
 */
export function salesSummary(items: readonly ItemSales[]): SoldCount[] {
  return items.flatMap((item) =>
    item.formats.filter((format) => format.count > 0).map((format) => ({ count: format.count, name: item.formats.length === 1 ? item.label : format.name })),
  )
}

/** Part du stock qui reste, de 0 à 1 ; null si le stock ou le reste est inconnu */
export function leftShare(item: ItemSales): number | null {
  if (item.left === null || item.stock === null || item.stock <= 0) return null
  return Math.min(1, Math.max(0, item.left / item.stock))
}

/** Combien du plus petit format (le verre) le reste permet encore ; null si une contenance manque */
export function servingsLeft(item: ItemSales): SoldCount | null {
  if (item.left === null) return null
  const smallest = item.formats.reduce<{ name: string; volume: number } | null>(
    (best, format) => (format.volume !== null && format.volume > 0 && (best === null || format.volume < best.volume) ? { name: format.name, volume: format.volume } : best),
    null,
  )
  return smallest === null ? null : { count: Math.floor(Math.max(0, item.left) / smallest.volume), name: smallest.name }
}
