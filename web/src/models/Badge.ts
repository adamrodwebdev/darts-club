import { labelled } from '@/i18n/labels'
import { list, obj, str } from './guards'

export type BadgeId = 'president' | 'tresorier' | 'secretaire' | 'cotisant'

export interface Badge {
  readonly id: BadgeId
  /** Libellé dans la langue courante */
  readonly label: string
}

const KNOWN: readonly string[] = ['president', 'tresorier', 'secretaire', 'cotisant']

/** Badges inconnus ignorés : le front reste compatible si l'API en ajoute. */
export function parseBadges(value: unknown): Badge[] {
  if (value === undefined) return []
  return list(value, (b) => {
    const d = obj(b, 'badge')
    return labelled('badge', str(d.id) as BadgeId, str(d.label))
  }).filter((b) => KNOWN.includes(b.id))
}
