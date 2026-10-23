import { readFileSync } from 'node:fs'
import type { Plugin } from 'vite'

/**
 * Texte inséré tel quel dans la page. La CSP (docker/frankenphp/Caddyfile) l'autorise par
 * l'empreinte SHA-256 de ce texte exact, contrôlée par src/__tests__/csp.spec.ts.
 */
export function inlineSource(path: string): string {
  return readFileSync(path, 'utf8').trim()
}

const ENTRY = /\s*<script type="module" crossorigin src="([^"]+)"><\/script>/
const MODULE_PRELOAD = /\s*<link rel="modulepreload" crossorigin href="([^"]+)">/g
const STYLESHEET = /\s*<link rel="stylesheet" crossorigin href="([^"]+)">/g

/**
 * Premier affichage sans attendre le code : l'écran titre de index.html est peint avec son
 * style en ligne, puis le script de démarrage charge l'application une fois ce premier
 * affichage signalé (Lighthouse : ni le JavaScript ni le CSS du site ne pèsent sur le FCP et le LCP).
 * Les balises que Vite écrit (module d'entrée, préchargements, feuilles de style) deviennent un
 * manifeste lu par ce script. À placer avant les autres extensions qui reprennent index.html.
 */
export function deferredBoot(options: { titleCardStyle: string; bootScript: string }): Plugin {
  return {
    name: 'deferred-boot',
    enforce: 'post',
    // Développement comme production : l'écran titre a son style dès le premier affichage
    transformIndexHtml(html) {
      return html.replace('</head>', `  <style>${inlineSource(options.titleCardStyle)}</style>\n  </head>`)
    },
    generateBundle(_, bundle) {
      const index = bundle['index.html']
      if (!index || index.type !== 'asset') return this.error('index.html introuvable dans le bundle')

      let html = String(index.source)
      const entry = ENTRY.exec(html)?.[1]
      if (!entry) return this.error("index.html : module d'entrée introuvable")
      const modules = [...html.matchAll(MODULE_PRELOAD)].map((match) => match[1])
      const styles = [...html.matchAll(STYLESHEET)].map((match) => match[1])
      html = html.replace(ENTRY, '').replace(MODULE_PRELOAD, '').replace(STYLESHEET, '')

      // « < » échappé : le JSON ne peut jamais fermer la balise
      const manifest = JSON.stringify({ entry, modules, styles }).replace(/</g, '\\u003c')
      index.source = html.replace(
        '</body>',
        `  <script type="application/json" id="boot-manifest">${manifest}</script>\n    <script>${inlineSource(options.bootScript)}</script>\n  </body>`,
      )
    },
  }
}
