import type { PreferenceStorage } from './PreferenceStorage'

export const MOTION_PREFERENCES = ['system', 'on', 'off'] as const
export type MotionPreference = (typeof MOTION_PREFERENCES)[number]
export type Motion = 'on' | 'off'

/** Même clé que public/theme-init.js (exécuté avant le rendu). */
export const MOTION_STORAGE_KEY = 'sd71.motion'

/**
 * Animations et effets de texte : choix mémorisé, sinon réglage « réduire les
 * animations » de l'appareil. Le résultat est posé sur <html data-motion>.
 */
export class MotionController {
  constructor(
    private readonly root: HTMLElement,
    private readonly storage: PreferenceStorage,
    /** Requête « (prefers-reduced-motion: reduce) », absente hors navigateur */
    private readonly prefersReduced: MediaQueryList | null,
  ) {}

  get preference(): MotionPreference {
    const stored = this.storage.get(MOTION_STORAGE_KEY)
    return (MOTION_PREFERENCES as readonly string[]).includes(stored ?? '') ? (stored as MotionPreference) : 'system'
  }

  resolve(preference: MotionPreference): Motion {
    if (preference !== 'system') return preference
    return this.prefersReduced?.matches ? 'off' : 'on'
  }

  choose(preference: MotionPreference): Motion {
    this.storage.set(MOTION_STORAGE_KEY, preference)
    return this.apply(preference)
  }

  apply(preference: MotionPreference): Motion {
    const motion = this.resolve(preference)
    this.root.dataset.motion = motion
    return motion
  }

  onSystemChange(listener: () => void): () => void {
    this.prefersReduced?.addEventListener('change', listener)
    return () => this.prefersReduced?.removeEventListener('change', listener)
  }
}

/** Animations actives sur la page (lu par les composables qui animent en JavaScript). */
export function motionEnabled(): boolean {
  return document.documentElement.dataset.motion !== 'off'
}
