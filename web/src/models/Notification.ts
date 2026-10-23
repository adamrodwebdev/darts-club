import { enumLabel } from '@/i18n'
import { labelled } from '@/i18n/labels'
import { bool, list, num, obj, optStr, str, strList } from './guards'

const CATEGORY_IDS = ['cotisation', 'evenement', 'reunion', 'annulation', 'information', 'message'] as const
export type CategoryId = (typeof CATEGORY_IDS)[number]

/** Catégories de notification, libellés dans la langue courante */
export const CATEGORIES = CATEGORY_IDS.map((id) => labelled('category', id))
export type OptionalChannel = 'email' | 'push'

export interface AppNotificationData {
  id: string
  category: string
  /** Libellé fourni par l'API (repli) */
  apiLabel: string
  title: string
  body: string
  link: string | null
  createdAt: Date
  read: boolean
}

export class AppNotification {
  declare readonly id: string
  declare readonly category: string
  declare readonly apiLabel: string
  declare readonly title: string
  declare readonly body: string
  declare readonly link: string | null
  declare readonly createdAt: Date
  /** Lue : ne change que par markRead() (champ « privé » TypeScript, compatible avec la réactivité Vue) */
  private wasRead: boolean

  constructor({ read, ...data }: AppNotificationData) {
    Object.assign(this, data)
    this.wasRead = read
  }

  get read(): boolean {
    return this.wasRead
  }

  markRead(): void {
    this.wasRead = true
  }

  /** Libellé de la catégorie dans la langue courante */
  get label(): string {
    return enumLabel('category', this.category, this.apiLabel)
  }

  static parse(value: unknown): AppNotification {
    const d = obj(value, 'notification')
    return new AppNotification({
      id: str(d.id),
      category: str(d.category),
      apiLabel: str(d.label),
      title: str(d.title),
      body: str(d.body),
      link: optStr(d.link),
      createdAt: new Date(str(d.createdAt)),
      read: bool(d.read),
    })
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
