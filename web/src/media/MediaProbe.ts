/** Lecture des métadonnées d'un fichier choisi, avant l'envoi (navigateur ; un double dans les tests). */
export interface MediaProbe {
  /** Durée d'une vidéo en millisecondes, ou null si le navigateur ne sait pas la lire (HEVC sous Firefox…) */
  videoDurationMs(file: File): Promise<number | null>
}

/** Ne lit que l'en-tête du fichier (preload « metadata »), sans rien décoder ni envoyer. */
export class BrowserMediaProbe implements MediaProbe {
  private static readonly TIMEOUT_MS = 10_000

  constructor(private readonly doc: () => Document = () => document) {}

  videoDurationMs(file: File): Promise<number | null> {
    return new Promise((resolve) => {
      const video = this.doc().createElement('video')
      const url = URL.createObjectURL(file)
      const pending: { timer?: ReturnType<typeof setTimeout> } = {}
      const done = (value: number | null): void => {
        clearTimeout(pending.timer)
        video.onloadedmetadata = null
        video.onerror = null
        video.removeAttribute('src')
        URL.revokeObjectURL(url)
        resolve(value)
      }
      video.preload = 'metadata'
      video.muted = true
      video.onloadedmetadata = () => done(Number.isFinite(video.duration) && video.duration > 0 ? Math.round(video.duration * 1000) : null)
      video.onerror = () => done(null)
      // Certains navigateurs ne répondent jamais pour un format qu'ils ne lisent pas
      pending.timer = setTimeout(() => done(null), BrowserMediaProbe.TIMEOUT_MS)
      video.src = url
    })
  }
}
