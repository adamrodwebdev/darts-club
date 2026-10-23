import type { ObjectDirective } from 'vue'
import { InViewReplay } from './InViewReplay'

const replays = new WeakMap<Element, InViewReplay>()

/**
 * v-replay : les animations de l'élément se rejouent à chaque retour à l'écran (voir InViewReplay).
 * Valeur facultative : la zone où il compte comme revenu (options d'IntersectionObserver).
 */
export const vReplay: ObjectDirective<Element, IntersectionObserverInit | undefined> = {
  mounted(element, binding) {
    if (typeof IntersectionObserver === 'undefined') return
    replays.set(element, new InViewReplay(element, binding.value))
  },
  beforeUnmount(element) {
    replays.get(element)?.disconnect()
    replays.delete(element)
  },
}
