import type { ApiClient } from '@/api/ApiClient'
import { obj, str, strList } from '@/models/guards'

type Listener = (data: unknown) => void

/** `EventSource.CLOSED` : connexion abandonnée par le navigateur (refus du hub, jeton expiré) */
const CLOSED = 2
/** Reconnexion de plus en plus espacée, jamais plus d'une demi-minute d'attente */
const FIRST_RETRY_MS = 1000
const MAX_RETRY_MS = 30_000

/**
 * Connexion Mercure (Server-Sent Events, protocole 1.0 : abonnement par « match »). L'API pose un
 * cookie d'autorisation HttpOnly limité aux topics privés de l'adhérent ; aucun jeton côté JS.
 * Le jeton du cookie expire : quand le hub refuse la connexion, un nouveau cookie est demandé
 * avant de se reconnecter (les écrans d'une soirée restent ouverts des heures).
 */
export class RealtimeClient {
  private source: EventSource | null = null
  private listener: Listener | null = null
  private retries = 0
  private timer: ReturnType<typeof setTimeout> | undefined

  constructor(
    private readonly api: ApiClient,
    private readonly createSource: (url: string) => EventSource = (url) => new EventSource(url, { withCredentials: true }),
  ) {}

  get connected(): boolean {
    return this.source !== null
  }

  async connect(onMessage: Listener): Promise<void> {
    this.disconnect()
    if (typeof EventSource === 'undefined') return
    this.listener = onMessage
    await this.open()
  }

  disconnect(): void {
    clearTimeout(this.timer)
    this.listener = null
    this.retries = 0
    this.source?.close()
    this.source = null
  }

  private async open(): Promise<void> {
    const auth = obj(await this.api.post('/api/realtime/authorization'))
    if (this.listener === null) return
    const url = new URL(str(auth.hub), window.location.origin)
    for (const topic of strList(auth.topics)) url.searchParams.append('match', topic)

    const source = this.createSource(url.toString())
    source.onopen = () => {
      this.retries = 0
    }
    source.onmessage = (event: MessageEvent<string>) => {
      try {
        this.listener?.(JSON.parse(event.data))
      } catch {
        // Message illisible : ignoré
      }
    }
    // Coupure réseau : le navigateur se reconnecte seul ; refus du hub : nouveau cookie d'abord
    source.onerror = () => {
      if (source.readyState === CLOSED && this.source === source) this.retry()
    }
    this.source = source
  }

  private retry(): void {
    this.source = null
    const delay = Math.min(MAX_RETRY_MS, FIRST_RETRY_MS * 2 ** this.retries++)
    this.timer = setTimeout(() => {
      if (this.listener !== null) this.open().catch(() => this.retry())
    }, delay)
  }
}
