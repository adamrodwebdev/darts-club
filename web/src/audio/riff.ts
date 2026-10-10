/** Partition du riff de « Triple Vingt » (même notes que la tablature). */
export interface RiffStep {
  /** Note MIDI (36 = do grave, corde à vide en drop C), null = silence */
  note: number | null
  /** Durée en croches */
  eighths: number
}

export const RIFF_TEMPO = 92

/** C C C · B♭ C · E♭ F E♭ · C B♭ · C C E♭ D♭ — gamme blues de do */
export const RIFF: readonly RiffStep[] = [
  { note: 36, eighths: 1 },
  { note: 36, eighths: 1 },
  { note: 36, eighths: 2 },
  { note: 46, eighths: 1 },
  { note: 48, eighths: 1 },
  { note: 51, eighths: 1 },
  { note: 53, eighths: 1 },
  { note: 51, eighths: 2 },
  { note: 48, eighths: 1 },
  { note: 46, eighths: 1 },
  { note: 36, eighths: 1 },
  { note: 36, eighths: 1 },
  { note: 39, eighths: 1 },
  { note: 37, eighths: 1 },
]

export function midiToFrequency(note: number): number {
  return 440 * 2 ** ((note - 69) / 12)
}

export function eighthDuration(tempo = RIFF_TEMPO): number {
  return 60 / tempo / 2
}

/** Durée d'un tour de riff, en secondes */
export function riffDuration(tempo = RIFF_TEMPO): number {
  return RIFF.reduce((total, step) => total + step.eighths, 0) * eighthDuration(tempo)
}
