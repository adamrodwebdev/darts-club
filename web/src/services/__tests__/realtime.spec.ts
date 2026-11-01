import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ApiClient } from '@/api/ApiClient'
import { RealtimeClient } from '../RealtimeClient'

/** EventSource simulé : l'état et les rappels que le client utilise */
class FakeSource {
  readyState = 1
  onopen: (() => void) | null = null
  onmessage: ((event: MessageEvent<string>) => void) | null = null
  onerror: (() => void) | null = null

  constructor(readonly url: string) {}

  close(): void {
    this.readyState = 2
  }

  /** Le hub refuse (jeton expiré) : le navigateur abandonne la connexion */
  refuse(): void {
    this.readyState = 2
    this.onerror?.()
  }
}

const TOPIC = 'https://darts-club.app/users/u1/notifications'

function setup() {
  const post = vi.fn<(path: string) => Promise<unknown>>().mockResolvedValue({ hub: 'https://example.test/.well-known/mercure', topics: [TOPIC] })
  const sources: FakeSource[] = []
  const client = new RealtimeClient({ post } as unknown as ApiClient, (url) => {
    const source = new FakeSource(url)
    sources.push(source)
    return source as unknown as EventSource
  })
  return { post, sources, client }
}

describe('RealtimeClient', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal('EventSource', class {})
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('subscribes with the Mercure 1.0 match parameter and passes messages on', async () => {
    const { sources, client } = setup()
    const received: unknown[] = []
    await client.connect((data) => received.push(data))

    expect(new URL(sources[0]?.url ?? '').searchParams.getAll('match')).toEqual([TOPIC])
    sources[0]?.onmessage?.({ data: '{"unread":2}' } as MessageEvent<string>)
    sources[0]?.onmessage?.({ data: 'not json' } as MessageEvent<string>)
    expect(received).toEqual([{ unread: 2 }])
  })

  it('asks for a fresh cookie before reconnecting when the hub refuses the connection', async () => {
    const { post, sources, client } = setup()
    await client.connect(() => {})

    sources[0]?.refuse()
    expect(post).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(1000)
    expect(post).toHaveBeenCalledTimes(2)
    expect(sources).toHaveLength(2)

    // Deuxième refus : attente doublée
    sources[1]?.refuse()
    await vi.advanceTimersByTimeAsync(1000)
    expect(sources).toHaveLength(2)
    await vi.advanceTimersByTimeAsync(1000)
    expect(sources).toHaveLength(3)
  })

  it('stops for good once disconnected', async () => {
    const { post, sources, client } = setup()
    await client.connect(() => {})
    sources[0]?.refuse()
    client.disconnect()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(post).toHaveBeenCalledTimes(1)
    expect(client.connected).toBe(false)
  })
})
