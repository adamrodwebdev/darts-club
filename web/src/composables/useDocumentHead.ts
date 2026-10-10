import { watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { HOME_PATHS, LOCALES, DEFAULT_LOCALE } from '@/i18n/locales'

const MANAGED = 'data-i18n-head'

function setMeta(selector: string, content: string): void {
  document.head.querySelector(selector)?.setAttribute('content', content)
}

function link(attributes: Record<string, string>): HTMLLinkElement {
  const element = document.createElement('link')
  for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value)
  element.setAttribute(MANAGED, '')
  return element
}

/**
 * En-tête du document dans la langue courante : lang, titre, description,
 * et pour les pages d'accueil les versions alternatives (hreflang) et l'URL canonique.
 */
export function useDocumentHead(): void {
  const route = useRoute()
  const { t, locale } = useI18n()

  watchEffect(() => {
    document.documentElement.lang = locale.value
    const key = route.meta.titleKey
    document.title = key ? `${t(`routes.${key}`)} · Skull Darts 71` : t('meta.title')
    setMeta('meta[name="description"]', t('meta.description'))
    setMeta('meta[property="og:locale"]', t('meta.ogLocale'))

    document.head.querySelectorAll(`[${MANAGED}]`).forEach((element) => element.remove())
    if (!Object.values(HOME_PATHS).includes(route.path)) return

    // URL absolues exigées : l'origine n'est connue qu'à l'exécution
    const origin = window.location.origin
    document.head.append(
      link({ rel: 'canonical', href: origin + route.path }),
      ...LOCALES.map((code) => link({ rel: 'alternate', hreflang: code, href: origin + HOME_PATHS[code] })),
      link({ rel: 'alternate', hreflang: 'x-default', href: origin + HOME_PATHS[DEFAULT_LOCALE] }),
    )
  })
}
