import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { probeHeaders } from '../probe'

// Chemin et non URL : sous jsdom, URL est celle du navigateur simulé, que node:fs ne lit pas
const PAGE_BYTES = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', 'probe.html'))
const PAGE = PAGE_BYTES.toString('utf8')

/** Sources d'une directive de la CSP */
function sources(policy: string | undefined, name: string): string[] {
  const directive = (policy ?? '').split('; ').find((each) => each.startsWith(`${name} `))
  return directive?.split(' ').slice(1) ?? []
}

/** Texte exact d'un bloc en ligne de la page, entre ses balises */
function inline(tag: string): string {
  const start = PAGE.indexOf(`<${tag}>`) + tag.length + 2
  return PAGE.slice(start, PAGE.indexOf(`</${tag}>`, start))
}

const sha256 = (text: string) => `'sha256-${createHash('sha256').update(text, 'utf8').digest('base64')}'`

describe('probe page', () => {
  // PROBE_REFERENCE_MS (cpuSlowdown.ts) a été mesurée sur ce texte exact : une page modifiée fait un
  // autre travail, et la référence ne vaut plus rien
  it('stays the page the browser clock reference was measured on', () => {
    const digest = createHash('sha256').update(PAGE_BYTES).digest('hex')
    expect(digest, 'probe.html a changé : remesurer PROBE_REFERENCE_MS (cpuSlowdown.ts), puis noter ici la nouvelle empreinte').toBe(
      '5ae9c2006479b8cf6343d2a453e9a5ef9ebc4085970c7666549d79808571488a',
    )
  })

  it('asks nothing from the network', () => {
    expect(PAGE).not.toMatch(/\ssrc=/)
    expect([...PAGE.matchAll(/\shref="([^"]*)"/g)].map((match) => match[1])).toEqual(['data:,'])
    expect(PAGE).not.toMatch(/url\(|@import/)
  })

  it('authorises its own inline script and style by their hash, and nothing else', () => {
    const policy = probeHeaders(PAGE)['Content-Security-Policy']
    expect(sources(policy, 'default-src')).toEqual(["'none'"])
    expect(sources(policy, 'script-src')).toEqual([sha256(inline('script'))])
    expect(sources(policy, 'style-src')).toEqual([sha256(inline('style'))])
  })
})

describe('probeHeaders', () => {
  // Exemple de la documentation de la CSP (MDN, script-src) : une empreinte publiée, calculée ailleurs
  it('hashes an inline script exactly as browsers do', () => {
    const policy = probeHeaders("<script>alert('Hello, world.');</script>")['Content-Security-Policy']
    expect(sources(policy, 'script-src')).toEqual(["'sha256-qznLcsROx4GACP2dm0UCKCzCG+HiZ1guq6ZZDob/Tng='"])
  })

  it('allows no inline block that the page does not have', () => {
    const policy = probeHeaders('<p>Ni script ni style</p>')['Content-Security-Policy']
    expect(sources(policy, 'script-src')).toEqual(["'none'"])
    expect(sources(policy, 'style-src')).toEqual(["'none'"])
  })

  // Sans quoi Lighthouse ne lit pas /robots.txt et la sonde perd l'audit SEO (91 au lieu de 100)
  it('lets Lighthouse read robots.txt from the same origin, as the site allows', () => {
    expect(sources(probeHeaders(PAGE)['Content-Security-Policy'], 'connect-src')).toEqual(["'self'"])
  })

  it('serves the page as HTML that no cache keeps', () => {
    expect(probeHeaders(PAGE)).toMatchObject({ 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' })
  })
})
