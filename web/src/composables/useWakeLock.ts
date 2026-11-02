import { onBeforeUnmount, ref } from 'vue'

/**
 * Écran gardé allumé (tablette de la cuisine, téléphone du bar) tant que l'option est cochée :
 * repris après un retour sur l'onglet, que le navigateur relâche. Sans prise en charge, l'option
 * reste décochée.
 */
export function useWakeLock() {
  const supported = typeof navigator !== 'undefined' && 'wakeLock' in navigator
  const active = ref(false)
  let sentinel: WakeLockSentinel | null = null

  async function acquire(): Promise<void> {
    try {
      sentinel = await navigator.wakeLock.request('screen')
      active.value = true
      sentinel.addEventListener('release', () => {
        if (document.visibilityState === 'visible') active.value = false
      })
    } catch {
      active.value = false
    }
  }

  async function toggle(): Promise<void> {
    if (!supported) return
    if (active.value) {
      active.value = false
      await sentinel?.release()
      sentinel = null
      return
    }
    await acquire()
  }

  const onVisible = () => {
    if (document.visibilityState === 'visible' && active.value && sentinel?.released !== false) void acquire()
  }
  document.addEventListener('visibilitychange', onVisible)
  onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisible)
    void sentinel?.release()
  })

  return { supported, active, toggle }
}
