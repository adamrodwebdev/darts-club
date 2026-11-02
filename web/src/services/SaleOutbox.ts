import { DeviceOutbox, isPending, type KeyValueStore } from './DeviceOutbox'
import type { OrderDraft, SaleDraft } from './EveningDeskService'

export type { KeyValueStore } from './DeviceOutbox'

/** Vente de l'accueil pas encore reçue par l'API (réseau coupé) */
export interface PendingSale {
  eventId: string
  sale: SaleDraft
  /** Montant affiché en attendant, décimal « 21.50 » (l'API recalcule) */
  total: string
}

/** Commande du bar pas encore reçue par l'API (réseau coupé) */
export interface PendingOrder {
  eventId: string
  order: OrderDraft
  total: string
}

/** Ventes de l'accueil en attente d'envoi */
export class SaleOutbox extends DeviceOutbox<PendingSale> {
  constructor(store: KeyValueStore | null) {
    super(store, 'skull-darts-71.entrance-outbox', {
      id: (pending) => pending.sale.id,
      eventId: (pending) => pending.eventId,
      accepts: (value): value is PendingSale => isPending(value, 'sale'),
    })
  }
}

/** Commandes du bar en attente d'envoi */
export class OrderOutbox extends DeviceOutbox<PendingOrder> {
  constructor(store: KeyValueStore | null) {
    super(store, 'skull-darts-71.bar-outbox', {
      id: (pending) => pending.order.id,
      eventId: (pending) => pending.eventId,
      accepts: (value): value is PendingOrder => isPending(value, 'order'),
    })
  }
}
