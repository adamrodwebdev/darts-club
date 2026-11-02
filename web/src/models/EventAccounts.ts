import { parseMember, type Member } from './Conversation'
import { bool, list, num, obj, optStr, str, strList } from './guards'

/** Qui sert un article : les boissons restent au bar, les plats partent en cuisine */
export type Station = 'bar' | 'cuisine'

/** Unité de stock : litre (compté en centilitres), unité, portion */
export type StockUnit = 'litre' | 'unite' | 'portion'

/** Format vendu : verre, pichet, bouteille, portion ; contenance facultative, en unité de stock */
export interface SaleFormat {
  name: string
  /** Centilitres pour un article au litre, 1 pour une unité ou une portion ; null si inconnue */
  volume: number | null
  /** Montant décimal « 2.00 » */
  price: string
  sold: number
}

/** Article de la carte, avec ses montants calculés par l'API (seule source des totaux) */
export interface BarItem {
  id: string
  label: string
  station: Station
  unit: StockUnit
  garnishes: readonly string[]
  alcoholic: boolean
  /** Contenant acheté (fût, pack) et sa contenance en unité de stock (3000 cl pour 30 L) */
  container: { name: string | null; size: number }
  /** En contenants */
  planned: number
  bought: number
  /** Retourné au fournisseur, remboursé */
  returned: number
  /** Prix d'un contenant, décimal « 95.00 » */
  purchasePrice: string
  formats: readonly SaleFormat[]
  /** Stock en unités de stock (centilitres, unités, portions) */
  stock: number
  /** Servi en unités de stock ; null tant qu'une contenance de format manque */
  served: number | null
  /** Ventes, tous formats confondus */
  soldCount: number
  /** Reste en unités de stock ; null tant qu'une contenance de format manque */
  left: number | null
  cost: string
  income: string
}

/** Couleur d'un jeton, reconnue d'un coup d'œil à l'accueil (les sachets n'en ont pas) */
export type TokenColor = 'vert' | 'jaune' | 'rouge' | 'bleu' | 'violet' | 'orange' | 'blanc' | 'noir'

export const TOKEN_COLORS: readonly TokenColor[] = ['vert', 'jaune', 'rouge', 'bleu', 'violet', 'orange', 'blanc', 'noir']

/** Jeton ou sachet vendu à l'accueil */
export interface TokenKind {
  name: string
  /** Montant décimal « 0.50 » */
  value: string
  bag: boolean
  color: TokenColor | null
}

export interface CashSettings {
  tokensEnabled: boolean
  tokens: readonly TokenKind[]
  /** Fond de caisse de l'accueil, décimal */
  cashFloat: string
  /** Figés par la première vente de l'accueil : seuls le fond de caisse et les couleurs changent encore */
  frozen: boolean
}

export function parseTokenColor(value: unknown): TokenColor | null {
  return TOKEN_COLORS.find((color) => color === value) ?? null
}

export function parseTokenKind(value: unknown): TokenKind {
  const x = obj(value, 'jeton')
  return { name: str(x.name), value: str(x.value), bag: bool(x.bag), color: parseTokenColor(x.color) }
}

export type Team = 'accueil' | 'bar' | 'cuisine'

export const TEAMS: readonly Team[] = ['accueil', 'bar', 'cuisine']

/** Membre placé dans une équipe de la soirée */
export interface TeamMember extends Member {
  assignmentId: string
}

export type EntryKind = 'depense' | 'recette'

export interface AccountsEntry {
  id: string
  kind: EntryKind
  label: string
  planned: string
  /** Inconnu tant que la soirée n'a pas eu lieu */
  actual: string | null
}

export interface Receipt {
  id: string
  label: string
  amount: string | null
  /** Avoir : montant à déduire */
  credit: boolean
  type: 'image' | 'pdf'
  uploadedBy: string | null
  uploadedAt: Date
}

export interface AccountsTotals {
  barCosts: string
  /** Buvette comptée au résultat : l'argent de l'accueil quand des jetons y ont été vendus, sinon les ventes de la carte */
  barIncome: string
  barIncomeSource: 'entrance' | 'menu'
  /** Ventes de la carte (vendu × prix), pour comparer */
  barSales: string
  costs: string
  income: string
  /** Signé : négatif en cas de perte */
  result: string
  plannedCosts: string
  plannedIncome: string
  plannedResult: string
}

/** Limite annuelle et ce qui en est déjà utilisé */
export interface YearCounter {
  used: number
  limit: number
}

export interface EventAccountsData {
  eventId: string
  eventTitle: string
  eventStartsAt: Date
  closed: boolean
  closedAt: Date | null
  closedBy: string | null
  temporaryBar: boolean
  taxExempt: boolean
  items: readonly BarItem[]
  entries: readonly AccountsEntry[]
  receipts: readonly Receipt[]
  totals: AccountsTotals
  cash: CashSettings
  teams: Readonly<Record<Team, readonly TeamMember[]>>
  year: number
  temporaryBars: YearCounter
  taxExemptEvents: YearCounter
  /** Ce que la personne connectée peut faire (l'API reste juge) */
  mayEdit: boolean
  /** Carte, encaissement et équipes : tout le bureau et l'administrateur */
  mayEditMenu: boolean
  mayReopen: boolean
}

const STATIONS: readonly Station[] = ['bar', 'cuisine']
const UNITS: readonly StockUnit[] = ['litre', 'unite', 'portion']

function parseFormat(value: unknown): SaleFormat {
  const d = obj(value, 'format')
  return { name: str(d.name), volume: d.volume === null ? null : num(d.volume), price: str(d.price), sold: num(d.sold) }
}

function parseItem(value: unknown): BarItem {
  const d = obj(value, 'article')
  const container = obj(d.container, 'contenant')
  const station = str(d.station)
  const unit = str(d.unit)
  return {
    id: str(d.id),
    label: str(d.label),
    station: STATIONS.find((s) => s === station) ?? 'bar',
    unit: UNITS.find((u) => u === unit) ?? 'unite',
    garnishes: strList(d.garnishes),
    alcoholic: bool(d.alcoholic),
    container: { name: optStr(container.name), size: num(container.size) },
    planned: num(d.planned),
    bought: num(d.bought),
    returned: num(d.returned),
    purchasePrice: str(d.purchasePrice),
    formats: list(d.formats, parseFormat),
    stock: num(d.stock),
    served: d.served === null ? null : num(d.served),
    soldCount: num(d.soldCount),
    left: d.left === null ? null : num(d.left),
    cost: str(d.cost),
    income: str(d.income),
  }
}

/** API plus ancienne, sans jetons ni équipes : encaissement direct, aucune équipe */
function parseCash(value: unknown): CashSettings {
  if (value === undefined) return { tokensEnabled: false, tokens: [], cashFloat: '0.00', frozen: false }
  const d = obj(value, 'encaissement')
  return {
    tokensEnabled: bool(d.tokensEnabled),
    tokens: list(d.tokens, parseTokenKind),
    cashFloat: str(d.cashFloat),
    frozen: d.frozen === true,
  }
}

function parseTeams(value: unknown): Record<Team, TeamMember[]> {
  const d = value === undefined ? {} : obj(value, 'équipes')
  const members = (team: Team): TeamMember[] =>
    d[team] === undefined ? [] : list(d[team], (m) => ({ ...parseMember(m), assignmentId: str(obj(m).assignmentId) }))
  return { accueil: members('accueil'), bar: members('bar'), cuisine: members('cuisine') }
}

function parseEntry(value: unknown): AccountsEntry {
  const d = obj(value, 'ligne du bilan')
  const kind = str(d.kind)
  return {
    id: str(d.id),
    kind: kind === 'recette' ? 'recette' : 'depense',
    label: str(d.label),
    planned: str(d.planned),
    actual: optStr(d.actual),
  }
}

function parseReceipt(value: unknown): Receipt {
  const d = obj(value, 'justificatif')
  return {
    id: str(d.id),
    label: str(d.label),
    amount: optStr(d.amount),
    credit: bool(d.credit),
    type: str(d.type) === 'pdf' ? 'pdf' : 'image',
    uploadedBy: optStr(d.uploadedBy),
    uploadedAt: new Date(str(d.uploadedAt)),
  }
}

function parseCounter(value: unknown): YearCounter {
  const d = obj(value, 'compteur')
  return { used: num(d.used), limit: num(d.limit) }
}

function parseTotals(value: unknown): AccountsTotals {
  const d = obj(value, 'totaux')
  return {
    barCosts: str(d.barCosts),
    barIncome: str(d.barIncome),
    barIncomeSource: d.barIncomeSource === 'entrance' ? 'entrance' : 'menu',
    barSales: str(d.barSales),
    costs: str(d.costs),
    income: str(d.income),
    result: str(d.result),
    plannedCosts: str(d.plannedCosts),
    plannedIncome: str(d.plannedIncome),
    plannedResult: str(d.plannedResult),
  }
}

/** Bilan d'une soirée (bureau). */
export class EventAccounts {
  declare readonly eventId: string
  declare readonly eventTitle: string
  declare readonly eventStartsAt: Date
  declare readonly closed: boolean
  declare readonly closedAt: Date | null
  declare readonly closedBy: string | null
  declare readonly temporaryBar: boolean
  declare readonly taxExempt: boolean
  declare readonly items: readonly BarItem[]
  declare readonly entries: readonly AccountsEntry[]
  declare readonly receipts: readonly Receipt[]
  declare readonly totals: AccountsTotals
  declare readonly cash: CashSettings
  declare readonly teams: Readonly<Record<Team, readonly TeamMember[]>>
  declare readonly year: number
  declare readonly temporaryBars: YearCounter
  declare readonly taxExemptEvents: YearCounter
  declare readonly mayEdit: boolean
  declare readonly mayEditMenu: boolean
  declare readonly mayReopen: boolean

  constructor(data: EventAccountsData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): EventAccounts {
    const d = obj(value, 'bilan')
    const event = obj(d.event, 'soirée')
    const flags = obj(d.flags, 'compteurs')
    const counters = obj(d.counters, 'compteurs')
    const permissions = obj(d.permissions, 'droits')
    const closedAt = optStr(d.closedAt)
    return new EventAccounts({
      eventId: str(event.id),
      eventTitle: str(event.title),
      eventStartsAt: new Date(str(event.startsAt)),
      closed: bool(d.closed),
      closedAt: closedAt === null ? null : new Date(closedAt),
      closedBy: optStr(d.closedBy),
      temporaryBar: bool(flags.temporaryBar),
      taxExempt: bool(flags.taxExempt),
      items: list(d.items, parseItem),
      entries: list(d.entries, parseEntry),
      receipts: list(d.receipts, parseReceipt),
      totals: parseTotals(d.totals),
      cash: parseCash(d.cash),
      teams: parseTeams(d.teams),
      year: num(counters.year),
      temporaryBars: parseCounter(counters.temporaryBars),
      taxExemptEvents: parseCounter(counters.taxExempt),
      mayEdit: bool(permissions.edit),
      mayEditMenu: permissions.editMenu === true,
      mayReopen: bool(permissions.reopen),
    })
  }

  /** Saisie possible : droit de modifier et bilan ouvert */
  get editable(): boolean {
    return this.mayEdit && !this.closed
  }

  /** Carte et encaissement modifiables : tout le bureau, bilan ouvert */
  get menuEditable(): boolean {
    return this.mayEditMenu && !this.closed
  }

  get expenses(): AccountsEntry[] {
    return this.entries.filter((e) => e.kind === 'depense')
  }

  get incomes(): AccountsEntry[] {
    return this.entries.filter((e) => e.kind === 'recette')
  }

  /** Résultat en perte (affichage) */
  get isLoss(): boolean {
    return this.totals.result.startsWith('-')
  }
}
