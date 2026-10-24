import { afterEach, describe, expect, it } from 'vitest'
import { i18n, loadLocaleMessages } from '..'
import { frenchTypography, typographyFor } from '../typography'

const NBSP = ' '

describe('french typography', () => {
  it('binds the space before high punctuation and inside guillemets', () => {
    expect(frenchTypography('Destinataire : « Mairie » ; vraiment ? oui ! −20 %')).toBe(
      `Destinataire${NBSP}: «${NBSP}Mairie${NBSP}»${NBSP}; vraiment${NBSP}? oui${NBSP}! −20${NBSP}%`,
    )
  })

  it('leaves times, links and spaces elsewhere alone, and is idempotent', () => {
    const text = 'À 20:30, voir https://skulldarts71.fr/?soiree=1 ou deux espaces'
    expect(frenchTypography(text)).toBe(text)
    const once = frenchTypography('Note : « oui »')
    expect(frenchTypography(once)).toBe(once)
  })

  it('applies to french only', () => {
    expect(typographyFor('fr-FR')('Objet : test')).toBe(`Objet${NBSP}: test`)
    expect(typographyFor('en')('Subject : test')).toBe('Subject : test')
  })
})

describe('translated text', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('carries french non-breaking spaces, english stays as written', async () => {
    await loadLocaleMessages('fr')
    await loadLocaleMessages('en')
    i18n.global.locale.value = 'fr'
    expect(i18n.global.t('events.documents.recipient', { name: 'Mairie' })).toBe(`Destinataire${NBSP}: Mairie`)
    i18n.global.locale.value = 'en'
    expect(i18n.global.t('events.documents.recipient', { name: 'Mairie' })).toBe('Recipient: Mairie')
  })
})
