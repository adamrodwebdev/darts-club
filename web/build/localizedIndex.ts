import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Plugin } from 'vite'

type MetaMessages = Record<string, string>

/** Pages publiques indexables (en plus des accueils de chaque langue) */
const PUBLIC_PAGES = ['/connexion', '/inscription', '/mentions-legales', '/confidentialite']
/** Espaces réservés aux adhérents : inutile de les explorer */
const PRIVATE_PATHS = ['/api/', '/compte', '/admin', '/tresorerie', '/messages', '/notifications', '/evenements', '/infos']

function readMeta(localesDir: string, locale: string): MetaMessages {
  return (JSON.parse(readFileSync(join(localesDir, locale, 'core.json'), 'utf8')) as { meta: MetaMessages }).meta
}

/**
 * Génère dist/<page>.html pour chaque langue secondaire : même application,
 * mais en-tête (lang, titre, description, Open Graph, JSON-LD) traduit, pour que
 * l'accueil de chaque langue soit indexé avec ses propres métadonnées.
 * Les textes de index.html doivent correspondre à la section « meta » de la langue source.
 */
/**
 * Avec l'URL publique du site (build de production) : image Open Graph, URL canonique
 * et versions linguistiques (hreflang) en URL absolues, lisibles sans JavaScript.
 */
function withSiteUrl(html: string, siteUrl: string, path: string, homes: Record<string, string>, defaultLocale: string): string {
  const absolute = (p: string) => siteUrl + p
  const links = [
    `<link rel="canonical" href="${absolute(path)}" data-i18n-head>`,
    `<meta property="og:url" content="${absolute(path)}" data-i18n-head>`,
    ...Object.entries(homes).map(([locale, home]) => `<link rel="alternate" hreflang="${locale}" href="${absolute(home)}" data-i18n-head>`),
    `<link rel="alternate" hreflang="x-default" href="${absolute(homes[defaultLocale] ?? '/')}" data-i18n-head>`,
  ]
  return html
    .replace('<meta property="og:image" content="/og-image.png">', `<meta property="og:image" content="${absolute('/og-image.png')}">`)
    .replace('</head>', `    ${links.join('\n    ')}\n  </head>`)
}

export function localizedIndex(options: {
  localesDir: string
  source: string
  /** langue → nom du fichier HTML généré (sans extension) */
  pages: Record<string, string>
  /** URL publique sans « / » final (VITE_SITE_URL), vide en développement */
  siteUrl?: string
}): Plugin {
  return {
    name: 'localized-index',
    apply: 'build',
    enforce: 'post',
    generateBundle(_, bundle) {
      const index = bundle['index.html']
      if (!index || index.type !== 'asset') this.error('index.html introuvable dans le bundle')

      const html = String(index.source)
      const siteUrl = (options.siteUrl ?? '').replace(/\/+$/, '')
      const homes: Record<string, string> = { [options.source]: '/', ...Object.fromEntries(Object.entries(options.pages).map(([l, d]) => [l, `/${d}`])) }
      if (siteUrl) index.source = withSiteUrl(html, siteUrl, '/', homes, options.source)
      const source = readMeta(options.localesDir, options.source)
      // Plus longs d'abord : « Fléchettes » est aussi contenu dans le titre
      const entries = Object.entries(source).sort(([, a], [, b]) => b.length - a.length)

      for (const [locale, directory] of Object.entries(options.pages)) {
        const target = readMeta(options.localesDir, locale)
        let localized = html.replace(`<html lang="${options.source}">`, `<html lang="${locale}">`)
        for (const [key, text] of entries) {
          if (!html.includes(text)) this.error(`index.html ne contient pas meta.${key} (${options.source}.json) : « ${text} »`)
          const translation = target[key]
          if (!translation) this.error(`meta.${key} manquant dans ${locale}.json`)
          localized = localized.replaceAll(text, translation)
        }
        if (siteUrl) localized = withSiteUrl(localized, siteUrl, `/${directory}`, homes, options.source)
        this.emitFile({ type: 'asset', fileName: `${directory}.html`, source: localized })
      }

      // Robots : espaces adhérents et API hors index ; plan du site si l'URL publique est connue
      const robots = ['User-agent: *', 'Allow: /', ...PRIVATE_PATHS.map((path) => `Disallow: ${path}`)]
      if (siteUrl) {
        robots.push('', `Sitemap: ${siteUrl}/sitemap.xml`)
        const urls = [...Object.values(homes), ...PUBLIC_PAGES]
          .map((path) => `  <url><loc>${siteUrl}${path}</loc></url>`)
          .join('\n')
        this.emitFile({
          type: 'asset',
          fileName: 'sitemap.xml',
          source: `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`,
        })
      }
      this.emitFile({ type: 'asset', fileName: 'robots.txt', source: robots.join('\n') + '\n' })
    },
  }
}
