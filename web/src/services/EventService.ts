import type { ApiClient } from '@/api/ApiClient'
import { EventSummary, parseEventDetail, type Attendance, type EventDetail } from '@/models/ClubEvent'
import { parseEventAudience, type EventAudience } from '@/models/EventAudience'
import { bool, list, obj, str } from '@/models/guards'

export interface EventDraft {
  title: string
  description: string
  location: string
  /** ISO 8601 */
  startsAt: string
  endsAt: string
  capacity: number | null
  /** Page publique : réponses possibles sans compte */
  public: boolean
  /** Seule indication de lieu montrée au public */
  city: string
}

export interface EventQuery {
  period: 'upcoming' | 'past'
  season?: string
  q?: string
}

/** Calendrier, inscriptions, archives et abonnement iCal. */
export class EventService {
  constructor(private readonly api: ApiClient) {}

  async list(query: EventQuery): Promise<{ items: EventSummary[]; canManage: boolean }> {
    const params = new URLSearchParams({ period: query.period })
    if (query.season) params.set('season', query.season)
    if (query.q) params.set('q', query.q)
    const d = obj(await this.api.get(`/api/events?${params}`))
    return { items: list(d.items, EventSummary.parse), canManage: bool(d.canManage) }
  }

  async get(id: string): Promise<EventDetail> {
    return parseEventDetail(await this.api.get(`/api/events/${encodeURIComponent(id)}`))
  }

  async create(draft: EventDraft): Promise<EventSummary> {
    return EventSummary.parse(await this.api.post('/api/events', { ...draft }))
  }

  async update(id: string, draft: EventDraft): Promise<EventSummary> {
    return EventSummary.parse(await this.api.put(`/api/events/${encodeURIComponent(id)}`, { ...draft }))
  }

  async cancel(id: string, reason: string): Promise<void> {
    await this.api.post(`/api/events/${encodeURIComponent(id)}/cancel`, { reason })
  }

  async writeSummary(id: string, summary: string): Promise<void> {
    await this.api.put(`/api/events/${encodeURIComponent(id)}/summary`, { summary })
  }

  /** Réponse de l'adhérent (ou changement d'avis) */
  async respond(id: string, answer: Attendance): Promise<void> {
    await this.api.post(`/api/events/${encodeURIComponent(id)}/registration`, { answer })
  }

  async unregister(id: string): Promise<void> {
    await this.api.delete(`/api/events/${encodeURIComponent(id)}/registration`)
  }

  /** Vue de l'annonce (mesure d'audience, sans cookie) */
  async recordView(id: string): Promise<void> {
    await this.api.post(`/api/events/${encodeURIComponent(id)}/view`)
  }

  /** Statistiques d'audience : administrateur uniquement */
  async audience(id: string): Promise<EventAudience> {
    return parseEventAudience(await this.api.get(`/api/admin/events/${encodeURIComponent(id)}/audience`))
  }

  audienceCsvUrl(id: string): string {
    return `/api/admin/events/${encodeURIComponent(id)}/audience.csv`
  }

  /** QR code d'une affiche (SVG) : le site, ou la page publique d'une soirée */
  qrUrl(eventId: string | null): string {
    return eventId === null ? '/api/qr/site.svg' : `/api/events/${encodeURIComponent(eventId)}/qr.svg`
  }

  icsUrl(id: string): string {
    return `/api/events/${encodeURIComponent(id)}/calendar.ics`
  }

  /** Pose ou remplace l'affiche (JPEG, PNG ou WebP, 10 Mo au plus). */
  async setPoster(id: string, file: File): Promise<void> {
    const form = new FormData()
    form.append('poster', file)
    await this.api.post(`/api/events/${encodeURIComponent(id)}/poster`, form)
  }

  async removePoster(id: string): Promise<void> {
    await this.api.delete(`/api/events/${encodeURIComponent(id)}/poster`)
  }

  async uploadPhotos(id: string, files: File[]): Promise<void> {
    const form = new FormData()
    files.forEach((file) => form.append('photos[]', file))
    await this.api.post(`/api/events/${encodeURIComponent(id)}/photos`, form)
  }

  async deletePhoto(photoId: string): Promise<void> {
    await this.api.delete(`/api/event-photos/${encodeURIComponent(photoId)}`)
  }

  async addPost(id: string, body: string): Promise<void> {
    await this.api.post(`/api/events/${encodeURIComponent(id)}/posts`, { body })
  }

  async deletePost(postId: string): Promise<void> {
    await this.api.delete(`/api/event-posts/${encodeURIComponent(postId)}`)
  }

  /** Retire un invité sans compte (organisateurs) */
  async removeGuest(id: string, guestId: string): Promise<void> {
    await this.api.delete(`/api/events/${encodeURIComponent(id)}/guests/${encodeURIComponent(guestId)}`)
  }

  /** Nouveau lien d'abonnement (l'ancien est révoqué). */
  async createCalendarFeed(): Promise<{ url: string; webcal: string }> {
    const d = obj(await this.api.post('/api/me/calendar-feed'))
    return { url: str(d.url), webcal: str(d.webcal) }
  }

  async revokeCalendarFeed(): Promise<void> {
    await this.api.delete('/api/me/calendar-feed')
  }
}
