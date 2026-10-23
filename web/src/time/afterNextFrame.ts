/**
 * Rappel après la prochaine image : la mise en page en attente se fait d'abord, dans sa propre
 * tâche, avant le travail suivant. Page en arrière-plan (images suspendues) : à la tâche suivante.
 * Renvoie de quoi annuler.
 */
export function afterNextFrame(callback: () => void): () => void {
  let timer: ReturnType<typeof setTimeout> | undefined
  let frame: number | undefined
  if (document.visibilityState === 'visible' && typeof requestAnimationFrame === 'function') {
    frame = requestAnimationFrame(() => {
      frame = undefined
      timer = setTimeout(callback)
    })
  } else {
    timer = setTimeout(callback)
  }
  return () => {
    if (frame !== undefined) cancelAnimationFrame(frame)
    clearTimeout(timer)
  }
}
