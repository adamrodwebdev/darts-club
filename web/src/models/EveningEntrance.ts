import { parseTokenColor, parseTokenKind, type TokenColor, type TokenKind } from './EventAccounts'
import { bool, list, num, obj, optStr, str } from './guards'

/** Moyen de paiement à l'accueil, seul endroit où l'argent change de mains */
export type PaymentMethod = 'especes' | 'carte'

export interface SaleLine {
  name: string
  /** Valeur figée au moment de la vente, décimale « 10.00 » */
  value: string
  bag: boolean
  color: TokenColor | null
  count: number
}

/** Vente de l'accueil ; une vente annulée reste, barrée */
export interface TokenSale {
  id: string
  lines: readonly SaleLine[]
  total: string
  method: PaymentMethod
  soldAt: Date
  soldBy: string | null
  cancelled: boolean
  cancelledBy: string | null
}

export function parseTokenSale(value: unknown): TokenSale {
  const d = obj(value, 'vente')
  return {
    id: str(d.id),
    lines: list(d.lines, (l) => {
      const x = obj(l, 'ligne')
      return { name: str(x.name), value: str(x.value), bag: bool(x.bag), color: parseTokenColor(x.color), count: num(x.count) }
    }),
    total: str(d.total),
    method: str(d.method) === 'carte' ? 'carte' : 'especes',
    soldAt: new Date(str(d.soldAt)),
    soldBy: optStr(d.soldBy),
    cancelled: bool(d.cancelled),
    cancelledBy: optStr(d.cancelledBy),
  }
}

export interface EntranceScreenData {
  eventId: string
  eventTitle: string
  eventStartsAt: Date
  tokensEnabled: boolean
  tokens: readonly TokenKind[]
  cashFloat: string
  sales: readonly TokenSale[]
  /** Argent encaissé ce soir, ventes annulées déduites (calculé par l'API) */
  taken: { cash: string; card: string }
}

/** Écran de l'accueil d'une soirée : jetons en vente, dernières ventes, caisse */
export class EntranceScreen {
  declare readonly eventId: string
  declare readonly eventTitle: string
  declare readonly eventStartsAt: Date
  declare readonly tokensEnabled: boolean
  declare readonly tokens: readonly TokenKind[]
  declare readonly cashFloat: string
  declare readonly sales: readonly TokenSale[]
  declare readonly taken: { cash: string; card: string }

  constructor(data: EntranceScreenData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): EntranceScreen {
    const d = obj(value, 'accueil')
    const event = obj(d.event, 'soirée')
    const taken = obj(d.taken, 'caisse')
    return new EntranceScreen({
      eventId: str(event.id),
      eventTitle: str(event.title),
      eventStartsAt: new Date(str(event.startsAt)),
      tokensEnabled: bool(d.tokensEnabled),
      tokens: list(d.tokens, parseTokenKind),
      cashFloat: str(d.cashFloat),
      sales: list(d.sales, parseTokenSale),
      taken: { cash: str(taken.cash), card: str(taken.card) },
    })
  }
}
