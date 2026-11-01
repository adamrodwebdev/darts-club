import type { ApiClient } from '@/api/ApiClient'
import { EntranceScreen, type PaymentMethod } from '@/models/EveningEntrance'

/** Vente préparée à l'accueil : identifiant tiré sur l'appareil, quantités par nom de jeton */
export interface SaleDraft {
  id: string
  method: PaymentMethod
  lines: { name: string; count: number }[]
}

/**
 * Écrans de la soirée en cours (accueil pour l'instant). Chaque action renvoie l'écran à jour :
 * l'argent encaissé est toujours celui calculé par l'API.
 */
export class EveningDeskService {
  constructor(private readonly api: ApiClient) {}

  async entrance(eventId: string): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.get(`/api/events/${encodeURIComponent(eventId)}/service/entrance`))
  }

  async sell(eventId: string, sale: SaleDraft): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.post(`/api/events/${encodeURIComponent(eventId)}/service/sales`, { ...sale }))
  }

  async cancelSale(saleId: string): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.post(`/api/token-sales/${encodeURIComponent(saleId)}/cancel`))
  }
}
