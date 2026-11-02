import type { ApiClient } from '@/api/ApiClient'
import { ApiError } from '@/api/ApiError'
import { GalleryVideo } from '@/models/Gallery'
import { num, obj } from '@/models/guards'

/** Attente injectée : réelle dans le navigateur, instantanée dans les tests */
export type Wait = (ms: number) => Promise<void>

export interface VideoUploadOptions {
  /** Durée lue sur l'appareil ; 0 si le navigateur ne sait pas la lire (le serveur la mesure) */
  durationMs: number
  /** Octets reçus par le serveur, après chaque morceau */
  onProgress: (received: number) => void
}

const realWait: Wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * Envoi d'une vidéo par morceaux : une coupure de réseau (4G dans la salle, écran verrouillé) ne fait
 * pas tout recommencer, l'envoi relit la position du serveur et reprend de là, plusieurs fois s'il le faut.
 */
export class VideoUploadService {
  static readonly MAX_BYTES = 500 * 1024 * 1024
  static readonly MAX_DURATION_MS = 60_000
  static readonly TYPES: readonly string[] = ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp', 'video/x-m4v']

  /** De plus en plus espacés : environ 3 minutes de patience au total */
  private static readonly RETRY_DELAYS = [2_000, 4_000, 8_000, 15_000, 30_000, 30_000, 30_000, 30_000, 30_000]
  /** Conversion : interrogée toutes les 4 s, 15 minutes au plus */
  private static readonly POLL_MS = 4_000
  private static readonly POLL_LIMIT = 225

  constructor(
    private readonly api: ApiClient,
    private readonly wait: Wait = realWait,
  ) {}

  static isVideo(file: File): boolean {
    return VideoUploadService.TYPES.includes(file.type)
  }

  async upload(eventId: string, file: File, options: VideoUploadOptions): Promise<GalleryVideo> {
    const started = obj(
      await this.api.post(`/api/events/${encodeURIComponent(eventId)}/videos`, {
        fileName: file.name,
        size: file.size,
        durationMs: Math.max(0, Math.round(options.durationMs)),
        mediaType: file.type,
      }),
    )
    const chunkSize = num(started.chunkSize)
    let video = GalleryVideo.parse(started)
    let failures = 0

    while (video.status === 'envoi') {
      const offset = video.received
      try {
        video = GalleryVideo.parse(await this.api.put(`/api/event-videos/${encodeURIComponent(video.id)}/chunks/${offset}`, file.slice(offset, offset + chunkSize)))
        failures = 0
        options.onProgress(video.received)
      } catch (error) {
        const delay = VideoUploadService.RETRY_DELAYS[failures++]
        if (!VideoUploadService.recoverable(error) || delay === undefined) throw error
        await this.wait(delay)
        video = await this.resync(video)
      }
    }

    return video
  }

  /** Après l'envoi : attend la fin de la conversion (prête ou refusée) */
  async waitForConversion(video: GalleryVideo): Promise<GalleryVideo> {
    let current = video
    for (let round = 0; current.status === 'conversion' && round < VideoUploadService.POLL_LIMIT; round++) {
      await this.wait(VideoUploadService.POLL_MS)
      current = await this.resync(current)
    }
    return current
  }

  /** Position et état lus sur le serveur ; l'ancien état si le réseau manque encore */
  private async resync(video: GalleryVideo): Promise<GalleryVideo> {
    try {
      return GalleryVideo.parse(await this.api.get(`/api/event-videos/${encodeURIComponent(video.id)}`))
    } catch (error) {
      if (VideoUploadService.recoverable(error)) return video
      throw error
    }
  }

  /** Réseau coupé, morceau en double (409), serveur occupé ou redémarré : on réessaie */
  private static recoverable(error: unknown): boolean {
    return error instanceof ApiError && [0, 409, 429, 502, 503, 504].includes(error.status)
  }
}
