/** Durée maximale du fondu (title-card.css), au-delà de laquelle l'écran titre est retiré quoi qu'il arrive */
const FADE_MS = 600

/**
 * Retire l'écran titre statique de index.html une fois la première page affichée :
 * fondu, ou retrait immédiat si les animations sont coupées.
 */
export function dismissTitleCard(doc: Document): void {
  const card = doc.getElementById('title-card')
  if (card === null) return
  if (doc.documentElement.dataset.motion === 'off') {
    card.remove()
    return
  }
  card.addEventListener('transitionend', () => card.remove(), { once: true })
  card.classList.add('title-card--leaving')
  // Filet : onglet en arrière-plan, où la transition ne se joue pas
  setTimeout(() => card.remove(), FADE_MS)
}
