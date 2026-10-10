import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect } from 'vitest'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..', '..')

describe('Content Security Policy', () => {
  it('authorises the inlined theme script by its exact hash, and nothing else inline', () => {
    // Même transformation que build/localizedIndex.ts : contenu du fichier, espaces de début/fin retirés
    const script = readFileSync(join(ROOT, 'web/public/theme-init.js'), 'utf8').trim()
    const hash = createHash('sha256').update(script).digest('base64')
    const caddyfile = readFileSync(join(ROOT, 'docker/frankenphp/Caddyfile'), 'utf8')
    const scriptSrc = /script-src ([^;]+);/.exec(caddyfile)?.[1] ?? ''

    expect(scriptSrc.split(' ')).toEqual(["'self'", `'sha256-${hash}'`])
  })
})
