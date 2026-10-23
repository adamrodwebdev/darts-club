import { defineStore } from 'pinia'
import { ref } from 'vue'
import { AppNotification, parseRealtimeNotification } from '@/models/Notification'
import { useServices } from '@/services'

export const useNotificationStore = defineStore('notifications', () => {
  const services = useServices()
  const items = ref<AppNotification[]>([])
  const unread = ref(0)
  const loaded = ref(false)
  /** Abonnés aux nouvelles notifications (ex. messagerie à rafraîchir) */
  const listeners = new Set<(n: AppNotification) => void>()

  async function load(): Promise<void> {
    const page = await services.notifications.list()
    items.value = page.items
    unread.value = page.unread
    loaded.value = true
  }

  /** Ne dépend que de ce qu'elle utilise : une notification réactive (proxy Vue) convient aussi */
  async function markRead(notification: Pick<AppNotification, 'id' | 'read' | 'markRead'>): Promise<void> {
    if (notification.read) return
    notification.markRead()
    unread.value = Math.max(0, unread.value - 1)
    await services.notifications.markRead(notification.id)
  }

  async function markAllRead(): Promise<void> {
    items.value.forEach((n) => n.markRead())
    unread.value = 0
    await services.notifications.markAllRead()
  }

  async function connectRealtime(): Promise<void> {
    try {
      await services.realtime.connect((data) => {
        const { notification, unread: count } = parseRealtimeNotification(data)
        items.value = [notification, ...items.value.filter((n) => n.id !== notification.id)].slice(0, 50)
        unread.value = count
        listeners.forEach((listener) => listener(notification))
      })
    } catch {
      // Temps réel indisponible : le centre reste utilisable au rechargement
    }
  }

  function onNotification(listener: (n: AppNotification) => void): () => void {
    listeners.add(listener)
    return () => listeners.delete(listener)
  }

  function reset(): void {
    services.realtime.disconnect()
    items.value = []
    unread.value = 0
    loaded.value = false
  }

  return { items, unread, loaded, load, markRead, markAllRead, connectRealtime, onNotification, reset }
})
