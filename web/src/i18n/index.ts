import { createI18n } from 'vue-i18n'
import type frCatalogue from './locales/fr/index'
import { DEFAULT_LOCALE, INTL_LOCALES, type Locale } from './locales'

/** Un fichier par domaine (locales/<langue>/<domaine>.json), fusionnés en un catalogue par langue. */
export type MessageSchema = typeof frCatalogue

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
})

const loaders: Record<Locale, () => Promise<{ default: unknown }>> = {
  fr: () => import('./locales/fr/index'),
  en: () => import('./locales/en/index'),
}

export async function loadLocaleMessages(locale: Locale): Promise<void> {
  if (i18n.global.availableLocales.includes(locale)) return
  const catalogue = await loaders[locale]()
  i18n.global.setLocaleMessage(locale, catalogue.default as MessageSchema)
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
