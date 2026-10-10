import { list, obj, str } from './guards'

export type BadgeId = 'president' | 'tresorier' | 'secretaire' | 'cotisant'

export interface Badge {
  id: BadgeId
  label: string
}

const KNOWN: readonly string[] = ['president', 'tresorier', 'secretaire', 'cotisant']

/** Badges inconnus ignorés : le front reste compatible si l'API en ajoute. */
export function parseBadges(value: unknown): Badge[] {
  if (value === undefined) return []
  return list(value, (b) => {
    const d = obj(b, 'badge')
    return { id: str(d.id) as BadgeId, label: str(d.label) }
  }).filter((b) => KNOWN.includes(b.id))
}
