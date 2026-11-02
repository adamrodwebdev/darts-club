import { parseTokenColor, type StockUnit, type TokenColor } from './EventAccounts'
import { bool, list, num, obj, optStr, str } from './guards'

/** Part cuisine d'une commande : en attente → prête → remise, ou annulée tant qu'elle attend */
export type KitchenStatus = 'en_attente' | 'prete' | 'remise' | 'annulee'

const KITCHEN_STATUSES: readonly KitchenStatus[] = ['en_attente', 'prete', 'remise', 'annulee']
const UNITS: readonly StockUnit[] = ['litre', 'unite', 'portion']

export interface ServiceFormat {
  name: string
  volume: number | null
  /** Décimal « 2.00 » */
  price: string
}

/** Article de la carte tel que le bar et la cuisine le voient : disponible ou épuisé */
export interface ServiceItem {
  id: string
  label: string
  station: 'bar' | 'cuisine'
  unit: StockUnit
  garnishes: readonly { name: string; out: boolean }[]
  /** Épuisé : déclaré par une équipe ou plus de stock */
  soldOut: boolean
  /** Déclaré épuisé par une équipe (le bouton « Remettre » l'annule) */
  markedSoldOut: boolean
  /** Reste en unités de stock ; null tant qu'une contenance manque */
  left: number | null
  formats: readonly ServiceFormat[]
}

export interface OrderLine {
  itemId: string
  label: string
  station: 'bar' | 'cuisine'
  format: string
  price: string
  count: number
  garnishes: readonly string[]
}

export interface EveningOrder {
  id: string
  number: number
  note: string | null
  lines: readonly OrderLine[]
  total: string
  /** null : boissons seules, rien pour la cuisine */
  kitchen: KitchenStatus | null
  takenAt: Date
  takenBy: string | null
  readyAt: Date | null
  deliveredAt: Date | null
  deliveredBy: string | null
}

export interface ServiceToken {
  name: string
  value: string
  color: TokenColor | null
}

export interface ScreenEvent {
  id: string
  title: string
  startsAt: Date
}

const optDate = (value: unknown): Date | null => {
  const text = optStr(value)
  return text === null ? null : new Date(text)
}

function parseEvent(value: unknown): ScreenEvent {
  const d = obj(value, 'soirée')
  return { id: str(d.id), title: str(d.title), startsAt: new Date(str(d.startsAt)) }
}

function parseStation(value: unknown): 'bar' | 'cuisine' {
  return str(value) === 'cuisine' ? 'cuisine' : 'bar'
}

export function parseServiceItem(value: unknown): ServiceItem {
  const d = obj(value, 'article')
  const unit = str(d.unit)
  return {
    id: str(d.id),
    label: str(d.label),
    station: parseStation(d.station),
    unit: UNITS.find((u) => u === unit) ?? 'unite',
    garnishes: list(d.garnishes, (g) => {
      const x = obj(g, 'garniture')
      return { name: str(x.name), out: bool(x.out) }
    }),
    soldOut: bool(d.soldOut),
    markedSoldOut: bool(d.markedSoldOut),
    left: d.left === null ? null : num(d.left),
    formats: list(d.formats, (f) => {
      const x = obj(f, 'format')
      return { name: str(x.name), volume: x.volume === null ? null : num(x.volume), price: str(x.price) }
    }),
  }
}

export function parseEveningOrder(value: unknown): EveningOrder {
  const d = obj(value, 'commande')
  const kitchen = optStr(d.kitchen)
  return {
    id: str(d.id),
    number: num(d.number),
    note: optStr(d.note),
    lines: list(d.lines, (l) => {
      const x = obj(l, 'ligne')
      return { itemId: str(x.itemId), label: str(x.label), station: parseStation(x.station), format: str(x.format), price: str(x.price), count: num(x.count), garnishes: list(x.garnishes, (g) => str(g)) }
    }),
    total: str(d.total),
    kitchen: KITCHEN_STATUSES.find((s) => s === kitchen) ?? null,
    takenAt: new Date(str(d.takenAt)),
    takenBy: optStr(d.takenBy),
    readyAt: optDate(d.readyAt),
    deliveredAt: optDate(d.deliveredAt),
    deliveredBy: optStr(d.deliveredBy),
  }
}

export interface BarScreenData {
  event: ScreenEvent
  nextNumber: number
  tokensEnabled: boolean
  /** Jetons rendus en monnaie au bar (pas les sachets) */
  tokens: readonly ServiceToken[]
  menu: readonly ServiceItem[]
  orders: { waiting: readonly EveningOrder[]; ready: readonly EveningOrder[]; delivered: readonly EveningOrder[] }
}

/** Écran du bar : carte disponible, jetons à encaisser, commandes par étape */
export class BarScreen {
  declare readonly event: ScreenEvent
  declare readonly nextNumber: number
  declare readonly tokensEnabled: boolean
  declare readonly tokens: readonly ServiceToken[]
  declare readonly menu: readonly ServiceItem[]
  declare readonly orders: BarScreenData['orders']

  constructor(data: BarScreenData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): BarScreen {
    const d = obj(value, 'bar')
    const orders = obj(d.orders, 'commandes')
    return new BarScreen({
      event: parseEvent(d.event),
      nextNumber: num(d.nextNumber),
      tokensEnabled: bool(d.tokensEnabled),
      tokens: list(d.tokens, (t) => {
        const x = obj(t, 'jeton')
        return { name: str(x.name), value: str(x.value), color: parseTokenColor(x.color) }
      }),
      menu: list(d.menu, parseServiceItem),
      orders: { waiting: list(orders.waiting, parseEveningOrder), ready: list(orders.ready, parseEveningOrder), delivered: list(orders.delivered, parseEveningOrder) },
    })
  }

  get drinks(): ServiceItem[] {
    return this.menu.filter((item) => item.station === 'bar')
  }

  get food(): ServiceItem[] {
    return this.menu.filter((item) => item.station === 'cuisine')
  }
}

export interface KitchenScreenData {
  event: ScreenEvent
  pending: readonly EveningOrder[]
  sent: readonly EveningOrder[]
  stock: readonly ServiceItem[]
}

/** Écran de la cuisine : file d'attente, plats envoyés, stock à déclarer épuisé */
export class KitchenScreen {
  declare readonly event: ScreenEvent
  declare readonly pending: readonly EveningOrder[]
  declare readonly sent: readonly EveningOrder[]
  declare readonly stock: readonly ServiceItem[]

  constructor(data: KitchenScreenData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): KitchenScreen {
    const d = obj(value, 'cuisine')
    return new KitchenScreen({ event: parseEvent(d.event), pending: list(d.pending, parseEveningOrder), sent: list(d.sent, parseEveningOrder), stock: list(d.stock, parseServiceItem) })
  }
}
