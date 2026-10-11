import { bool, list, num, obj, str } from './guards'

/** Nouvelles personnes ayant vu l'annonce, par jour */
export interface AudienceDay {
  day: string
  members: number
  visitors: number
}

/** Audience d'une annonce d'événement (administrateur uniquement) */
export interface EventAudience {
  unique: number
  /** Adhérents connectés : compte exact */
  members: number
  /** Visiteurs anonymes : estimation */
  visitors: number
  days: AudienceDay[]
  going: number
  maybe: number
  /** Détail supprimé, seuls les totaux restent */
  archived: boolean
  /** Mesure terminée (30 jours après l'événement) */
  closed: boolean
}

export function parseEventAudience(value: unknown): EventAudience {
  const d = obj(value, 'audience')
  return {
    unique: num(d.unique),
    members: num(d.members),
    visitors: num(d.visitors),
    days: list(d.days, (item) => {
      const x = obj(item, 'jour')
      return { day: str(x.day), members: num(x.members), visitors: num(x.visitors) }
    }),
    going: num(d.going),
    maybe: num(d.maybe),
    archived: bool(d.archived),
    closed: bool(d.closed),
  }
}
