import type { Attendance, EventPoster } from './ClubEvent'
import { parseAttendance, parsePoster } from './ClubEvent'
import { bool, list, num, obj, optStr, str, type Json } from './guards'

/** Événement ouvert au public : ville seulement, compteurs sans noms. */
export interface PublicEvent {
  slug: string
  title: string
  description: string
  city: string
  startsAt: Date
  endsAt: Date
  cancelled: boolean
  cancelReason: string | null
  capacity: number | null
  going: number
  maybe: number
  full: boolean
  started: boolean
  past: boolean
  poster: EventPoster | null
  /** Adresse exacte : seulement pour une réponse confirmée */
  location: string | null
}

export function parsePublicEvent(value: unknown): PublicEvent {
  const d: Json = obj(value, 'événement public')
  return {
    slug: str(d.slug),
    title: str(d.title),
    description: str(d.description),
    city: str(d.city),
    startsAt: new Date(str(d.startsAt)),
    endsAt: new Date(str(d.endsAt)),
    cancelled: str(d.status) === 'annule',
    cancelReason: optStr(d.cancelReason),
    capacity: d.capacity === null ? null : num(d.capacity),
    going: num(d.going),
    maybe: num(d.maybe),
    full: bool(d.full),
    started: bool(d.started),
    past: bool(d.past),
    poster: parsePoster(d.poster),
    location: d.location === undefined ? null : optStr(d.location),
  }
}

/** Rendez-vous ouverts à tous : à venir (du plus proche au plus lointain), puis passés (du plus récent au plus ancien) */
export interface PublicEventTimeline {
  upcoming: PublicEvent[]
  past: PublicEvent[]
}

export function parsePublicEventTimeline(value: unknown): PublicEventTimeline {
  const d = obj(value, 'événements publics')
  return { upcoming: list(d.items, parsePublicEvent), past: list(d.past, parsePublicEvent) }
}

/** Place d'un rendez-vous dans le carrousel de l'accueil */
export type TimelineBadge = 'next' | 'live' | 'upcoming' | 'past' | 'cancelled'

/** Libellé de chaque place (clé de traduction) */
export const TIMELINE_BADGE_LABELS: Record<TimelineBadge, string> = {
  next: 'publicEvents.carousel.badges.next',
  live: 'publicEvents.carousel.badges.live',
  upcoming: 'publicEvents.carousel.badges.upcoming',
  past: 'publicEvents.carousel.badges.past',
  cancelled: 'publicEvents.carousel.badges.cancelled',
}

export interface TimelineSlide {
  event: PublicEvent
  badge: TimelineBadge
}

/**
 * Ordre du carrousel : le prochain rendez-vous d'abord, les suivants, puis les passés
 * du plus récent au plus ancien (ordre déjà fourni par l'API).
 */
export function timelineSlides(timeline: PublicEventTimeline): TimelineSlide[] {
  let nextShown = false
  const upcoming = timeline.upcoming.map((event): TimelineSlide => {
    if (event.cancelled) return { event, badge: 'cancelled' }
    if (event.started) return { event, badge: 'live' }
    const badge: TimelineBadge = nextShown ? 'upcoming' : 'next'
    nextShown = true
    return { event, badge }
  })
  return [...upcoming, ...timeline.past.map((event): TimelineSlide => ({ event, badge: 'past' }))]
}

/** Réponse d'une personne sans compte, lue avec son lien personnel */
export interface GuestResponse {
  name: string
  answer: Attendance | null
  pendingAnswer: Attendance | null
  confirmed: boolean
  expired: boolean
  event: PublicEvent
}

export function parseGuestResponse(value: unknown): GuestResponse {
  const d = obj(value, 'réponse')
  return {
    name: str(d.name),
    answer: d.answer === null ? null : parseAttendance(d.answer),
    pendingAnswer: d.pendingAnswer === null ? null : parseAttendance(d.pendingAnswer),
    confirmed: bool(d.confirmed),
    expired: bool(d.expired),
    event: parsePublicEvent(d.event),
  }
}
