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

/** Libellé de chaque réponse (clé de traduction) : une seule table, aucun test répété */
export const ATTENDANCE_LABELS = {
  'je-viens': 'events.answer.going',
  'pas-sur': 'events.answer.maybe',
} as const satisfies Record<Attendance, string>

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

/** Champs d'un événement tels que l'API les décrit */
export interface EventSummaryData {
  id: string
  title: string
  location: string
  startsAt: Date
  endsAt: Date
  season: string
  cancelled: boolean
  cancelReason: string | null
  capacity: number | null
  /** « je viens » (adhérents et invités confirmés) */
  registered: number
  /** « pas sûr » (adhérents et invités confirmés) */
  maybe: number
  isRegistered: boolean
  myAnswer: Attendance | null
  full: boolean
  started: boolean
  past: boolean
  hasSummary: boolean
  poster: EventPoster | null
  isPublic: boolean
  city: string | null
  /** Page publique (« /e/… »), si l'événement est ouvert au public */
  publicPath: string | null
}

export class EventSummary {
  declare readonly id: string
  declare readonly title: string
  declare readonly location: string
  declare readonly startsAt: Date
  declare readonly endsAt: Date
  declare readonly season: string
  declare readonly cancelled: boolean
  declare readonly cancelReason: string | null
  declare readonly capacity: number | null
  declare readonly registered: number
  declare readonly maybe: number
  declare readonly isRegistered: boolean
  declare readonly myAnswer: Attendance | null
  declare readonly full: boolean
  declare readonly started: boolean
  declare readonly past: boolean
  declare readonly hasSummary: boolean
  declare readonly poster: EventPoster | null
  declare readonly isPublic: boolean
  declare readonly city: string | null
  declare readonly publicPath: string | null

  constructor(data: EventSummaryData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): EventSummary {
    return EventSummary.fromJson(obj(value, 'événement'))
  }

  protected static fromJson(d: Json): EventSummary {
    return new EventSummary({
      id: str(d.id),
      title: str(d.title),
      location: str(d.location),
      startsAt: new Date(str(d.startsAt)),
      endsAt: new Date(str(d.endsAt)),
      season: str(d.season),
      cancelled: str(d.status) === 'annule',
      cancelReason: optStr(d.cancelReason),
      capacity: d.capacity === null ? null : num(d.capacity),
      registered: num(d.registered),
      maybe: typeof d.maybe === 'number' ? d.maybe : 0,
      isRegistered: bool(d.isRegistered),
      myAnswer: d.myAnswer === null || d.myAnswer === undefined ? null : parseAttendance(d.myAnswer),
      full: bool(d.full),
      started: bool(d.started),
      past: bool(d.past),
      hasSummary: bool(d.hasSummary),
      poster: parsePoster(d.poster),
      isPublic: d.public === true,
      city: optStr(d.city),
      publicPath: optStr(d.publicPath),
    })
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
  id: string
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
            return { id: str(x.id), name: str(x.name), email: str(x.email), answer: parseAttendance(x.answer) }
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
