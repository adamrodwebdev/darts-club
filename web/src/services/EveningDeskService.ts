import type { ApiClient } from '@/api/ApiClient'
import { EntranceScreen, type PaymentMethod } from '@/models/EveningEntrance'
import { BarScreen, KitchenScreen } from '@/models/EveningService'

/** Vente préparée à l'accueil : identifiant tiré sur l'appareil, quantités par nom de jeton */
export interface SaleDraft {
  id: string
  method: PaymentMethod
  lines: { name: string; count: number }[]
}

/** Commande préparée au bar : identifiant tiré sur l'appareil, repère facultatif, lignes de la carte */
export interface OrderDraft {
  id: string
  note: string
  lines: { itemId: string; format: string; count: number; garnishes: string[] }[]
}

const order = (id: string) => `/api/evening-orders/${encodeURIComponent(id)}`
const service = (eventId: string) => `/api/events/${encodeURIComponent(eventId)}/service`

/**
 * Écrans de la soirée en cours : accueil, bar, cuisine. Chaque action renvoie l'écran à jour : prix,
 * stock et argent sont toujours ceux calculés par l'API.
 */
export class EveningDeskService {
  constructor(private readonly api: ApiClient) {}

  async entrance(eventId: string): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.get(`${service(eventId)}/entrance`))
  }

  async sell(eventId: string, sale: SaleDraft): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.post(`${service(eventId)}/sales`, { ...sale }))
  }

  async cancelSale(saleId: string): Promise<EntranceScreen> {
    return EntranceScreen.parse(await this.api.post(`/api/token-sales/${encodeURIComponent(saleId)}/cancel`))
  }

  async bar(eventId: string): Promise<BarScreen> {
    return BarScreen.parse(await this.api.get(`${service(eventId)}/bar`))
  }

  async kitchen(eventId: string): Promise<KitchenScreen> {
    return KitchenScreen.parse(await this.api.get(`${service(eventId)}/kitchen`))
  }

  async order(eventId: string, draft: OrderDraft): Promise<BarScreen> {
    return BarScreen.parse(await this.api.post(`${service(eventId)}/orders`, { ...draft }))
  }

  /** Cuisine : plats prêts, le bar est prévenu */
  async markReady(orderId: string): Promise<KitchenScreen> {
    return KitchenScreen.parse(await this.api.post(`${order(orderId)}/ready`))
  }

  /** Bar : remise au client, la commande se verrouille */
  async deliver(orderId: string): Promise<BarScreen> {
    return BarScreen.parse(await this.api.post(`${order(orderId)}/deliver`))
  }

  async cancelOrder(orderId: string): Promise<BarScreen> {
    return BarScreen.parse(await this.api.post(`${order(orderId)}/cancel`))
  }

  /** Cuisine : article ou garnitures épuisés (ou remis à la carte) */
  async setAvailability(itemId: string, availability: { soldOut: boolean; garnishesOut: string[] }): Promise<KitchenScreen> {
    return KitchenScreen.parse(await this.api.put(`/api/event-accounts/items/${encodeURIComponent(itemId)}/availability`, { ...availability }))
  }
}
