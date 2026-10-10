import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect } from 'vitest'

// Fichiers lus bruts : l'import JSON passe par la précompilation des messages
const read = (locale: string): unknown => 
  JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', 'locales', `${locale}.json`), 'utf8'))
const fr = read('fr')
const en = read('en')

/** Chemins de toutes les feuilles d'un catalogue (« landing.cta.title », « song.directions.intro.0 »…) */
function leaves(node: unknown, prefix = ''): Map<string, unknown> {
  const result = new Map<string, unknown>()
  if (node !== null && typeof node === 'object') {
    for (const [key, value] of Object.entries(node)) {
      for (const [path, leaf] of leaves(value, prefix ? `${prefix}.${key}` : key)) result.set(path, leaf)
    }
  } else {
    result.set(prefix, node)
  }
  return result
}

describe('translation catalogues', () => {
  const reference = leaves(fr)
  const english = leaves(en)

  it('english defines every french key and nothing more', () => {
    expect([...reference.keys()].filter((key) => !english.has(key))).toEqual([])
    expect([...english.keys()].filter((key) => !reference.has(key))).toEqual([])
  })

  it('has no empty message', () => {
    const empty = [...reference, ...english].filter(([, value]) => typeof value !== 'string' || value.trim() === '')
    expect(empty).toEqual([])
  })

  it('keeps the same placeholders in both languages', () => {
    const placeholders = (text: unknown) => [...String(text).matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort()
    const mismatches = [...reference].filter(([key, text]) => placeholders(text).join() !== placeholders(english.get(key)).join())
    expect(mismatches.map(([key]) => key)).toEqual([])
  })

  it('keeps the same plural forms in both languages', () => {
    const forms = (text: unknown) => String(text).split(' | ').length
    const mismatches = [...reference].filter(([key, text]) => forms(text) !== forms(english.get(key)))
    expect(mismatches.map(([key]) => key)).toEqual([])
  })
})
