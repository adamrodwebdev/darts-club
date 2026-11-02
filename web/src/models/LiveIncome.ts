import { parseTokenColor, type Station, type StockUnit, type TokenColor } from './EventAccounts'
import { parseScreenEvent, type ScreenEvent } from './EveningService'
import { bool, list, num, obj, str } from './guards'

const UNITS: readonly StockUnit[] = ['litre', 'unite', 'portion']

/** Jetons ou sachets d'une sorte vendus ce soir à l'accueil */
export interface TokenTally {
  name: string
  value: string
  bag: boolean
  color: TokenColor | null
  count: number
  amount: string
}

/** Ventes d'un format ce soir, aux prix figés dans les commandes */
export interface FormatSales {
  name: string
  /** Contenance à la carte ; null si inconnue, ou format retiré de la carte */
  volume: number | null
  count: number
  amount: string
}

/** Ventes et reste d'un article */
export interface ItemSales {
  id: string
  label: string
  station: Station
  /** null : article retiré de la carte après ses ventes */
  unit: StockUnit | null
  formats: readonly FormatSales[]
  amount: string
  /** Servi en unités de stock ; null si une contenance manque */
  served: number | null
  /** Stock de la soirée en unités de stock ; null hors de la carte */
  stock: number | null
  /** Reste en unités de stock ; null tant qu'une contenance manque */
  left: number | null
}

/** Ce qu'un poste a servi : valeur des jetons dépensés, nombre d'articles */
export interface StationIncome {
  amount: string
  served: number
}

export interface EntranceIncome {
  cash: string
  card: string
  total: string
  cashFloat: string
  /** Fond de caisse et espèces reçues */
  expectedCash: string
  tokens: readonly TokenTally[]
}

export interface OrderTally {
  count: number
  waiting: number
  ready: number
  cancelled: number
}

export interface LiveIncomeData {
  event: ScreenEvent
  tokensEnabled: boolean
  entrance: EntranceIncome
  bar: StationIncome
  kitchen: StationIncome
  spent: string
  /** Signé : négatif si des jetons d'une autre soirée ont servi */
  circulation: string
  orders: OrderTally
  items: readonly ItemSales[]
}

function parseStation(value: unknown): Station {
  return str(value) === 'cuisine' ? 'cuisine' : 'bar'
}

function parseStationIncome(value: unknown): StationIncome {
  const d = obj(value, 'poste')
  return { amount: str(d.amount), served: num(d.served) }
}

const optNum = (value: unknown): number | null => (value === null ? null : num(value))

function parseItem(value: unknown): ItemSales {
  const d = obj(value, 'article')
  const unit = d.unit === null ? null : str(d.unit)
  return {
    id: str(d.id),
    label: str(d.label),
    station: parseStation(d.station),
    unit: unit === null ? null : (UNITS.find((u) => u === unit) ?? 'unite'),
    formats: list(d.formats, (f) => {
      const x = obj(f, 'format')
      return { name: str(x.name), volume: optNum(x.volume), count: num(x.count), amount: str(x.amount) }
    }),
    amount: str(d.amount),
    served: optNum(d.served),
    stock: optNum(d.stock),
    left: optNum(d.left),
  }
}

/** Recettes en direct d'une soirée (bureau) : l'API calcule tout, le site affiche. */
export class LiveIncome {
  declare readonly event: ScreenEvent
  declare readonly tokensEnabled: boolean
  declare readonly entrance: EntranceIncome
  declare readonly bar: StationIncome
  declare readonly kitchen: StationIncome
  declare readonly spent: string
  declare readonly circulation: string
  declare readonly orders: OrderTally
  declare readonly items: readonly ItemSales[]

  constructor(data: LiveIncomeData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): LiveIncome {
    const d = obj(value, 'recettes')
    const entrance = obj(d.entrance, 'accueil')
    const orders = obj(d.orders, 'commandes')
    return new LiveIncome({
      event: parseScreenEvent(d.event),
      tokensEnabled: bool(d.tokensEnabled),
      entrance: {
        cash: str(entrance.cash),
        card: str(entrance.card),
        total: str(entrance.total),
        cashFloat: str(entrance.cashFloat),
        expectedCash: str(entrance.expectedCash),
        tokens: list(entrance.tokens, (t) => {
          const x = obj(t, 'jeton')
          return { name: str(x.name), value: str(x.value), bag: bool(x.bag), color: parseTokenColor(x.color), count: num(x.count), amount: str(x.amount) }
        }),
      },
      bar: parseStationIncome(d.bar),
      kitchen: parseStationIncome(d.kitchen),
      spent: str(d.spent),
      circulation: str(d.circulation),
      orders: { count: num(orders.count), waiting: num(orders.waiting), ready: num(orders.ready), cancelled: num(orders.cancelled) },
      items: list(d.items, parseItem),
    })
  }

  /** Articles d'un poste, dans l'ordre de la carte */
  itemsAt(station: Station): ItemSales[] {
    return this.items.filter((item) => item.station === station)
  }
}
