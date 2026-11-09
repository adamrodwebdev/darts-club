// Page sonde de l'horloge du navigateur (probe.html), servie par le bouchon de la mesure (api-stub.mjs)
// à côté de l'aperçu du site : la CSP de production que l'aperçu applique y bloquerait son script.
import { createHash } from 'node:crypto'

/** Chemin de la page sonde sur le bouchon ; les passages qui le demandent nourrissent l'horloge du navigateur */
export const PROBE_PATH = '/sonde'

/** Blocs en ligne sans attribut : le texte exact entre les balises, celui que le navigateur hache */
const INLINE_BLOCK = /<(script|style)>([\s\S]*?)<\/\1>/g

function sha256(text: string): string {
  return `'sha256-${createHash('sha256').update(text, 'utf8').digest('base64')}'`
}

/**
 * En-têtes de la page sonde. Comme l'écran titre du site sous la CSP de production, ses blocs en
 * ligne ne passent que par leur empreinte et rien d'autre n'est permis : le navigateur lit la
 * politique à l'arrivée du document et vérifie les empreintes, comme pour le site.
 */
export function probeHeaders(html: string): Readonly<Record<string, string>> {
  const blocks = [...html.matchAll(INLINE_BLOCK)]
  const sources = (tag: string): string =>
    blocks
      .filter((block) => block[1] === tag)
      .map((block) => sha256(block[2] ?? ''))
      .join(' ') || "'none'"
  const policy = [
    "default-src 'none'",
    `script-src ${sources('script')}`,
    `style-src ${sources('style')}`,
    // Icône en data: (la page n'en demande aucune)
    'img-src data:',
    // Comme le site : Lighthouse lit /robots.txt au nom de la page (sans quoi l'audit SEO échoue)
    "connect-src 'self'",
    "base-uri 'none'",
    "form-action 'none'",
    "frame-ancestors 'none'",
  ].join('; ')
  return {
    'Content-Type': 'text/html; charset=utf-8',
    'Cache-Control': 'no-store',
    'Content-Security-Policy': policy,
  }
}
