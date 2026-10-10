import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import type { Plugin } from 'vite'

type MetaMessages = Record<string, string>

function readMeta(localesDir: string, locale: string): MetaMessages {
  return (JSON.parse(readFileSync(join(localesDir, `${locale}.json`), 'utf8')) as { meta: MetaMessages }).meta
}

/**
 * Génère dist/<page>.html pour chaque langue secondaire : même application,
 * mais en-tête (lang, titre, description, Open Graph, JSON-LD) traduit, pour que
 * l'accueil de chaque langue soit indexé avec ses propres métadonnées.
 * Les textes de index.html doivent correspondre à la section « meta » de la langue source.
 */
export function localizedIndex(options: { localesDir: string; source: string; pages: Record<string, string> }): Plugin {
  return {
    name: 'localized-index',
    apply: 'build',
    enforce: 'post',
    generateBundle(_, bundle) {
      const index = bundle['index.html']
      if (!index || index.type !== 'asset') this.error('index.html introuvable dans le bundle')

      const html = String(index.source)
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
        this.emitFile({ type: 'asset', fileName: `${directory}.html`, source: localized })
      }
    },
  }
}
