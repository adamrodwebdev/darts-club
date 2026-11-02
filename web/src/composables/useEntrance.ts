import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/ApiError'
import { formatEuro } from '@/i18n/format'
import { changeCents, saleCents } from '@/models/CashRules'
import type { EntranceScreen, PaymentMethod, TokenSale } from '@/models/EveningEntrance'
import { useServices } from '@/services'
import type { PendingSale } from '@/services/SaleOutbox'
import { useAsyncAction } from './useAsyncAction'

/** Ventes en attente : nouvel essai régulier tant que le réseau manque */
const RETRY_MS = 20_000
/** Plusieurs ventes diffusées d'un coup : une seule relecture de l'écran */
const REFRESH_DELAY_MS = 300

const METHOD_KEYS = { especes: 'evening.entrance.byCash', carte: 'evening.entrance.byCard' } as const

/**
 * Accueil d'une soirée : choix des jetons, montant et monnaie à rendre (calculés sur l'appareil,
 * hors ligne compris), envoi de la vente avec un identifiant tiré sur l'appareil, file d'attente
 * gardée sur l'appareil quand le réseau manque, relecture quand un autre appareil vend.
 */
export function useEntrance(eventId: string) {
  const services = useServices()
  const { t } = useI18n()
  const load = useAsyncAction()
  const action = useAsyncAction()

  const screen = ref<EntranceScreen | null>(null)
  const pending = ref<PendingSale[]>(services.saleOutbox.list(eventId))
  const counts = ref<Record<string, number>>({})
  /** Billet reçu, en centimes */
  const given = ref<number | null>(null)
  const online = ref(true)
  const toast = ref('')

  const total = computed(() => (screen.value ? saleCents(screen.value.tokens, counts.value) : 0))
  const change = computed(() => (given.value === null || total.value === 0 ? null : changeCents(given.value, total.value)))
  const summary = computed(() =>
    (screen.value?.tokens ?? [])
      .filter((token) => (counts.value[token.name] ?? 0) > 0)
      .map((token) => t('evening.entrance.line', { count: counts.value[token.name] ?? 0, name: token.name }))
      .join(' + '),
  )

  function add(name: string, delta: number): void {
    counts.value = { ...counts.value, [name]: Math.max(0, (counts.value[name] ?? 0) + delta) }
    toast.value = ''
  }

  async function refresh(): Promise<void> {
    const loaded = await load.run(() => services.eveningDesk.entrance(eventId))
    if (loaded) screen.value = loaded
  }

  /** Une vente en attente part ; réseau coupé, elle reste dans la file ; refusée par l'API, elle en sort */
  async function send(waiting: PendingSale): Promise<void> {
    try {
      screen.value = await services.eveningDesk.sell(eventId, waiting.sale)
      services.saleOutbox.remove(waiting.sale.id)
      online.value = true
    } catch (e) {
      if (e instanceof ApiError && e.status === 0) {
        online.value = false
        return
      }
      services.saleOutbox.remove(waiting.sale.id)
      action.error.value = e instanceof Error ? e.message : t('errors.unexpected')
    } finally {
      pending.value = services.saleOutbox.list(eventId)
    }
  }

  async function flush(): Promise<void> {
    for (const waiting of services.saleOutbox.list(eventId)) {
      await send(waiting)
      if (!online.value) return
    }
  }

  async function sell(method: PaymentMethod): Promise<void> {
    const tokens = screen.value?.tokens ?? []
    if (total.value === 0) return
    const waiting: PendingSale = {
      eventId,
      total: (total.value / 100).toFixed(2),
      sale: {
        id: services.ids.next(),
        method,
        lines: tokens.filter((token) => (counts.value[token.name] ?? 0) > 0).map((token) => ({ name: token.name, count: counts.value[token.name] ?? 0 })),
      },
    }
    const sold = t('evening.entrance.sold', { summary: summary.value, amount: formatEuro(waiting.total), method: t(METHOD_KEYS[method]) })
    action.error.value = null
    services.saleOutbox.add(waiting)
    counts.value = {}
    given.value = null
    await send(waiting)
    toast.value = online.value ? sold : t('evening.entrance.queued')
  }

  async function cancel(sale: TokenSale, time: string): Promise<void> {
    const updated = await action.run(() => services.eveningDesk.cancelSale(sale.id))
    if (updated) {
      screen.value = updated
      toast.value = t('evening.entrance.cancelledToast', { time })
    } else if (action.errorCode.value === null) {
      await refresh()
    }
  }

  let refreshTimer: ReturnType<typeof setTimeout> | undefined
  const stopListening = services.realtime.listen((data) => {
    if (typeof data !== 'object' || data === null || (data as Record<string, unknown>).kind !== 'sale') return
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => void refresh(), REFRESH_DELAY_MS)
  })
  const goOnline = () => void flush()
  const goOffline = () => (online.value = false)
  const retryTimer = setInterval(() => {
    if (pending.value.length > 0) void flush()
  }, RETRY_MS)

  onMounted(async () => {
    window.addEventListener('online', goOnline)
    window.addEventListener('offline', goOffline)
    void services.realtime.watchEvening(eventId).catch(() => undefined)
    await refresh()
    await flush()
  })

  onBeforeUnmount(() => {
    stopListening()
    clearTimeout(refreshTimer)
    clearInterval(retryTimer)
    window.removeEventListener('online', goOnline)
    window.removeEventListener('offline', goOffline)
    void services.realtime.watchEvening(null).catch(() => undefined)
  })

  return { screen, pending, counts, given, online, toast, total, change, summary, load, action, add, sell, cancel }
}
