import type { ApiClient } from '@/api/ApiClient'
import {
  parseNotificationPage,
  parsePreferences,
  type NotificationPage,
  type OptionalChannel,
  type Preferences,
} from '@/models/Notification'

export class NotificationService {
  constructor(private readonly api: ApiClient) {}

  async list(unreadOnly = false): Promise<NotificationPage> {
    return parseNotificationPage(await this.api.get(`/api/notifications${unreadOnly ? '?unread=1' : ''}`))
  }

  async markRead(id: string): Promise<void> {
    await this.api.post(`/api/notifications/${encodeURIComponent(id)}/read`)
  }

  async markAllRead(): Promise<void> {
    await this.api.post('/api/notifications/read-all')
  }

  async preferences(): Promise<Preferences> {
    return parsePreferences(await this.api.get('/api/me/notification-preferences'))
  }

  async savePreferences(preferences: Record<string, OptionalChannel[]>): Promise<Preferences> {
    return parsePreferences(await this.api.put('/api/me/notification-preferences', { preferences }))
  }
}
