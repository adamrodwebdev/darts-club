import { bool, list, num, obj, optStr, str } from './guards'

/** Article de la buvette, avec ses montants calculés par l'API (seule source des totaux) */
export interface BarItem {
  id: string
  label: string
  planned: number
  bought: number
  /** Retourné au fournisseur, remboursé */
  returned: number
  sold: number
  /** Gardé pour une autre soirée, ou offert */
  left: number
  /** Montants décimaux « 0.95 » */
  purchasePrice: string
  salePrice: string
  cost: string
  income: string
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
  barIncome: string
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
  year: number
  temporaryBars: YearCounter
  taxExemptEvents: YearCounter
  /** Ce que la personne connectée peut faire (l'API reste juge) */
  mayEdit: boolean
  mayReopen: boolean
}

function parseItem(value: unknown): BarItem {
  const d = obj(value, 'article')
  return {
    id: str(d.id),
    label: str(d.label),
    planned: num(d.planned),
    bought: num(d.bought),
    returned: num(d.returned),
    sold: num(d.sold),
    left: num(d.left),
    purchasePrice: str(d.purchasePrice),
    salePrice: str(d.salePrice),
    cost: str(d.cost),
    income: str(d.income),
  }
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
  declare readonly year: number
  declare readonly temporaryBars: YearCounter
  declare readonly taxExemptEvents: YearCounter
  declare readonly mayEdit: boolean
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
      year: num(counters.year),
      temporaryBars: parseCounter(counters.temporaryBars),
      taxExemptEvents: parseCounter(counters.taxExempt),
      mayEdit: bool(permissions.edit),
      mayReopen: bool(permissions.reopen),
    })
  }

  /** Saisie possible : droit de modifier et bilan ouvert */
  get editable(): boolean {
    return this.mayEdit && !this.closed
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
