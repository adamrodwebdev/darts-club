/**
 * Imprime une partie de la page : <html data-print="…"> le temps de l'impression, que visent les
 * règles @media print de cette partie (le reste de la page n'en sort pas).
 */
export function printOnly(target: string, after?: () => void): void {
  const root = document.documentElement
  root.dataset.print = target
  window.addEventListener(
    'afterprint',
    () => {
      root.removeAttribute('data-print')
      after?.()
    },
    { once: true },
  )
  window.print()
}
