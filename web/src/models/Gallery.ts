import { formatClock } from '@/i18n/format'
import type { EventPhoto } from './ClubEvent'
import { bool, InvalidResponseError, num, obj, optStr, str } from './guards'

/** Petite image d'une case (vignette de la photo, aperçu de la vidéo ; aucune tant que la vidéo n'est pas convertie) */
export interface GalleryPreview {
  url: string | null
  width: number | undefined
  height: number | undefined
}

interface GalleryEntry {
  key: string
  at: Date
  preview: GalleryPreview
}

/**
 * Une case de la galerie, photo ou vidéo, dans l'ordre d'arrivée. `media` porte ce que les deux ont en
 * commun (auteur, état, identifiant) : l'affichage n'a pas à demander de quelle sorte elle est.
 */
export type GalleryItem = (GalleryEntry & { kind: 'photo'; photo: EventPhoto; media: EventPhoto }) | (GalleryEntry & { kind: 'video'; video: GalleryVideo; media: GalleryVideo })

export function galleryItems(photos: readonly EventPhoto[], videos: readonly GalleryVideo[]): GalleryItem[] {
  const items: GalleryItem[] = [
    ...photos.map(
      (photo): GalleryItem => ({
        kind: 'photo',
        key: `photo-${photo.id}`,
        at: photo.uploadedAt,
        photo,
        media: photo,
        preview: { url: photo.thumbUrl, width: photo.width, height: photo.height },
      }),
    ),
    ...videos.map(
      (video): GalleryItem => ({
        kind: 'video',
        key: `video-${video.id}`,
        at: video.uploadedAt,
        video,
        media: video,
        preview: { url: video.posterUrl, width: video.width ?? undefined, height: video.height ?? undefined },
      }),
    ),
  ]
  return items.sort((a, b) => a.at.getTime() - b.at.getTime())
}

/** Sortes de médias de la galerie */
export type MediaKind = 'photo' | 'video'

/** Libellés de chaque sorte de média (clés de traduction) : une seule table, aucun test répété */
export const GALLERY_LABELS = {
  photo: { item: 'events.gallery.itemPhoto', position: 'events.gallery.position', deleteTitle: 'events.gallery.deletePhoto' },
  video: { item: 'events.gallery.itemVideo', position: 'events.gallery.positionVideo', deleteTitle: 'events.gallery.deleteVideo' },
} as const satisfies Record<MediaKind, { item: string; position: string; deleteTitle: string }>

/** Étapes d'une vidéo : envoi par morceaux, conversion par le serveur, prête, échec */
export type VideoStatus = 'envoi' | 'conversion' | 'prete' | 'echec'

/** Raison d'un refus du serveur, expliquée dans la langue de l'adhérent */
export type VideoFailure = 'trop-longue' | 'trop-grande' | 'illisible'

const STATUSES: readonly VideoStatus[] = ['envoi', 'conversion', 'prete', 'echec']
const FAILURES: readonly VideoFailure[] = ['trop-longue', 'trop-grande', 'illisible']

/** Ce que l'adhérent peut faire dans la galerie d'un événement (décidé par l'API) */
export interface GalleryRights {
  canAddPhotos: boolean
  canAddVideos: boolean
  canModerate: boolean
}

/** API plus ancienne, sans ces droits : rien de plus que regarder */
export function parseGalleryRights(value: unknown): GalleryRights {
  if (value === undefined || value === null) return { canAddPhotos: false, canAddVideos: false, canModerate: false }
  const d = obj(value, 'droits de la galerie')
  return { canAddPhotos: bool(d.canAddPhotos), canAddVideos: bool(d.canAddVideos), canModerate: bool(d.canModerate) }
}

interface GalleryVideoOptions {
  id: string
  status: VideoStatus
  fileName: string
  size: number
  received: number
  durationMs: number
  width: number | null
  height: number | null
  videoUrl: string | null
  av1Url: string | null
  posterUrl: string | null
  failure: VideoFailure | null
  hidden: boolean
  hiddenBy: string | null
  uploader: string
  uploadedAt: Date
  mine: boolean
}

/** Vidéo courte de la galerie : MP4 H.264 pour tous, AV1 plus léger quand le serveur l'a produit. */
export class GalleryVideo {
  readonly id: string
  readonly status: VideoStatus
  readonly fileName: string
  readonly size: number
  readonly received: number
  readonly durationMs: number
  readonly width: number | null
  readonly height: number | null
  readonly videoUrl: string | null
  readonly av1Url: string | null
  readonly posterUrl: string | null
  readonly failure: VideoFailure | null
  readonly hidden: boolean
  readonly hiddenBy: string | null
  readonly uploader: string
  readonly uploadedAt: Date
  readonly mine: boolean

  constructor(options: GalleryVideoOptions) {
    this.id = options.id
    this.status = options.status
    this.fileName = options.fileName
    this.size = options.size
    this.received = options.received
    this.durationMs = options.durationMs
    this.width = options.width
    this.height = options.height
    this.videoUrl = options.videoUrl
    this.av1Url = options.av1Url
    this.posterUrl = options.posterUrl
    this.failure = options.failure
    this.hidden = options.hidden
    this.hiddenBy = options.hiddenBy
    this.uploader = options.uploader
    this.uploadedAt = options.uploadedAt
    this.mine = options.mine
  }

  static parse(value: unknown): GalleryVideo {
    const d = obj(value, 'vidéo')
    const status = str(d.status)
    if (!STATUSES.includes(status as VideoStatus)) throw new InvalidResponseError(`état de vidéo inconnu : ${status}`)
    const failure = optStr(d.failure)
    return new GalleryVideo({
      id: str(d.id),
      status: status as VideoStatus,
      fileName: str(d.fileName),
      size: num(d.size),
      received: num(d.received),
      durationMs: num(d.durationMs),
      width: d.width === null ? null : num(d.width),
      height: d.height === null ? null : num(d.height),
      videoUrl: optStr(d.videoUrl),
      av1Url: optStr(d.av1Url),
      posterUrl: optStr(d.posterUrl),
      failure: failure !== null && FAILURES.includes(failure as VideoFailure) ? (failure as VideoFailure) : null,
      hidden: d.hidden === true,
      hiddenBy: optStr(d.hiddenBy),
      uploader: str(d.uploader),
      uploadedAt: new Date(str(d.uploadedAt)),
      mine: d.mine === true,
    })
  }

  get isReady(): boolean {
    return this.status === 'prete'
  }

  /** 0:42 ; vide tant que le serveur ne l'a pas mesurée */
  get durationLabel(): string {
    return this.durationMs > 0 ? formatClock(this.durationMs / 1000) : ''
  }
}
