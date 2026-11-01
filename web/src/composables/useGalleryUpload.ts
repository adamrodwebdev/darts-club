import { ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/ApiError'
import { formatClock, formatMegabytes, formatPercent } from '@/i18n/format'
import type { GalleryVideo, VideoFailure } from '@/models/Gallery'
import { useServices } from '@/services'
import { VideoUploadService } from '@/services/VideoUploadService'

export type UploadTone = 'progress' | 'done' | 'error'

/** Une ligne du panneau d'envoi, remplacée à chaque étape (jamais modifiée sur place) */
export interface UploadLine {
  readonly key: string
  readonly name: string
  readonly meta: string
  /** 0 à 1 ; null pendant une étape sans mesure (conversion) */
  readonly progress: number | null
  readonly status: string
  readonly tone: UploadTone
}

const PHOTO_TYPES: readonly string[] = ['image/jpeg', 'image/png', 'image/webp']
const PHOTO_MAX_BYTES = 10 * 1024 * 1024
const PHOTOS_PER_BATCH = 10
/** Arrondi des téléphones : 60,4 s passe, 61 s non (le serveur mesure de toute façon) */
const DURATION_TOLERANCE_MS = 999

const FAILURES = {
  'trop-longue': 'events.gallery.failure.tooLong',
  'trop-grande': 'events.gallery.failure.oversized',
  illisible: 'events.gallery.failure.unreadable',
} as const satisfies Record<VideoFailure, string>

/**
 * Envoi de photos et de vidéos vers la galerie, avec un panneau qui suit chaque fichier : contrôles
 * avant l'envoi (format, poids, durée), progression, reprise après une coupure, conversion.
 */
export function useGalleryUpload(eventId: () => string, onChanged: () => void): { lines: Ref<readonly UploadLine[]>; add: (files: File[]) => Promise<void> } {
  const services = useServices()
  const { t } = useI18n()
  const lines = ref<readonly UploadLine[]>([])
  let counter = 0

  function show(line: UploadLine): void {
    lines.value = lines.value.some((l) => l.key === line.key) ? lines.value.map((l) => (l.key === line.key ? line : l)) : [...lines.value, line]
  }

  function failed(base: Omit<UploadLine, 'progress' | 'status' | 'tone'>, status: string): void {
    show({ ...base, progress: null, status, tone: 'error' })
  }

  function errorText(error: unknown): string {
    return error instanceof ApiError ? error.message : t('errors.network')
  }

  async function sendPhotos(batch: File[]): Promise<void> {
    const base = { key: `photos-${++counter}`, name: t('events.gallery.photosBatch', batch.length), meta: formatMegabytes(batch.reduce((sum, f) => sum + f.size, 0)) }
    show({ ...base, progress: null, status: t('events.gallery.status.sending'), tone: 'progress' })
    try {
      await services.events.uploadPhotos(eventId(), batch)
      show({ ...base, progress: 1, status: t('events.gallery.status.photosDone'), tone: 'done' })
      onChanged()
    } catch (error) {
      failed(base, errorText(error))
    }
  }

  async function sendVideo(file: File): Promise<void> {
    const key = `video-${++counter}`
    const sizeLabel = formatMegabytes(file.size)
    const checking = { key, name: file.name, meta: sizeLabel }
    if (file.size > VideoUploadService.MAX_BYTES) return failed(checking, t('events.gallery.failure.tooHeavy'))

    show({ ...checking, progress: null, status: t('events.gallery.status.checking'), tone: 'progress' })
    const duration = await services.media.videoDurationMs(file)
    if (duration !== null && duration > VideoUploadService.MAX_DURATION_MS + DURATION_TOLERANCE_MS) {
      return failed({ ...checking, meta: `${sizeLabel} · ${formatClock(duration / 1000)}` }, t('events.gallery.failure.tooLong'))
    }

    const base = { ...checking, meta: duration === null ? sizeLabel : `${sizeLabel} · ${formatClock(duration / 1000)}` }
    const progress = (received: number): void =>
      show({
        ...base,
        progress: received / file.size,
        status: t('events.gallery.status.sendingPercent', { percent: formatPercent(received / file.size) }),
        tone: 'progress',
      })
    try {
      progress(0)
      let video: GalleryVideo = await services.videoUploads.upload(eventId(), file, {
        durationMs: Math.min(duration ?? 0, VideoUploadService.MAX_DURATION_MS),
        onProgress: progress,
      })
      if (video.status === 'conversion') {
        show({ ...base, progress: null, status: t('events.gallery.status.converting'), tone: 'progress' })
        onChanged()
        video = await services.videoUploads.waitForConversion(video)
      }
      if (video.isReady) {
        show({ ...base, progress: 1, status: t('events.gallery.status.done'), tone: 'done' })
        onChanged()
      } else {
        failed(base, t(FAILURES[video.failure ?? 'illisible']))
        onChanged()
      }
    } catch (error) {
      failed(base, errorText(error))
    }
  }

  async function add(files: File[]): Promise<void> {
    const photos: File[] = []
    for (const file of files) {
      if (PHOTO_TYPES.includes(file.type)) {
        if (file.size > PHOTO_MAX_BYTES) failed({ key: `photo-${++counter}`, name: file.name, meta: formatMegabytes(file.size) }, t('events.gallery.failure.photoTooHeavy'))
        else photos.push(file)
      } else if (!VideoUploadService.isVideo(file)) {
        failed({ key: `file-${++counter}`, name: file.name, meta: formatMegabytes(file.size) }, t('events.gallery.failure.unsupported'))
      }
    }

    for (let i = 0; i < photos.length; i += PHOTOS_PER_BATCH) await sendPhotos(photos.slice(i, i + PHOTOS_PER_BATCH))
    // Une vidéo après l'autre : le réseau et la conversion ne sont jamais saturés
    for (const file of files.filter((f) => VideoUploadService.isVideo(f))) await sendVideo(file)
  }

  return { lines, add }
}
