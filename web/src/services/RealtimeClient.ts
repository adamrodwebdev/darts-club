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
 * cookie d'autorisation HttpOnly limité aux topics privés de l'adhérent et, pour un écran de
 * soirée, au topic de cette soirée ; aucun jeton côté JS. Une seule connexion pour toute l'appli :
 * chaque partie y écoute ce qui la concerne. Le jeton du cookie expire : quand le hub refuse la
 * connexion, un nouveau cookie est demandé avant de se reconnecter.
 */
export class RealtimeClient {
  private source: EventSource | null = null
  private readonly listeners = new Set<Listener>()
  private evening: string | null = null
  private active = false
  private retries = 0
  private timer: ReturnType<typeof setTimeout> | undefined

  constructor(
    private readonly api: ApiClient,
    private readonly createSource: (url: string) => EventSource = (url) => new EventSource(url, { withCredentials: true }),
  ) {}

  get connected(): boolean {
    return this.source !== null
  }

  /** Reçoit chaque message ; renvoie de quoi cesser d'écouter */
  listen(listener: Listener): () => void {
    this.listeners.add(listener)
    return () => this.listeners.delete(listener)
  }

  async connect(): Promise<void> {
    this.close()
    if (typeof EventSource === 'undefined') return
    this.active = true
    await this.open()
  }

  /** Suit aussi la soirée d'un écran (accueil, bar, cuisine), ou plus aucune ; se reconnecte si besoin */
  async watchEvening(eventId: string | null): Promise<void> {
    if (this.evening === eventId) return
    this.evening = eventId
    if (this.active) await this.connect()
  }

  disconnect(): void {
    this.active = false
    this.close()
  }

  private close(): void {
    clearTimeout(this.timer)
    this.retries = 0
    this.source?.close()
    this.source = null
  }

  private async open(): Promise<void> {
    const query = this.evening === null ? '' : `?event=${encodeURIComponent(this.evening)}`
    const auth = obj(await this.api.post(`/api/realtime/authorization${query}`))
    if (!this.active) return
    const url = new URL(str(auth.hub), window.location.origin)
    for (const topic of strList(auth.topics)) url.searchParams.append('match', topic)

    const source = this.createSource(url.toString())
    source.onopen = () => {
      this.retries = 0
    }
    source.onmessage = (event: MessageEvent<string>) => {
      let data: unknown
      try {
        data = JSON.parse(event.data)
      } catch {
        return // Message illisible : ignoré
      }
      this.listeners.forEach((listener) => listener(data))
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
      if (this.active) this.open().catch(() => this.retry())
    }, delay)
  }
}
