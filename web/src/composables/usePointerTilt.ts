import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import { motionEnabled } from '@/preferences/MotionController'

/**
 * Inclinaison 3D d'un élément qui suit le pointeur (souris ou stylet uniquement).
 * Écrit --tilt-x / --tilt-y (en degrés) ; aucun effet si les animations sont coupées.
 */
export function usePointerTilt(target: Ref<HTMLElement | null>, maxDegrees = 9): void {
  let frame = 0
  let element: HTMLElement | null = null

  const set = (x: number, y: number) => {
    cancelAnimationFrame(frame)
    frame = requestAnimationFrame(() => {
      element?.style.setProperty('--tilt-x', `${(-y * maxDegrees).toFixed(2)}deg`)
      element?.style.setProperty('--tilt-y', `${(x * maxDegrees).toFixed(2)}deg`)
    })
  }

  const onMove = (event: PointerEvent) => {
    if (!element || event.pointerType === 'touch' || !motionEnabled()) return
    const box = element.getBoundingClientRect()
    set((event.clientX - box.left) / box.width - 0.5, (event.clientY - box.top) / box.height - 0.5)
  }
  const onLeave = () => set(0, 0)

  onMounted(() => {
    element = target.value
    if (!element || typeof window.matchMedia !== 'function' || !window.matchMedia('(pointer: fine)').matches) return
    element.addEventListener('pointermove', onMove)
    element.addEventListener('pointerleave', onLeave)
  })

  onBeforeUnmount(() => {
    cancelAnimationFrame(frame)
    element?.removeEventListener('pointermove', onMove)
    element?.removeEventListener('pointerleave', onLeave)
  })
}
