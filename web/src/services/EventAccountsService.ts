import type { ApiClient } from '@/api/ApiClient'
import { EventAccounts, type EntryKind, type Station, type StockUnit, type Team, type TokenColor } from '@/models/EventAccounts'

/** Format vendu saisi : contenance en unité de stock (centilitres au litre), null si inconnue */
export interface SaleFormatDraft {
  name: string
  volume: number | null
  price: string
  sold: number
}

/**
 * Article de la carte saisi dans le formulaire : prix décimaux « 0,95 », quantités en contenants,
 * contenance du contenant en unité de stock.
 */
export interface BarItemDraft {
  label: string
  station: Station
  unit: StockUnit
  garnishes: string[]
  alcoholic: boolean
  containerName: string | null
  containerSize: number
  planned: number
  bought: number
  returned: number
  purchasePrice: string
  formats: SaleFormatDraft[]
}

/** Jeton ou sachet vendu à l'accueil, valeur décimale « 0,50 » ; couleur du jeton (aucune pour un sachet) */
export interface TokenDraft {
  name: string
  value: string
  bag: boolean
  color: TokenColor | null
}

/** Encaissement de la soirée : jetons et fond de caisse de l'accueil */
export interface CashDraft {
  tokensEnabled: boolean
  tokens: TokenDraft[]
  cashFloat: string
}

/** Dépense ou recette hors buvette ; réel vide tant qu'il n'est pas connu */
export interface EntryDraft {
  kind: EntryKind
  label: string
  planned: string
  actual: string | null
}

export interface ReceiptDraft {
  file: File
  label: string
  /** Facultatif, « 12,50 » */
  amount: string
  /** Avoir (retour fournisseur remboursé) */
  credit: boolean
}

/**
 * Bilan d'une soirée (bureau). Chaque modification renvoie le bilan à jour, totaux compris :
 * l'écran ne les recalcule jamais.
 */
export class EventAccountsService {
  constructor(private readonly api: ApiClient) {}

  async get(eventId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.get(this.base(eventId)))
  }

  async addItem(eventId: string, draft: BarItemDraft): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.post(`${this.base(eventId)}/items`, { ...draft }))
  }

  async changeItem(itemId: string, draft: BarItemDraft): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.put(`/api/event-accounts/items/${encodeURIComponent(itemId)}`, { ...draft }))
  }

  async removeItem(itemId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.delete(`/api/event-accounts/items/${encodeURIComponent(itemId)}`))
  }

  async addEntry(eventId: string, draft: EntryDraft): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.post(`${this.base(eventId)}/entries`, { ...draft }))
  }

  async changeEntry(entryId: string, draft: EntryDraft): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.put(`/api/event-accounts/entries/${encodeURIComponent(entryId)}`, { ...draft }))
  }

  async removeEntry(entryId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.delete(`/api/event-accounts/entries/${encodeURIComponent(entryId)}`))
  }

  async saveCash(eventId: string, draft: CashDraft): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.put(`${this.base(eventId)}/cash`, { ...draft }))
  }

  /** Place un membre dans l'équipe de l'accueil, du bar ou de la cuisine */
  async addTeamMember(eventId: string, assignment: { userId: string; team: Team }): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.post(`/api/events/${encodeURIComponent(eventId)}/teams`, { ...assignment }))
  }

  async removeTeamMember(assignmentId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.delete(`/api/evening-team-members/${encodeURIComponent(assignmentId)}`))
  }

  async changeFlags(eventId: string, flags: { temporaryBar: boolean; taxExempt: boolean }): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.put(`${this.base(eventId)}/flags`, { ...flags }))
  }

  async addReceipt(eventId: string, draft: ReceiptDraft): Promise<EventAccounts> {
    const form = new FormData()
    form.append('file', draft.file)
    form.append('label', draft.label)
    form.append('amount', draft.amount)
    form.append('credit', draft.credit ? '1' : '0')
    return EventAccounts.parse(await this.api.post(`${this.base(eventId)}/receipts`, form))
  }

  async removeReceipt(receiptId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.delete(`/api/event-accounts/receipts/${encodeURIComponent(receiptId)}`))
  }

  async close(eventId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.post(`${this.base(eventId)}/close`))
  }

  async reopen(eventId: string): Promise<EventAccounts> {
    return EventAccounts.parse(await this.api.post(`${this.base(eventId)}/reopen`))
  }

  /** Photo affichée, PDF téléchargé */
  receiptUrl(receiptId: string): string {
    return `/api/event-accounts/receipts/${encodeURIComponent(receiptId)}`
  }

  exportUrl(eventId: string): string {
    return `${this.base(eventId)}/export.csv`
  }

  private base(eventId: string): string {
    return `/api/events/${encodeURIComponent(eventId)}/accounts`
  }
}
