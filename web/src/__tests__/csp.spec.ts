import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect } from 'vitest'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..', '..')

/** Empreinte CSP d'un fichier intégré à la page : son contenu, espaces de début et de fin retirés (comme au build) */
function hashOf(file: string): string {
  const content = readFileSync(join(ROOT, file), 'utf8').trim()
  return `'sha256-${createHash('sha256').update(content).digest('base64')}'`
}

function directive(name: string): string[] {
  const caddyfile = readFileSync(join(ROOT, 'docker/frankenphp/Caddyfile'), 'utf8')
  return (new RegExp(`${name} ([^;]+);`).exec(caddyfile)?.[1] ?? '').split(' ')
}

describe('Content Security Policy', () => {
  it('authorises the inlined scripts by their exact hash, and nothing else inline', () => {
    // Script de thème (build/localizedIndex.ts) et script de démarrage (build/deferredBoot.ts)
    expect(directive('script-src')).toEqual(["'self'", hashOf('web/public/theme-init.js'), hashOf('web/build/inline/boot.js')])
  })

  it('authorises only the title card style inline', () => {
    expect(directive('style-src')).toEqual(["'self'", hashOf('web/build/inline/title-card.css')])
  })
})
