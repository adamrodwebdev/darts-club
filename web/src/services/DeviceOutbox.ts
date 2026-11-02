export type KeyValueStore = Pick<Storage, 'getItem' | 'setItem'>

/** Ce qu'une file sait de ses éléments : identifiant tiré sur l'appareil, soirée, forme attendue */
export interface OutboxRules<T> {
  id(item: T): string
  eventId(item: T): string
  accepts(value: unknown): value is T
}

/**
 * File d'actions en attente d'envoi (ventes de l'accueil, commandes du bar), gardée dans le
 * stockage de l'appareil : une page rechargée hors ligne ne perd rien. L'action repart avec le même
 * identifiant : l'API ne la compte jamais deux fois.
 */
export class DeviceOutbox<T> {
  constructor(
    private readonly store: KeyValueStore | null,
    private readonly key: string,
    private readonly rules: OutboxRules<T>,
  ) {}

  list(eventId: string): T[] {
    return this.all().filter((item) => this.rules.eventId(item) === eventId)
  }

  add(item: T): void {
    this.write([...this.all().filter((other) => this.rules.id(other) !== this.rules.id(item)), item])
  }

  remove(id: string): void {
    this.write(this.all().filter((item) => this.rules.id(item) !== id))
  }

  private all(): T[] {
    try {
      const stored: unknown = JSON.parse(this.store?.getItem(this.key) ?? '[]')
      return Array.isArray(stored) ? stored.filter((value) => this.rules.accepts(value)) : []
    } catch {
      return []
    }
  }

  private write(items: T[]): void {
    try {
      this.store?.setItem(this.key, JSON.stringify(items))
    } catch {
      // Stockage plein ou interdit (navigation privée) : l'action reste en mémoire le temps de la page
    }
  }
}

/** Objet `{ eventId, total, <champ> : { id } }` lu dans le stockage */
export function isPending(value: unknown, field: string): boolean {
  if (typeof value !== 'object' || value === null) return false
  const record = value as Record<string, unknown>
  const draft = record[field]
  return typeof record.eventId === 'string' && typeof record.total === 'string' && typeof draft === 'object' && draft !== null && typeof (draft as Record<string, unknown>).id === 'string'
}
