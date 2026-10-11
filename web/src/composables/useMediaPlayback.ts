import { onBeforeUnmount, onMounted, readonly, ref, type Ref } from 'vue'

export interface MediaPlayback {
  playing: Readonly<Ref<boolean>>
  /** Position de lecture, en secondes */
  time: Readonly<Ref<number>>
  /** Lecture lancée au moins une fois (la position a un sens) */
  started: Readonly<Ref<boolean>>
  failed: Readonly<Ref<boolean>>
  toggle(): Promise<void>
  seek(seconds: number): void
  /** Se place à `seconds` puis lance la lecture */
  playFrom(seconds: number): Promise<void>
}

/**
 * État réactif d'un élément <audio>/<video> : rien n'est téléchargé avant la
 * première lecture (preload="none" côté gabarit), le navigateur lit ensuite le
 * fichier par plages (Range), d'où un démarrage immédiat même au milieu du morceau.
 */
export function useMediaPlayback(target: Ref<HTMLMediaElement | null>): MediaPlayback {
  const playing = ref(false)
  const time = ref(0)
  const started = ref(false)
  const failed = ref(false)
  let element: HTMLMediaElement | null = null

  const listeners: [string, () => void][] = [
    ['play', () => { playing.value = true; started.value = true }],
    ['pause', () => { playing.value = false }],
    ['ended', () => { playing.value = false }],
    ['timeupdate', () => { time.value = element?.currentTime ?? 0 }],
    ['error', () => { playing.value = false; failed.value = true }],
  ]

  async function play(): Promise<void> {
    if (!element) return
    try {
      failed.value = false
      await element.play()
    } catch (error) {
      // Lecture interrompue par une pause : pas une erreur
      if (!(error instanceof DOMException && error.name === 'AbortError')) failed.value = true
    }
  }

  function seek(seconds: number): void {
    if (!element) return
    element.currentTime = seconds
    time.value = seconds
    started.value = true
  }

  onMounted(() => {
    element = target.value
    for (const [name, listener] of listeners) element?.addEventListener(name, listener)
  })

  onBeforeUnmount(() => {
    element?.pause()
    for (const [name, listener] of listeners) element?.removeEventListener(name, listener)
  })

  return {
    playing: readonly(playing),
    time: readonly(time),
    started: readonly(started),
    failed: readonly(failed),
    async toggle() {
      if (element && !element.paused) element.pause()
      else await play()
    },
    seek,
    async playFrom(seconds) {
      seek(seconds)
      await play()
    },
  }
}
