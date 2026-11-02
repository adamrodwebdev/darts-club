import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { EveningOrder, KitchenScreen, ServiceItem } from '@/models/EveningService'
import { useServices } from '@/services'
import { useAsyncAction } from './useAsyncAction'
import { useWakeLock } from './useWakeLock'

const REFRESH_DELAY_MS = 300
const TICK_MS = 30_000
/** Sans temps réel (hub en panne), la file se relit quand même régulièrement */
const POLL_MS = 15_000

/**
 * Cuisine d'une soirée (tablette) : file des commandes de la plus ancienne à la plus récente, avec
 * leur attente, bouton « Prête » (validé une seule fois, même depuis deux appareils), articles et
 * garnitures épuisés, son à chaque nouvelle commande, écran gardé allumé.
 */
export function useKitchen(eventId: string) {
  const services = useServices()
  const { t } = useI18n()
  const load = useAsyncAction()
  const action = useAsyncAction()
  const wakeLock = useWakeLock()

  const screen = ref<KitchenScreen | null>(null)
  const sound = ref(true)
  const toast = ref('')
  const now = ref(services.clock.now())

  async function refresh(): Promise<void> {
    const before = new Set(screen.value?.pending.map((order) => order.id) ?? [])
    const loaded = await load.run(() => services.eveningDesk.kitchen(eventId))
    if (!loaded) return
    if (screen.value !== null && sound.value && loaded.pending.some((order) => !before.has(order.id))) services.chime.play()
    screen.value = loaded
  }

  async function ready(order: EveningOrder): Promise<void> {
    services.chime.unlock()
    const updated = await action.run(() => services.eveningDesk.markReady(order.id))
    if (updated) {
      screen.value = updated
      toast.value = t('evening.kitchen.readyToast', { number: order.number })
    } else {
      await refresh()
    }
  }

  async function setAvailability(item: ServiceItem, change: { soldOut?: boolean; garnish?: string }): Promise<void> {
    const out = item.garnishes.filter((g) => g.out).map((g) => g.name)
    const garnishesOut = change.garnish === undefined ? out : out.includes(change.garnish) ? out.filter((g) => g !== change.garnish) : [...out, change.garnish]
    const updated = await action.run(() => services.eveningDesk.setAvailability(item.id, { soldOut: change.soldOut ?? item.markedSoldOut, garnishesOut }))
    if (updated) screen.value = updated
  }

  function toggleSound(): void {
    sound.value = !sound.value
    if (sound.value) services.chime.unlock()
  }

  let refreshTimer: ReturnType<typeof setTimeout> | undefined
  const stopListening = services.realtime.listen((data) => {
    const kind = typeof data === 'object' && data !== null ? (data as Record<string, unknown>).kind : null
    if (kind !== 'order' && kind !== 'menu') return
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => void refresh(), REFRESH_DELAY_MS)
  })
  const ticker = setInterval(() => (now.value = services.clock.now()), TICK_MS)
  const poller = setInterval(() => void refresh(), POLL_MS)

  onMounted(async () => {
    void services.realtime.watchEvening(eventId).catch(() => undefined)
    await refresh()
  })

  onBeforeUnmount(() => {
    stopListening()
    clearTimeout(refreshTimer)
    clearInterval(ticker)
    clearInterval(poller)
    void services.realtime.watchEvening(null).catch(() => undefined)
  })

  return { screen, sound, toast, now, load, action, wakeLock, ready, setAvailability, toggleSound }
}
