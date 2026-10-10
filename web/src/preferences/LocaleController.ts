import { DEFAULT_LOCALE, HOME_PATHS, isLocale, negotiateLocale, type Locale } from '@/i18n/locales'
import type { PreferenceStorage } from './PreferenceStorage'

export const LOCALE_STORAGE_KEY = 'sd71.locale'

/**
 * Langue de l'interface : choix mémorisé, sinon langue du navigateur.
 * Les pages d'accueil ont une URL par langue (« / » et « /en ») : leur langue
 * vient de l'URL, pour que les moteurs de recherche indexent les deux versions.
 */
export class LocaleController {
  constructor(
    private readonly storage: PreferenceStorage,
    /** Langues préférées du navigateur (navigator.languages) */
    private readonly browserLanguages: () => readonly string[],
  ) {}

  get stored(): Locale | null {
    const value = this.storage.get(LOCALE_STORAGE_KEY)
    return isLocale(value) ? value : null
  }

  /** Langue au chargement de l'application, selon l'URL demandée. */
  initialFor(path: string): Locale {
    const home = (Object.entries(HOME_PATHS) as [Locale, string][]).find(([, homePath]) => homePath === path)
    if (home && home[0] !== DEFAULT_LOCALE) return home[0]
    if (home) return this.stored ?? DEFAULT_LOCALE
    return this.stored ?? negotiateLocale(this.browserLanguages())
  }

  remember(locale: Locale): void {
    this.storage.set(LOCALE_STORAGE_KEY, locale)
  }
}
