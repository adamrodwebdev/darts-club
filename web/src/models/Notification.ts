import { enumLabel } from '@/i18n'
import { labelled } from '@/i18n/labels'
import { bool, list, num, obj, optStr, str, strList } from './guards'

const CATEGORY_IDS = ['cotisation', 'evenement', 'reunion', 'annulation', 'information', 'message'] as const
export type CategoryId = (typeof CATEGORY_IDS)[number]

/** Catégories de notification, libellés dans la langue courante */
export const CATEGORIES = CATEGORY_IDS.map((id) => labelled('category', id))
export type OptionalChannel = 'email' | 'push'

export class AppNotification {
  constructor(
    readonly id: string,
    readonly category: string,
    /** Libellé fourni par l'API (repli) */
    readonly apiLabel: string,
    readonly title: string,
    readonly body: string,
    readonly link: string | null,
    readonly createdAt: Date,
    public read: boolean,
  ) {}

  /** Libellé de la catégorie dans la langue courante */
  get label(): string {
    return enumLabel('category', this.category, this.apiLabel)
  }

  static parse(value: unknown): AppNotification {
    const d = obj(value, 'notification')
    return new AppNotification(
      str(d.id),
      str(d.category),
      str(d.label),
      str(d.title),
      str(d.body),
      optStr(d.link),
      new Date(str(d.createdAt)),
      bool(d.read),
    )
  }
}

export interface NotificationPage {
  items: AppNotification[]
  unread: number
}

export function parseNotificationPage(value: unknown): NotificationPage {
  const d = obj(value)
  return { items: list(d.items, AppNotification.parse), unread: num(d.unread) }
}

/** Message temps réel publié par l'API sur le topic privé de l'adhérent. */
export function parseRealtimeNotification(value: unknown): { notification: AppNotification; unread: number } {
  const d = obj(value)
  return { notification: AppNotification.parse(d.notification), unread: num(d.unread) }
}

export type Preferences = Record<string, OptionalChannel[]>

export function parsePreferences(value: unknown): Preferences {
  const d = obj(obj(value).preferences, 'préférences')
  const result: Preferences = {}
  for (const [category, channels] of Object.entries(d)) {
    result[category] = strList(channels).filter((c): c is OptionalChannel => c === 'email' || c === 'push')
  }
  return result
}
