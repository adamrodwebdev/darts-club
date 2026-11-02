import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/ApiError'
import { formatEuro } from '@/i18n/format'
import { toCents } from '@/models/CashRules'
import type { BarScreen, EveningOrder, ServiceFormat, ServiceItem } from '@/models/EveningService'
import { tokensFor } from '@/models/OrderRules'
import { useServices } from '@/services'
import type { PendingOrder } from '@/services/SaleOutbox'
import { useAsyncAction } from './useAsyncAction'

/** Ligne de la commande en préparation ; même article, format et garnitures : une seule ligne */
export interface CartLine {
  key: string
  item: ServiceItem
  format: ServiceFormat
  garnishes: string[]
  count: number
}

export type BarTab = 'order' | 'ready' | 'kitchen'

const RETRY_MS = 20_000
const REFRESH_DELAY_MS = 300
const TICK_MS = 30_000
/** Sans temps réel (hub en panne), les commandes prêtes arrivent quand même */
const POLL_MS = 15_000

const keyOf = (itemId: string, format: string, garnishes: readonly string[]): string => [itemId, format, ...[...garnishes].sort()].join('|')

/**
 * Bar d'une soirée : commande composée sur l'appareil (montant et jetons à demander calculés hors
 * ligne compris), envoyée avec un identifiant tiré sur l'appareil, gardée dans la file quand le
 * réseau manque ; plats prêts annoncés par un son, remise au client, annulation tant que la cuisine
 * attend.
 */
export function useBar(eventId: string) {
  const services = useServices()
  const { t } = useI18n()
  const load = useAsyncAction()
  const action = useAsyncAction()

  const screen = ref<BarScreen | null>(null)
  const tab = ref<BarTab>('order')
  const cart = ref<CartLine[]>([])
  /** Garnitures choisies pour le prochain plat, par article */
  const garnishChoice = ref<Record<string, string[]>>({})
  const note = ref('')
  const pending = ref<PendingOrder[]>(services.orderOutbox.list(eventId))
  const online = ref(true)
  const toast = ref('')
  const now = ref(services.clock.now())

  const total = computed(() => cart.value.reduce((sum, line) => sum + toCents(line.format.price) * line.count, 0))
  const tokens = computed(() => (screen.value?.tokensEnabled ? tokensFor(total.value, screen.value.tokens) : null))

  function countOf(item: ServiceItem, format: ServiceFormat): number {
    return cart.value.find((line) => line.key === keyOf(item.id, format.name, []))?.count ?? 0
  }

  /** Ajoute ou retire un article sans garniture (boisson, frites) */
  function step(item: ServiceItem, format: ServiceFormat, delta: number): void {
    change(keyOf(item.id, format.name, []), delta, { item, format, garnishes: [] })
  }

  /** Une ligne de la commande en préparation, plus ou moins un */
  function stepLine(line: CartLine, delta: number): void {
    change(line.key, delta, line)
  }

  function toggleGarnish(item: ServiceItem, garnish: string): void {
    const chosen = garnishChoice.value[item.id] ?? []
    garnishChoice.value = { ...garnishChoice.value, [item.id]: chosen.includes(garnish) ? chosen.filter((g) => g !== garnish) : [...chosen, garnish] }
  }

  /** Un plat de plus, avec les garnitures choisies */
  function addWithGarnishes(item: ServiceItem, format: ServiceFormat): void {
    const garnishes = garnishChoice.value[item.id] ?? []
    change(keyOf(item.id, format.name, garnishes), 1, { item, format, garnishes: [...garnishes] })
    garnishChoice.value = { ...garnishChoice.value, [item.id]: [] }
  }

  function change(key: string, delta: number, line: Omit<CartLine, 'key' | 'count'>): void {
    const existing = cart.value.find((l) => l.key === key)
    const count = Math.max(0, (existing?.count ?? 0) + delta)
    cart.value = existing
      ? cart.value.map((l) => (l.key === key ? { ...l, count } : l)).filter((l) => l.count > 0)
      : count > 0
        ? [...cart.value, { ...line, key, count }]
        : cart.value
    toast.value = ''
  }

  async function refresh(): Promise<void> {
    const before = new Set(screen.value?.orders.ready.map((order) => order.id) ?? [])
    const loaded = await load.run(() => services.eveningDesk.bar(eventId))
    if (!loaded) return
    const fresh = loaded.orders.ready.some((order) => !before.has(order.id))
    if (screen.value !== null && fresh) services.chime.play()
    screen.value = loaded
  }

  async function send(waiting: PendingOrder): Promise<EveningOrder | null> {
    try {
      screen.value = await services.eveningDesk.order(eventId, waiting.order)
      services.orderOutbox.remove(waiting.order.id)
      online.value = true
      return screen.value.orders.waiting.find((order) => order.id === waiting.order.id) ?? null
    } catch (e) {
      if (e instanceof ApiError && e.status === 0) {
        online.value = false
        return null
      }
      services.orderOutbox.remove(waiting.order.id)
      action.error.value = e instanceof Error ? e.message : t('errors.unexpected')
      return null
    } finally {
      pending.value = services.orderOutbox.list(eventId)
    }
  }

  async function flush(): Promise<void> {
    for (const waiting of services.orderOutbox.list(eventId)) {
      await send(waiting)
      if (!online.value) return
    }
  }

  async function validate(): Promise<void> {
    if (cart.value.length === 0) return
    services.chime.unlock()
    const food = cart.value.filter((line) => line.item.station === 'cuisine').reduce((sum, line) => sum + line.count, 0)
    const waiting: PendingOrder = {
      eventId,
      total: (total.value / 100).toFixed(2),
      order: {
        id: services.ids.next(),
        note: note.value.trim(),
        lines: cart.value.map((line) => ({ itemId: line.item.id, format: line.format.name, count: line.count, garnishes: line.garnishes })),
      },
    }
    action.error.value = null
    services.orderOutbox.add(waiting)
    cart.value = []
    note.value = ''
    const placed = await send(waiting)
    if (!online.value) {
      toast.value = t('evening.bar.queued')
    } else if (action.error.value === null) {
      toast.value = food > 0 ? t('evening.bar.sentToKitchen', { number: placed?.number ?? '', n: food }, food) : t('evening.bar.drinksOnly')
    }
  }

  async function deliver(order: EveningOrder): Promise<void> {
    services.chime.unlock()
    const updated = await action.run(() => services.eveningDesk.deliver(order.id))
    if (updated) {
      screen.value = updated
      toast.value = t('evening.bar.delivered', { number: order.number })
    } else {
      await refresh()
    }
  }

  async function cancel(order: EveningOrder): Promise<void> {
    const updated = await action.run(() => services.eveningDesk.cancelOrder(order.id))
    if (updated) {
      screen.value = updated
      toast.value = refundOf(order)
    } else {
      await refresh()
    }
  }

  /** Part cuisine annulée : ce que le bar rend au client, en jetons quand la soirée en a */
  function refundOf(order: EveningOrder): string {
    const cents = order.lines.filter((line) => line.station === 'cuisine').reduce((sum, line) => sum + toCents(line.price) * line.count, 0)
    const amount = formatEuro(cents / 100)
    const counts = screen.value?.tokensEnabled ? tokensFor(cents, screen.value.tokens).counts : []
    if (counts.length === 0) return t('evening.bar.cancelledAmount', { number: order.number, amount })
    const tokens = counts.map((entry) => t('evening.entrance.line', { count: entry.count, name: entry.token.name })).join(' + ')
    return t('evening.bar.cancelledTokens', { number: order.number, tokens, amount })
  }

  let refreshTimer: ReturnType<typeof setTimeout> | undefined
  const stopListening = services.realtime.listen((data) => {
    const kind = typeof data === 'object' && data !== null ? (data as Record<string, unknown>).kind : null
    if (kind !== 'order' && kind !== 'menu') return
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => void refresh(), REFRESH_DELAY_MS)
  })
  const goOnline = () => void flush()
  const goOffline = () => (online.value = false)
  const retryTimer = setInterval(() => {
    if (pending.value.length > 0) void flush()
  }, RETRY_MS)
  const ticker = setInterval(() => (now.value = services.clock.now()), TICK_MS)
  const poller = setInterval(() => void refresh(), POLL_MS)

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
    clearInterval(ticker)
    clearInterval(poller)
    window.removeEventListener('online', goOnline)
    window.removeEventListener('offline', goOffline)
    void services.realtime.watchEvening(null).catch(() => undefined)
  })

  return { screen, tab, cart, garnishChoice, note, pending, online, toast, now, total, tokens, load, action, countOf, step, stepLine, toggleGarnish, addWithGarnishes, validate, deliver, cancel }
}
