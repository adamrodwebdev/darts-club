/**
 * Cartes en orbite autour d'un titre, en 3D CSS (les coupures de presse d'une soirée), repris de la
 * démo Plumb : réparties à égalité sur un anneau, à des hauteurs et des inclinaisons alternées pour
 * qu'on lise un nuage de papiers plutôt qu'une roue. Règles pures : aucun DOM, aucune horloge.
 *
 * Le tour de l'anneau (`turn`, en degrés) n'est pas borné : passer de la dernière carte à la
 * première continue dans le même sens, sans refaire le tour à l'envers.
 */
export interface OrbitSlot {
  /** Angle sur l'anneau, en degrés (0 = face au lecteur) */
  readonly angle: number
  /** Décalage vertical, en rem (la carte de devant revient à 0 : voir le CSS) */
  readonly lift: number
  /** Légère inclinaison du papier, en degrés */
  readonly tilt: number
}

const LIFTS = [-3.5, 2.75, -1, 4, -2.75, 1.5, -4, 3.25] as const
const TILTS = [-2.5, 1.5, -1, 2.5, -1.5, 1, -2, 2] as const

export function orbitSlots(count: number): readonly OrbitSlot[] {
  const step = orbitStep(count)
  return Array.from({ length: count }, (_, index) => ({
    angle: index * step,
    lift: LIFTS[index % LIFTS.length] ?? 0,
    tilt: TILTS[index % TILTS.length] ?? 0,
  }))
}

/** Angle entre deux cartes voisines */
export function orbitStep(count: number): number {
  return count > 0 ? 360 / count : 0
}

/** Index ramené dans la série : après la dernière carte vient la première */
export function wrapIndex(index: number, count: number): number {
  return count > 0 ? ((index % count) + count) % count : 0
}

/** La carte la plus proche de l'avant pour un tour d'anneau */
export function frontCard(turn: number, count: number): number {
  if (count <= 0) return 0
  return wrapIndex(Math.round(-turn / orbitStep(count)), count)
}

/** Le tour qui amène la carte devant, choisi au plus près du tour actuel (jamais plus d'un demi-tour) */
export function turnTowards(index: number, count: number, from: number): number {
  if (count <= 0) return 0
  // « 0 − » plutôt qu'un signe moins : la première carte donne 0, jamais −0
  const base = 0 - wrapIndex(index, count) * orbitStep(count)
  return base + 360 * Math.round((from - base) / 360)
}

/**
 * Carte où se pose l'anneau qu'on lâche après un glissé : celle que le geste a amenée devant ; un
 * geste assez long (`swiped`) qui l'a laissée en place passe quand même à la voisine, dans son sens.
 * `dx` : le déplacement horizontal, négatif vers la gauche (la carte de droite vient devant).
 */
export function settleCard(options: { start: number; landed: number; dx: number; swiped: boolean; count: number }): number {
  const { start, landed, dx, swiped, count } = options
  if (landed !== start || !swiped || dx === 0) return landed
  return wrapIndex(start - Math.sign(dx), count)
}
