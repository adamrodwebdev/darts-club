import { motionEnabled } from '@/preferences/MotionController'

/** Bien à l'écran : à plus de 15 % des bords haut et bas de la fenêtre, quelle que soit la hauteur de l'élément */
export const WELL_IN_VIEW: IntersectionObserverInit = { rootMargin: '-15% 0px -15% 0px' }

/**
 * Animations d'un élément rejouées à chaque retour à l'écran. Sorti de l'écran, les animations
 * CSS à durée de l'élément et de ses descendants attendent à leur début ; revenu bien à l'écran
 * (`enter`), elles repartent de zéro. La remise au début se fait toujours hors de la vue : aucun
 * saut visible. Celles liées au défilement et les transitions ne sont pas touchées ; animations
 * coupées : rien n'est retenu.
 */
export class InViewReplay {
  private held: Animation[] = []
  private readonly observers: IntersectionObserver[]

  constructor(
    private readonly element: Element,
    enter: IntersectionObserverInit = WELL_IN_VIEW,
  ) {
    const minRatio = Math.min(...[enter.threshold ?? 0].flat())
    this.observers = [
      new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting && entry.intersectionRatio >= minRatio)) this.play()
      }, enter),
      new IntersectionObserver((entries) => {
        if (entries.every((entry) => !entry.isIntersecting)) this.hold()
      }),
    ]
    for (const observer of this.observers) observer.observe(element)
  }

  /** Fin de l'observation : ce qui attendait repart */
  disconnect(): void {
    for (const observer of this.observers) observer.disconnect()
    this.play()
  }

  private hold(): void {
    if (!motionEnabled()) return
    this.held = timedAnimations(this.element)
    for (const animation of this.held) {
      animation.pause()
      animation.currentTime = 0
    }
  }

  private play(): void {
    for (const animation of this.held) animation.play()
    this.held = []
  }
}

/** Animations CSS de l'élément et de ses descendants qui suivent l'horloge de la page (ni défilement, ni transitions) */
function timedAnimations(element: Element): Animation[] {
  if (typeof element.getAnimations !== 'function') return []
  return element.getAnimations({ subtree: true }).filter((animation) => 'animationName' in animation && animation.timeline === document.timeline)
}
