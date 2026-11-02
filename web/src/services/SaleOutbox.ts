import type { SaleDraft } from './EveningDeskService'

/** Vente pas encore reçue par l'API (réseau coupé) : gardée sur l'appareil, renvoyée telle quelle */
export interface PendingSale {
  eventId: string
  sale: SaleDraft
  /** Montant affiché en attendant, décimal « 21.50 » (l'API recalcule) */
  total: string
}

export type KeyValueStore = Pick<Storage, 'getItem' | 'setItem'>

function isPendingSale(value: unknown): value is PendingSale {
  if (typeof value !== 'object' || value === null) return false
  const { eventId, sale, total } = value as Record<string, unknown>
  return typeof eventId === 'string' && typeof total === 'string' && typeof sale === 'object' && sale !== null && typeof (sale as Record<string, unknown>).id === 'string'
}

/**
 * File des ventes de l'accueil en attente d'envoi, gardée dans le stockage de l'appareil : une page
 * rechargée hors ligne ne perd aucune vente. Le même identifiant est renvoyé : jamais de doublon.
 */
export class SaleOutbox {
  constructor(
    private readonly store: KeyValueStore | null,
    private readonly key = 'skull-darts-71.entrance-outbox',
  ) {}

  list(eventId: string): PendingSale[] {
    return this.all().filter((pending) => pending.eventId === eventId)
  }

  add(pending: PendingSale): void {
    this.write([...this.all().filter((p) => p.sale.id !== pending.sale.id), pending])
  }

  remove(saleId: string): void {
    this.write(this.all().filter((p) => p.sale.id !== saleId))
  }

  private all(): PendingSale[] {
    try {
      const stored: unknown = JSON.parse(this.store?.getItem(this.key) ?? '[]')
      return Array.isArray(stored) ? stored.filter(isPendingSale) : []
    } catch {
      return []
    }
  }

  private write(pending: PendingSale[]): void {
    try {
      this.store?.setItem(this.key, JSON.stringify(pending))
    } catch {
      // Stockage plein ou interdit (navigation privée) : la vente reste en mémoire le temps de la page
    }
  }
}
