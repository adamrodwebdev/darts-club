import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import { parseMember, type Member } from './Conversation'
import { bool, InvalidResponseError, list, num, obj, optStr, str, type Json } from './guards'

/** Affiche d'un événement (WebP) : version carte pour le calendrier, grande version pour la page */
export interface EventPoster {
  cardUrl: string
  largeUrl: string
  width: number
  height: number
}

/** Réponse à un événement : seul « je viens » occupe une place */
export type Attendance = 'je-viens' | 'pas-sur'
export const ATTENDANCES: readonly Attendance[] = ['je-viens', 'pas-sur']

export function parseAttendance(value: unknown): Attendance {
  const answer = str(value, 'réponse')
  if (!(ATTENDANCES as readonly string[]).includes(answer)) throw new InvalidResponseError('réponse')
  return answer as Attendance
}

export function parsePoster(value: unknown): EventPoster | null {
  if (value === null || value === undefined) return null
  const d = obj(value, 'affiche')
  return { cardUrl: str(d.cardUrl), largeUrl: str(d.largeUrl), width: num(d.width), height: num(d.height) }
}

export class EventSummary {
  constructor(
    readonly id: string,
    readonly title: string,
    readonly location: string,
    readonly startsAt: Date,
    readonly endsAt: Date,
    readonly season: string,
    readonly cancelled: boolean,
    readonly cancelReason: string | null,
    readonly capacity: number | null,
    readonly registered: number,
    readonly isRegistered: boolean,
    readonly full: boolean,
    readonly started: boolean,
    readonly past: boolean,
    readonly hasSummary: boolean,
    readonly poster: EventPoster | null = null,
    /** « pas sûr » (adhérents et invités confirmés) */
    readonly maybe: number = 0,
    readonly myAnswer: Attendance | null = null,
    readonly isPublic: boolean = false,
    readonly city: string | null = null,
    /** Page publique (« /e/… »), si l'événement est ouvert au public */
    readonly publicPath: string | null = null,
  ) {}

  static parse(value: unknown): EventSummary {
    return EventSummary.fromJson(obj(value, 'événement'))
  }

  protected static fromJson(d: Json): EventSummary {
    const capacity = d.capacity === null ? null : num(d.capacity)
    return new EventSummary(
      str(d.id),
      str(d.title),
      str(d.location),
      new Date(str(d.startsAt)),
      new Date(str(d.endsAt)),
      str(d.season),
      str(d.status) === 'annule',
      optStr(d.cancelReason),
      capacity,
      num(d.registered),
      bool(d.isRegistered),
      bool(d.full),
      bool(d.started),
      bool(d.past),
      bool(d.hasSummary),
      parsePoster(d.poster),
      typeof d.maybe === 'number' ? d.maybe : 0,
      d.myAnswer === null || d.myAnswer === undefined ? null : parseAttendance(d.myAnswer),
      d.public === true,
      optStr(d.city),
      optStr(d.publicPath),
    )
  }

  get placesLeft(): number | null {
    return this.capacity === null ? null : Math.max(0, this.capacity - this.registered)
  }

  get canRegister(): boolean {
    return !this.cancelled && !this.started && !this.isRegistered && !this.full
  }
}

export interface EventPhoto {
  id: string
  thumbUrl: string
  largeUrl: string
  width: number
  height: number
  caption: string | null
  uploader: string
  mine: boolean
}

export interface EventPost {
  id: string
  body: string
  at: Date
  mine: boolean
  author: Member
}

/** Adhérent ayant répondu, vu par les organisateurs */
export interface Participant extends Member {
  answer: Attendance
}

/** Invité (sans compte) dont la réponse est confirmée, vu par les organisateurs */
export interface Guest {
  name: string
  email: string
  answer: Attendance
}

export interface EventDetail {
  summary: EventSummary
  description: string
  organizer: string
  report: string | null
  canManage: boolean
  photos: EventPhoto[]
  posts: EventPost[]
  participants: Participant[] | null
  guests: Guest[] | null
}

export function parseEventDetail(value: unknown): EventDetail {
  const d = obj(value, 'événement')
  return {
    summary: EventSummary.parse(d),
    description: str(d.description),
    organizer: str(d.organizer),
    report: optStr(d.summary),
    canManage: bool(d.canManage),
    photos: list(d.photos, (p) => {
      const x = obj(p, 'photo')
      return {
        id: str(x.id),
        thumbUrl: str(x.thumbUrl),
        largeUrl: str(x.largeUrl),
        width: num(x.width),
        height: num(x.height),
        caption: optStr(x.caption),
        uploader: str(x.uploader),
        mine: bool(x.mine),
      }
    }),
    posts: list(d.posts, (p) => {
      const x = obj(p, 'post')
      return { id: str(x.id), body: str(x.body), at: new Date(str(x.at)), mine: bool(x.mine), author: parseMember(x.author) }
    }),
    participants:
      d.participants === null || d.participants === undefined
        ? null
        : list(d.participants, (p) => ({ ...parseMember(p), answer: parseAnswerOrGoing(obj(p).answer) })),
    guests:
      d.guests === null || d.guests === undefined
        ? null
        : list(d.guests, (g) => {
            const x = obj(g, 'invité')
            return { name: str(x.name), email: str(x.email), answer: parseAttendance(x.answer) }
          }),
  }
}

/** Anciennes réponses sans précision : « je viens » */
function parseAnswerOrGoing(value: unknown): Attendance {
  return value === undefined ? 'je-viens' : parseAttendance(value)
}

/** Date d'événement à l'heure du club, dans la langue courante */
export function formatEventDate(date: Date): string {
  const text = formatDate(date, { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: CLUB_TIME_ZONE })
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export function dateBlock(date: Date): { day: string; month: string } {
  return {
    day: formatDate(date, { day: '2-digit', timeZone: CLUB_TIME_ZONE }),
    month: formatDate(date, { month: 'short', timeZone: CLUB_TIME_ZONE }).replace('.', ''),
  }
}

/** Valeur d'un <input type="datetime-local"> (heure locale) → ISO 8601 UTC pour l'API */
export function localInputToIso(value: string): string {
  return new Date(value).toISOString()
}

/** Date → valeur d'un <input type="datetime-local"> en heure locale */
export function toLocalInput(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
