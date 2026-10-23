/**
 * Horloge injectée : aucun code ne lit l'heure système directement, pour des
 * tests déterministes (horloge figée) et un seul point de vérité.
 */
export interface Clock {
  now(): Date
}

/** Horloge réelle du navigateur (sans état) */
export const systemClock: Clock = { now: () => new Date() }

/** Horloge arrêtée sur un instant (tests) */
export function fixedClock(at: Date): Clock {
  return { now: () => new Date(at.getTime()) }
}

/** Date du jour au format d'un <input type="date">, à l'heure locale */
export function toDateInput(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
