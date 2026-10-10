import { createI18n } from 'vue-i18n'
import account from './locales/fr/account.json'
import admin from './locales/fr/admin.json'
import core from './locales/fr/core.json'
import events from './locales/fr/events.json'
import messaging from './locales/fr/messaging.json'
import treasury from './locales/fr/treasury.json'
import { DEFAULT_LOCALE, INTL_LOCALES, type Locale } from './locales'

/** Un fichier par domaine (locales/<langue>/<domaine>.json), fusionnés en un catalogue. */
const fr = { ...core, ...account, ...events, ...messaging, ...treasury, ...admin }

export type MessageSchema = typeof fr

declare module 'vue-i18n' {
  // Clés de traduction vérifiées par le compilateur TypeScript
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  export interface DefineLocaleMessage extends MessageSchema {}
}

/** Le français est embarqué ; les autres langues sont chargées à la demande. */
export const i18n = createI18n<[MessageSchema], Locale, false>({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { fr } as Record<Locale, MessageSchema>,
})

const loaders: Record<Exclude<Locale, 'fr'>, () => Promise<{ default: unknown }>> = {
  en: () => import('./locales/en/index'),
}

export async function loadLocaleMessages(locale: Locale): Promise<void> {
  if (locale === 'fr' || i18n.global.availableLocales.includes(locale)) return
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
