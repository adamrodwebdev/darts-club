/** Langues de l'interface : le français est la langue de référence. */
export const LOCALES = ['fr', 'en'] as const
export type Locale = (typeof LOCALES)[number]

export const DEFAULT_LOCALE: Locale = 'fr'

/** Variante régionale utilisée par Intl (dates européennes pour l'anglais aussi). */
export const INTL_LOCALES: Record<Locale, string> = { fr: 'fr-FR', en: 'en-GB' }

/** Nom de chaque langue dans cette langue même (sélecteur de langue). */
export const AUTONYMS: Record<Locale, string> = { fr: 'Français', en: 'English' }

/** Libellé du bouton qui bascule vers la langue, rédigé dans cette langue. */
export const SWITCH_LABELS: Record<Locale, string> = { fr: 'Passer en français', en: 'Switch to English' }

/** Page d'accueil de chaque langue (URL distinctes pour le référencement). */
export const HOME_PATHS: Record<Locale, string> = { fr: '/', en: '/en' }

export function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (LOCALES as readonly string[]).includes(value)
}

/** Première langue du navigateur (reflet d'Accept-Language) prise en charge. */
export function negotiateLocale(languages: readonly string[]): Locale {
  for (const tag of languages) {
    const primary = tag.toLowerCase().split('-')[0]
    if (isLocale(primary)) return primary
  }
  return DEFAULT_LOCALE
}
