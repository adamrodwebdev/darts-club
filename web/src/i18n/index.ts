import { createI18n } from 'vue-i18n'
import type frBoard from './locales/fr/board.json'
import type frCatalogue from './locales/fr/index'
import { DEFAULT_LOCALE, INTL_LOCALES, type Locale } from './locales'
import { frenchTypography } from './typography'

/**
 * Un fichier par domaine (locales/<langue>/<domaine>.json), fusionnés en un catalogue par langue ;
 * les textes du bureau et des équipes (board.json) sont à part, chargés à la demande, mais leurs clés
 * restent vérifiées par le compilateur.
 */
export type MessageSchema = typeof frCatalogue & typeof frBoard

declare module 'vue-i18n' {
  // Clés de traduction vérifiées par le compilateur TypeScript
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  export interface DefineLocaleMessage extends MessageSchema {}
}

/**
 * Chaque langue est chargée à la demande (main.ts attend celle de la page avant le premier
 * rendu) : un visiteur ne télécharge ni n'évalue que la sienne.
 */
export const i18n = createI18n<[MessageSchema], Locale, false>({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: {} as Record<Locale, MessageSchema>,
  // Typographie française appliquée au texte traduit (espaces insécables) : catalogues inchangés
  postTranslation: (translated) => (typeof translated === 'string' && currentLocale() === 'fr' ? frenchTypography(translated) : translated),
})

const loaders: Record<Locale, () => Promise<{ default: unknown }>> = {
  fr: () => import('./locales/fr/index'),
  en: () => import('./locales/en/index'),
}

const boardLoaders: Record<Locale, () => Promise<{ default: unknown }>> = {
  fr: () => import('./locales/fr/board.json'),
  en: () => import('./locales/en/board.json'),
}

/** Langues dont les textes du bureau sont déjà fusionnés ; demandés une fois, suivis au changement de langue */
const boardLoaded = new Set<Locale>()
let boardWanted = false

export async function loadLocaleMessages(locale: Locale): Promise<void> {
  if (!i18n.global.availableLocales.includes(locale)) {
    const catalogue = await loaders[locale]()
    i18n.global.setLocaleMessage(locale, catalogue.default as MessageSchema)
  }
  if (boardWanted) await loadBoardMessages(locale)
}

/**
 * Textes du bureau et des équipes (bilan, démarches, affiche QR, écrans de soirée, journal) : jamais
 * chargés par une page publique, qui démarre ainsi avec un catalogue plus léger.
 */
export async function loadBoardMessages(locale: Locale): Promise<void> {
  boardWanted = true
  if (boardLoaded.has(locale)) return
  const board = await boardLoaders[locale]()
  i18n.global.mergeLocaleMessage(locale, board.default as MessageSchema)
  boardLoaded.add(locale)
}

export function currentLocale(): Locale {
  return i18n.global.locale.value
}

/** Étiquette BCP 47 pour Intl (dates, nombres, montants). */
export function intlLocale(): string {
  return INTL_LOCALES[currentLocale()]
}

export type EnumKind = keyof MessageSchema['enums']

/**
 * Libellé traduit d'une valeur d'énumération de l'API (rôle, statut, badge…),
 * repli sur le libellé fourni par l'API si la valeur est inconnue du front.
 */
export function enumLabel(kind: EnumKind, id: string, fallback = id): string {
  const key = `enums.${kind}.${id}`
  const { te, t } = i18n.global
  return te(key as never) ? t(key as never) : fallback
}
