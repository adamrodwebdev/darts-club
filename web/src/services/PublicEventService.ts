import type { ApiClient } from '@/api/ApiClient'
import type { Attendance } from '@/models/ClubEvent'
import { obj, str } from '@/models/guards'
import {
  parseGuestResponse,
  parsePublicEvent,
  parsePublicEventTimeline,
  type GuestResponse,
  type PublicEvent,
  type PublicEventTimeline,
} from '@/models/PublicEvent'

export interface GuestAnswer {
  name: string
  email: string
  answer: Attendance
  consent: boolean
  /** Champ piège laissé vide par les humains */
  website: string
}

/**
 * Événements ouverts au public et réponses sans compte. Le jeton du lien
 * personnel voyage dans le corps des requêtes, jamais dans l'URL.
 */
export class PublicEventService {
  constructor(private readonly api: ApiClient) {}

  /** Rendez-vous ouverts à tous, à venir et passés (sans compte) */
  async timeline(): Promise<PublicEventTimeline> {
    return parsePublicEventTimeline(await this.api.get('/api/public/events'))
  }

  /** Événements publics à venir (sans compte) */
  async list(): Promise<PublicEvent[]> {
    return (await this.timeline()).upcoming
  }

  /** Adresse publique d'un événement à partir de son identifiant (lien adhérent partagé) */
  async slugFor(id: string): Promise<string> {
    return str(obj(await this.api.get(`/api/public/events/by-id/${encodeURIComponent(id)}`)).slug)
  }

  async get(slug: string): Promise<PublicEvent> {
    return parsePublicEvent(await this.api.get(`/api/public/events/${encodeURIComponent(slug)}`))
  }

  /** Envoie le lien de confirmation par email (réponse identique dans tous les cas) */
  async answer(slug: string, answer: GuestAnswer): Promise<void> {
    await this.api.post(`/api/public/events/${encodeURIComponent(slug)}/responses`, { ...answer })
  }

  /** Vue de l'annonce publique (mesure d'audience, sans cookie) */
  async recordView(slug: string): Promise<void> {
    await this.api.post(`/api/public/events/${encodeURIComponent(slug)}/view`)
  }

  async lookup(token: string): Promise<GuestResponse> {
    return parseGuestResponse(await this.api.post('/api/public/responses/lookup', { token }))
  }

  async confirm(token: string): Promise<GuestResponse> {
    return parseGuestResponse(await this.api.post('/api/public/responses/confirm', { token }))
  }

  async change(token: string, answer: Attendance): Promise<GuestResponse> {
    return parseGuestResponse(await this.api.post('/api/public/responses/answer', { token, answer }))
  }

  async cancel(token: string): Promise<void> {
    await this.api.post('/api/public/responses/cancel', { token })
  }
}
