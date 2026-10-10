import type { PreferenceStorage } from './PreferenceStorage'

export const THEME_PREFERENCES = ['system', 'light', 'dark'] as const
export type ThemePreference = (typeof THEME_PREFERENCES)[number]
export type Theme = 'light' | 'dark'

/** Même clé et mêmes couleurs que public/theme-init.js (exécuté avant le rendu). */
export const THEME_STORAGE_KEY = 'sd71.theme'
const THEME_COLORS: Record<Theme, string> = { dark: '#0b0b0d', light: '#f6f6f8' }

/**
 * Thème clair / sombre : préférence mémorisée, ou celle du système par défaut.
 * Le thème résolu est posé sur <html data-theme>, que les jetons CSS suivent.
 */
export class ThemeController {
  constructor(
    private readonly root: HTMLElement,
    private readonly storage: PreferenceStorage,
    /** Requête « (prefers-color-scheme: light) », absente hors navigateur */
    private readonly prefersLight: MediaQueryList | null,
  ) {}

  get preference(): ThemePreference {
    const stored = this.storage.get(THEME_STORAGE_KEY)
    return (THEME_PREFERENCES as readonly string[]).includes(stored ?? '') ? (stored as ThemePreference) : 'system'
  }

  resolve(preference: ThemePreference): Theme {
    if (preference !== 'system') return preference
    return this.prefersLight?.matches ? 'light' : 'dark'
  }

  /** Enregistre la préférence et l'applique ; renvoie le thème affiché. */
  choose(preference: ThemePreference): Theme {
    this.storage.set(THEME_STORAGE_KEY, preference)
    return this.apply(preference)
  }

  apply(preference: ThemePreference): Theme {
    const theme = this.resolve(preference)
    this.root.dataset.theme = theme
    this.root.ownerDocument.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_COLORS[theme])
    return theme
  }

  /** Suit les changements du thème système ; renvoie la fonction de désabonnement. */
  onSystemChange(listener: () => void): () => void {
    this.prefersLight?.addEventListener('change', listener)
    return () => this.prefersLight?.removeEventListener('change', listener)
  }
}
