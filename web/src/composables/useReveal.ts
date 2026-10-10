import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import { motionEnabled } from '@/preferences/MotionController'

/**
 * Révèle les éléments [data-reveal] d'un conteneur à leur entrée dans l'écran.
 * Seuls opacity/transform sont animés (aucun décalage de mise en page) ;
 * sans IntersectionObserver ou animations coupées (préférence du site ou de l'appareil), tout est visible.
 */
export function useReveal(root: Ref<HTMLElement | null>): void {
  let observer: IntersectionObserver | null = null

  onMounted(() => {
    const elements = root.value?.querySelectorAll<HTMLElement>('[data-reveal]') ?? []
    if (!motionEnabled() || typeof IntersectionObserver === 'undefined') {
      elements.forEach((el) => el.classList.add('is-revealed'))
      return
    }

    // Masquage seulement une fois le script actif : sans JS, tout reste visible
    root.value?.classList.add('reveal-armed')
    observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-revealed')
            observer?.unobserve(entry.target)
          }
        }
      },
      { rootMargin: '0px 0px -10% 0px' },
    )
    elements.forEach((el) => observer?.observe(el))
  })

  onBeforeUnmount(() => observer?.disconnect())
}
