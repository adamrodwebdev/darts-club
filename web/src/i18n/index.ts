import { createI18n } from 'vue-i18n'
import fr from './locales/fr.json'
import { DEFAULT_LOCALE, INTL_LOCALES, type Locale } from './locales'

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

const loaders: Record<Exclude<Locale, 'fr'>, () => Promise<{ default: MessageSchema }>> = {
  en: () => import('./locales/en.json'),
}

export async function loadLocaleMessages(locale: Locale): Promise<void> {
  if (locale === 'fr' || i18n.global.availableLocales.includes(locale)) return
  const messages = await loaders[locale]()
  i18n.global.setLocaleMessage(locale, messages.default)
}

export function currentLocale(): Locale {
  return i18n.global.locale.value
}

/** Étiquette BCP 47 pour Intl (dates, nombres, montants). */
export function intlLocale(): string {
  return INTL_LOCALES[currentLocale()]
}
