import { onBeforeUnmount, onMounted } from 'vue'

/** Durée d'affichage avant de compter une vue : écarte préchargements et passages éclair */
const DELAY_MS = 2000

/**
 * Signale une vue une fois la page restée visible 2 secondes (mesure d'audience
 * sans cookie). Un onglet ouvert en arrière-plan n'est compté qu'une fois affiché.
 * Les erreurs sont ignorées : la mesure ne doit jamais gêner la page.
 */
export function useViewBeacon(send: () => Promise<void>): void {
  let timer = 0
  let done = false

  const arm = () => {
    window.clearTimeout(timer)
    if (done || document.visibilityState !== 'visible') return
    timer = window.setTimeout(() => {
      done = true
      document.removeEventListener('visibilitychange', arm)
      send().catch(() => undefined)
    }, DELAY_MS)
  }

  onMounted(() => {
    document.addEventListener('visibilitychange', arm)
    arm()
  })

  onBeforeUnmount(() => {
    window.clearTimeout(timer)
    document.removeEventListener('visibilitychange', arm)
  })
}
