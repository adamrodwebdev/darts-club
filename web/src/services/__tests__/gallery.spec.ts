import { describe, expect, it } from 'vitest'
import { ApiClient } from '@/api/ApiClient'
import { GalleryVideo, galleryItems, parseGalleryRights } from '@/models/Gallery'
import type { EventPhoto } from '@/models/ClubEvent'
import { EventService } from '../EventService'
import { VideoUploadService } from '../VideoUploadService'

interface Call {
  method: string
  path: string
  body: BodyInit | null | undefined
}

type Reply = { status?: number; json?: unknown } | 'network'

/** API simulée : une réponse par appel, dans l'ordre ; « network » simule une coupure */
function scriptedApi(replies: Reply[]): { api: ApiClient; calls: Call[] } {
  const calls: Call[] = []
  const fetchFn: typeof fetch = async (input, init) => {
    calls.push({ method: init?.method ?? 'GET', path: String(input), body: init?.body })
    const reply = replies.shift()
    if (reply === undefined) throw new Error(`appel inattendu : ${String(input)}`)
    if (reply === 'network') throw new TypeError('Failed to fetch')
    if (reply.status === 204) return new Response(null, { status: 204 })
    return new Response(JSON.stringify(reply.json ?? {}), { status: reply.status ?? 200, headers: { 'Content-Type': 'application/json' } })
  }
  return { api: new ApiClient('', fetchFn, () => 'fr'), calls }
}

function video(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'v1', status: 'envoi', fileName: 'IMG_2041.MOV', size: 10, received: 0, durationMs: 4200, width: null, height: null,
    videoUrl: null, av1Url: null, posterUrl: null, failure: null, hidden: false, hiddenBy: null, uploader: 'Présidente',
    uploadedAt: '2026-11-21T20:30:00+01:00', mine: true, ...overrides,
  }
}

const file = new File(['0123456789'], 'IMG_2041.MOV', { type: 'video/quicktime' })
const noWait = async (): Promise<void> => {}

describe('VideoUploadService', () => {
  it('sends the video in chunks and reports what the server received', async () => {
    const { api, calls } = scriptedApi([
      { status: 201, json: { ...video(), chunkSize: 4 } },
      { json: video({ received: 4 }) },
      { json: video({ received: 8 }) },
      { json: video({ received: 10, status: 'conversion' }) },
    ])
    const progress: number[] = []

    const result = await new VideoUploadService(api, noWait).upload('e1', file, { durationMs: 4200, onProgress: (n) => progress.push(n) })

    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual([
      'POST /api/events/e1/videos',
      'PUT /api/event-videos/v1/chunks/0',
      'PUT /api/event-videos/v1/chunks/4',
      'PUT /api/event-videos/v1/chunks/8',
    ])
    expect(calls[1]?.body).toBeInstanceOf(Blob)
    expect(progress).toEqual([4, 8, 10])
    expect(result.status).toBe('conversion')
  })

  it('resumes from the server position after a cut or a duplicate chunk', async () => {
    const { api, calls } = scriptedApi([
      { status: 201, json: { ...video(), chunkSize: 4 } },
      'network', // morceau 0 perdu en route…
      { json: video({ received: 4 }) }, // … mais reçu par le serveur
      { status: 409, json: { message: 'L’envoi reprend à 8 octets.' } },
      { json: video({ received: 8 }) },
      { json: video({ received: 10, status: 'prete', videoUrl: '/api/event-videos/v1/file' }) },
    ])

    const result = await new VideoUploadService(api, noWait).upload('e1', file, { durationMs: 0, onProgress: () => {} })

    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual([
      'POST /api/events/e1/videos',
      'PUT /api/event-videos/v1/chunks/0',
      'GET /api/event-videos/v1',
      'PUT /api/event-videos/v1/chunks/4',
      'GET /api/event-videos/v1',
      'PUT /api/event-videos/v1/chunks/8',
    ])
    expect(result.isReady).toBe(true)
  })

  it('stops at once on a refusal the server explains', async () => {
    const { api } = scriptedApi([{ status: 422, json: { message: 'Une vidéo dure 60 secondes au plus.' } }])

    await expect(new VideoUploadService(api, noWait).upload('e1', file, { durationMs: 90_000, onProgress: () => {} })).rejects.toMatchObject({ status: 422 })
  })

  it('waits for the conversion to finish', async () => {
    const { api } = scriptedApi([{ json: video({ status: 'conversion', received: 10 }) }, { json: video({ status: 'echec', received: 10, failure: 'trop-longue' }) }])
    const waits: number[] = []

    const result = await new VideoUploadService(api, async (ms) => void waits.push(ms)).waitForConversion(GalleryVideo.parse(video({ status: 'conversion', received: 10 })))

    expect(result.failure).toBe('trop-longue')
    expect(waits).toEqual([4000, 4000])
  })

  it('accepts the formats phones record and nothing else', () => {
    expect(VideoUploadService.isVideo(file)).toBe(true)
    expect(VideoUploadService.isVideo(new File([''], 'clip.webm', { type: 'video/webm' }))).toBe(true)
    expect(VideoUploadService.isVideo(new File([''], 'playlist.m3u8', { type: 'application/vnd.apple.mpegurl' }))).toBe(false)
  })
})

describe('gallery models', () => {
  it('merges photos and videos in arrival order', () => {
    const photo = { id: 'p1', uploadedAt: new Date('2026-11-21T21:00:00+01:00') } as EventPhoto
    const clip = GalleryVideo.parse(video({ status: 'prete' }))

    expect(galleryItems([photo], [clip]).map((item) => item.key)).toEqual(['video-v1', 'photo-p1'])
    expect(clip.durationLabel).toBe('0:04')
  })

  it('reads the rights, nothing beyond viewing with an older API', () => {
    expect(parseGalleryRights(undefined)).toEqual({ canAddPhotos: false, canAddVideos: false, canModerate: false })
    expect(parseGalleryRights({ canAddPhotos: true, canAddVideos: true, canModerate: false }).canAddVideos).toBe(true)
  })

  it('asks for a removal and restores through the media address', async () => {
    const { api, calls } = scriptedApi([{ status: 204 }, { status: 204 }])
    const events = new EventService(api)

    await events.requestRemoval({ kind: 'photo', id: 'p1' })
    await events.restoreMedia({ kind: 'video', id: 'v1' })

    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual(['POST /api/event-photos/p1/removal-request', 'POST /api/event-videos/v1/restore'])
  })
})
