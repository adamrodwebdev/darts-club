import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import type { Reel } from './useReel'

/** Déplacement (px) à partir duquel un appui devient un glissé */
const DRAG_START = 6
/** Part de la largeur de la bande qu'un glissé doit parcourir pour changer de carte */
const SWIPE_SHARE = 0.12
/** Images sans mouvement qui disent le défilement doux fini : les points d'arrêt reviennent */
const STILL_FRAMES = 3
/** Au plus tard, quoi qu'il arrive */
const SETTLE_LIMIT = 1500

/**
 * Faire glisser une bande à la souris, comme au doigt : le doigt et le pavé tactile la font défiler
 * nativement, la souris non. Pendant le glissé, la bande suit le pointeur, points d'arrêt levés (posés
 * tout de suite sur l'élément : le premier déplacement ne doit pas être ramené au point d'arrêt) ;
 * au lâcher, elle va à la carte suivante ou précédente selon le sens, ou revient à la sienne, et le
 * clic qui suit le lâcher est avalé (aucune action au bout d'un glissé). Les points d'arrêt reviennent
 * une fois la bande immobile : remis en plein défilement doux, ils la ramèneraient à la carte quittée.
 * Écouteurs natifs : aucun écouteur Vue de plus sur le chemin du clic.
 */
export function useMouseSwipe(track: Ref<HTMLElement | null>, reel: Reel): void {
  let press: { id: number; x: number; left: number; from: number } | null = null
  let dragging = false
  let swallowClick = false
  let settle = 0
  let frame = 0

  function release(): void {
    window.clearTimeout(settle)
    cancelAnimationFrame(frame)
    if (track.value === null) return
    track.value.style.scrollSnapType = ''
    track.value.style.cursor = ''
  }

  /** Relâche quand la position n'a pas bougé pendant quelques images */
  function releaseWhenStill(): void {
    let last = Number.NaN
    let still = 0
    const watch = (): void => {
      const left = track.value?.scrollLeft ?? last
      still = left === last ? still + 1 : 0
      last = left
      if (still >= STILL_FRAMES) release()
      else frame = requestAnimationFrame(watch)
    }
    frame = requestAnimationFrame(watch)
    settle = window.setTimeout(release, SETTLE_LIMIT)
  }

  function onDown(event: PointerEvent): void {
    swallowClick = false
    if (event.pointerType !== 'mouse' || event.button !== 0 || track.value === null) return
    press = { id: event.pointerId, x: event.clientX, left: track.value.scrollLeft, from: reel.active.value }
  }

  function onMove(event: PointerEvent): void {
    const element = track.value
    if (press === null || element === null || event.pointerId !== press.id) return
    const dx = event.clientX - press.x
    if (!dragging) {
      if (Math.abs(dx) < DRAG_START) return
      dragging = true
      window.clearTimeout(settle)
      cancelAnimationFrame(frame)
      element.style.scrollSnapType = 'none'
      element.style.cursor = 'grabbing'
      element.setPointerCapture(press.id)
    }
    element.scrollLeft = press.left - dx
  }

  function onUp(event: PointerEvent): void {
    const element = track.value
    if (press === null || event.pointerId !== press.id) return
    const { x, from } = press
    press = null
    if (!dragging || element === null) return
    dragging = false
    swallowClick = true
    const dx = event.clientX - x
    reel.goTo(from + (Math.abs(dx) > element.clientWidth * SWIPE_SHARE ? -Math.sign(dx) : 0))
    releaseWhenStill()
  }

  function onCancel(): void {
    press = null
    dragging = false
    release()
  }

  function onClick(event: MouseEvent): void {
    if (!swallowClick) return
    swallowClick = false
    event.preventDefault()
    event.stopPropagation()
  }

  onMounted(() => {
    const element = track.value
    element?.addEventListener('pointerdown', onDown)
    element?.addEventListener('pointermove', onMove)
    element?.addEventListener('pointerup', onUp)
    element?.addEventListener('pointercancel', onCancel)
    element?.addEventListener('click', onClick, true)
  })

  onBeforeUnmount(() => {
    const element = track.value
    element?.removeEventListener('pointerdown', onDown)
    element?.removeEventListener('pointermove', onMove)
    element?.removeEventListener('pointerup', onUp)
    element?.removeEventListener('pointercancel', onCancel)
    element?.removeEventListener('click', onClick, true)
    window.clearTimeout(settle)
    cancelAnimationFrame(frame)
  })
}
