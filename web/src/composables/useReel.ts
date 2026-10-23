import { nextTick, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'
import { motionEnabled } from '@/preferences/MotionController'

/** Bande de cartes qui défile de côté : carte en place, défilement possible, navigation */
export interface Reel {
  /** Index de la carte calée au point d'arrêt (centre ou début de la bande) */
  readonly active: Ref<number>
  /** Le contenu dépasse : la navigation est utile */
  readonly scrollable: Ref<boolean>
  goTo(index: number): void
  next(): void
  previous(): void
}

export interface ReelOptions {
  /** Nombre de cartes (relu quand la liste change) */
  count: () => number
  /** Calage des cartes, comme leur scroll-snap-align */
  align: 'start' | 'center'
}

/**
 * Suit une bande défilant horizontalement (scroll-snap) dont les cartes portent
 * data-reel-index. La carte en place est celle dont le point de calage est le plus
 * proche de celui de la bande, mesuré sur la mise en page (offsetLeft) : les cartes
 * inclinées ou superposées du coverflow ne faussent rien. Une mesure par image au plus ;
 * la navigation fait défiler la bande seule, jamais la page.
 */
export function useReel(track: Ref<HTMLElement | null>, options: ReelOptions): Reel {
  const active = ref(0)
  const scrollable = ref(false)
  let paddingStart = 0
  let frame = 0
  /** Carte demandée par la navigation, tant que le défilement doux ne l'a pas atteinte */
  let target: number | null = null
  let resizes: ResizeObserver | null = null

  function cards(): HTMLElement[] {
    return Array.from(track.value?.querySelectorAll<HTMLElement>(':scope > [data-reel-index]') ?? [])
  }

  function anchorOf(card: HTMLElement): number {
    return options.align === 'center' ? card.offsetLeft + card.offsetWidth / 2 : card.offsetLeft - paddingStart
  }

  function inPlace(): number {
    const element = track.value
    const all = cards()
    if (element === null || all.length === 0) return 0
    // Bout de la bande atteint : la dernière carte ne peut pas venir au point de calage
    if (element.scrollLeft >= element.scrollWidth - element.clientWidth - 1) return all.length - 1
    const anchor = options.align === 'center' ? element.scrollLeft + element.clientWidth / 2 : element.scrollLeft
    let best = 0
    let bestDistance = Number.POSITIVE_INFINITY
    all.forEach((card, index) => {
      const distance = Math.abs(anchorOf(card) - anchor)
      if (distance < bestDistance) {
        best = index
        bestDistance = distance
      }
    })
    return best
  }

  function measure(): void {
    const element = track.value
    if (element === null) return
    scrollable.value = element.scrollWidth > element.clientWidth + 1
    paddingStart = Number.parseFloat(getComputedStyle(element).scrollPaddingInlineStart) || 0
    active.value = inPlace()
  }

  function onScroll(): void {
    if (frame !== 0) return
    frame = requestAnimationFrame(() => {
      frame = 0
      active.value = inPlace()
      if (active.value === target) target = null
    })
  }

  /** Le visiteur reprend la main (doigt, souris, molette) : on oublie la carte demandée */
  function release(): void {
    target = null
  }

  function goTo(index: number): void {
    const element = track.value
    const clamped = Math.min(Math.max(index, 0), options.count() - 1)
    const card = cards()[clamped]
    if (element === null || card === undefined) return
    target = clamped
    const left = options.align === 'center' ? anchorOf(card) - element.clientWidth / 2 : anchorOf(card)
    element.scrollTo({ left, behavior: motionEnabled() ? 'smooth' : 'auto' })
  }

  onMounted(() => {
    measure()
    track.value?.addEventListener('scroll', onScroll, { passive: true })
    track.value?.addEventListener('pointerdown', release, { passive: true })
    track.value?.addEventListener('wheel', release, { passive: true })
    if (track.value !== null && typeof ResizeObserver !== 'undefined') {
      resizes = new ResizeObserver(measure)
      resizes.observe(track.value)
    }
  })

  // Cartes chargées après coup (événements) : nouvelle mesure
  watch(options.count, async () => {
    await nextTick()
    measure()
  })

  onBeforeUnmount(() => {
    track.value?.removeEventListener('scroll', onScroll)
    track.value?.removeEventListener('pointerdown', release)
    track.value?.removeEventListener('wheel', release)
    resizes?.disconnect()
    cancelAnimationFrame(frame)
  })

  return {
    active,
    scrollable,
    goTo,
    next: () => goTo((target ?? active.value) + 1),
    previous: () => goTo((target ?? active.value) - 1),
  }
}
