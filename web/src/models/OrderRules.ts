import { toCents } from './CashRules'
import type { ServiceItem, ServiceToken } from './EveningService'

/**
 * Règles du bar et de la cuisine, pures et en centimes : jetons à demander pour un total, minutes
 * d'attente d'une commande, verres restants. L'API reste juge des prix et du stock.
 */

export interface TokenCount {
  token: ServiceToken
  count: number
}

/**
 * Jetons à demander pour un montant, des plus gros aux plus petits (« 21 verts + 1 jaune ») ;
 * `rest` : ce que les jetons ne couvrent pas exactement (prix qui n'est pas un multiple du plus petit).
 */
export function tokensFor(totalCents: number, tokens: readonly ServiceToken[]): { counts: TokenCount[]; rest: number } {
  let rest = totalCents
  const counts: TokenCount[] = []
  for (const token of [...tokens].sort((a, b) => toCents(b.value) - toCents(a.value))) {
    const value = toCents(token.value)
    if (value <= 0) continue
    const count = Math.floor(rest / value)
    if (count > 0) {
      counts.push({ token, count })
      rest -= count * value
    }
  }
  return { counts, rest }
}

/** Minutes entières écoulées depuis la prise de la commande (horloge injectée) */
export function waitMinutes(takenAt: Date, now: Date): number {
  return Math.max(0, Math.floor((now.getTime() - takenAt.getTime()) / 60_000))
}

/** Au-delà, une commande en cuisine est signalée en retard */
export const LATE_AFTER_MINUTES = 8

/** Combien de premiers formats (verres) le reste permet encore ; null si inconnu */
export function servingsLeft(item: ServiceItem): number | null {
  const first = item.formats[0]
  if (item.left === null || !first?.volume) return null
  return Math.floor(item.left / first.volume)
}
