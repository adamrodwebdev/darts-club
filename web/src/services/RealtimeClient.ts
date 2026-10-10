import type { ApiClient } from '@/api/ApiClient'
import { obj, str, strList } from '@/models/guards'

type Listener = (data: unknown) => void

/**
 * Connexion Mercure (Server-Sent Events). L'API pose un cookie d'autorisation
 * HttpOnly limité aux topics privés de l'adhérent ; aucun jeton côté JS.
 */
export class RealtimeClient {
  private source: EventSource | null = null

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

    const auth = obj(await this.api.post('/api/realtime/authorization'))
    const url = new URL(str(auth.hub), window.location.origin)
    for (const topic of strList(auth.topics)) url.searchParams.append('topic', topic)

    this.source = this.createSource(url.toString())
    this.source.onmessage = (event: MessageEvent<string>) => {
      try {
        onMessage(JSON.parse(event.data))
      } catch {
        // Message illisible : ignoré
      }
    }
  }

  disconnect(): void {
    this.source?.close()
    this.source = null
  }
}
