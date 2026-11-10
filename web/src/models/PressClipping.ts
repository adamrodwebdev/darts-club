import { formatDate } from '@/i18n/format'
import type { EventPhoto } from './ClubEvent'
import { InvalidResponseError, obj, optStr } from './guards'
import { TEXT_LIMITS } from './limits'

/** Un jour du calendrier écrit à la manière ISO 8601, comme l'API l'envoie et le reçoit */
const ISO_DAY = /^(\d{4})-(\d{2})-(\d{2})$/

/** « 2026-11-03 » s'il désigne un vrai jour (pas de 30 février), null sinon */
export function isoDay(text: string): string | null {
  const parts = ISO_DAY.exec(text)
  if (parts === null) return null
  const [, year, month, day] = parts.map(Number)
  if (year === undefined || month === undefined || day === undefined) return null
  const date = new Date(Date.UTC(year, month - 1, day))
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day ? text : null
}

/** Texte facultatif de l'API, refusé au-delà de la longueur de sa colonne (comptée en caractères) */
function optText(value: unknown, max: number, what: string): string | null {
  const text = optStr(value, what)
  if (text !== null && [...text].length > max) throw new InvalidResponseError(what)
  return text === '' ? null : text
}

interface PressDetailsOptions {
  outlet: string | null
  publishedOn: string | null
  title: string | null
  credit: string | null
}

/**
 * Ce qu'une coupure de presse dit sous son image : le journal, la date de parution (un jour du
 * calendrier, sans heure ni fuseau), le titre de l'article et le crédit. Tous facultatifs.
 */
export class PressDetails {
  readonly outlet: string | null
  /** « 2026-11-03 » */
  readonly publishedOn: string | null
  readonly title: string | null
  readonly credit: string | null

  private constructor(options: PressDetailsOptions) {
    this.outlet = options.outlet
    this.publishedOn = options.publishedOn
    this.title = options.title
    this.credit = options.credit
  }

  static parse(value: unknown): PressDetails {
    const d = obj(value, 'coupure de presse')
    const day = optStr(d.publishedOn, 'date de parution')
    const publishedOn = day === null ? null : isoDay(day)
    if (day !== null && publishedOn === null) throw new InvalidResponseError(`date de parution : ${day}`)
    return new PressDetails({
      outlet: optText(d.outlet, TEXT_LIMITS.pressOutlet, 'journal'),
      publishedOn,
      title: optText(d.title, TEXT_LIMITS.pressTitle, 'titre de l’article'),
      credit: optText(d.credit, TEXT_LIMITS.pressCredit, 'crédit'),
    })
  }

  /** « 3 novembre 2026 » dans la langue courante ; null sans date */
  get publishedLabel(): string | null {
    if (this.publishedOn === null) return null
    // Le jour tel quel, à minuit UTC et lu en UTC : aucun fuseau ne le décale d'un jour
    return formatDate(new Date(`${this.publishedOn}T00:00:00Z`), { dateStyle: 'long', timeZone: 'UTC' })
  }
}

/** Ce que le bureau saisit pour une coupure (textes tels que tapés ; vide = non renseigné) */
export interface PressDraft {
  outlet: string
  /** « 2026-11-03 », ou vide */
  publishedOn: string
  title: string
  credit: string
}

/**
 * Coupure de presse : une photo de la galerie d'un événement que le bureau a classée « Dans la
 * presse ». Mêmes fichiers et mêmes droits que les autres photos ; ses détails ne manquent jamais
 * (ils peuvent être vides).
 */
export class PressClipping {
  private constructor(
    readonly photo: EventPhoto,
    readonly details: PressDetails,
  ) {}

  /** Depuis une photo lue à la frontière (parseEventPhoto) : refusée si l'API ne l'a pas classée */
  static from(photo: EventPhoto): PressClipping {
    if (photo.press === null) throw new InvalidResponseError('coupure de presse sans détails')
    return new PressClipping(photo, photo.press)
  }

  get id(): string {
    return this.photo.id
  }
}
