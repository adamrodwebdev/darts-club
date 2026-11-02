import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { LiveIncome } from '@/models/LiveIncome'
import { useServices } from '@/services'
import { useAsyncAction } from './useAsyncAction'

/** Ce qui change les recettes : une vente à l'accueil, une commande, un article épuisé */
const KINDS: ReadonlySet<unknown> = new Set(['sale', 'order', 'menu'])
const REFRESH_DELAY_MS = 300
/** Sans temps réel (hub en panne), les chiffres suivent quand même */
const POLL_MS = 15_000

/**
 * Recettes en direct d'une soirée : relues à chaque vente et commande annoncées par le temps réel
 * (regroupées quand elles se suivent), toutes les 15 s en secours, et au retour sur l'onglet.
 */
export function useLiveIncome(eventId: string) {
  const services = useServices()
  const load = useAsyncAction()
  const income = ref<LiveIncome | null>(null)
  const updatedAt = ref<Date | null>(null)
  /** Dernière lecture manquée : les chiffres affichés datent */
  const stale = computed(() => income.value !== null && load.error.value !== null)

  async function refresh(): Promise<void> {
    const loaded = await load.run(() => services.eventAccounts.income(eventId))
    if (!loaded) return
    income.value = loaded
    updatedAt.value = services.clock.now()
  }

  let refreshTimer: ReturnType<typeof setTimeout> | undefined
  const stopListening = services.realtime.listen((data) => {
    const kind = typeof data === 'object' && data !== null ? (data as Record<string, unknown>).kind : null
    if (!KINDS.has(kind)) return
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => void refresh(), REFRESH_DELAY_MS)
  })
  const visible = (): boolean => document.visibilityState === 'visible'
  const onVisibility = (): void => {
    if (visible()) void refresh()
  }
  const poller = setInterval(onVisibility, POLL_MS)

  onMounted(async () => {
    document.addEventListener('visibilitychange', onVisibility)
    void services.realtime.watchEvening(eventId).catch(() => undefined)
    await refresh()
  })

  onBeforeUnmount(() => {
    stopListening()
    clearTimeout(refreshTimer)
    clearInterval(poller)
    document.removeEventListener('visibilitychange', onVisibility)
    void services.realtime.watchEvening(null).catch(() => undefined)
  })

  return { income, updatedAt, stale, load }
}
