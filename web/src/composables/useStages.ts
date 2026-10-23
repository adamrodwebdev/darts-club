import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'
import { afterNextFrame } from '@/time/afterNextFrame'

/**
 * Rendu par étapes après le premier affichage : une étape par tâche, pour qu'aucune ne bloque
 * longtemps le fil principal au démarrage (Lighthouse : temps de blocage). Page visible : chaque
 * étape attend aussi l'image suivante, sinon plusieurs étapes enchaînées seraient mises en page
 * d'un coup. `all` affiche tout d'un coup, par exemple pour un lien vers une ancre de la page,
 * dont la cible doit exister tout de suite. `after` retarde la première étape (par exemple
 * jusqu'à la première page affichée), qu'il aboutisse ou échoue.
 */
export function useStages(total: number, options: { all: boolean; after?: Promise<unknown> }): Ref<number> {
  const shown = ref(options.all ? total : 0)
  let cancel: () => void = () => undefined

  function schedule(): void {
    cancel = afterNextFrame(next)
  }

  function next(): void {
    shown.value += 1
    if (shown.value < total) schedule()
  }

  let unmounted = false
  function start(): void {
    if (!unmounted) schedule()
  }

  onMounted(() => {
    if (shown.value >= total) return
    if (options.after) void options.after.then(start, start)
    else start()
  })
  onBeforeUnmount(() => {
    unmounted = true
    cancel()
  })

  return shown
}
