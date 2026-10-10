import { describe, it, expect, beforeEach } from 'vitest'
import { negotiateLocale } from '@/i18n/locales'
import { LocaleController, LOCALE_STORAGE_KEY } from '../LocaleController'
import { MemoryPreferenceStorage } from '../PreferenceStorage'
import { ThemeController, THEME_STORAGE_KEY } from '../ThemeController'

function mediaQuery(matches: boolean): MediaQueryList {
  return { matches, addEventListener: () => undefined, removeEventListener: () => undefined } as unknown as MediaQueryList
}

describe('ThemeController', () => {
  let storage: MemoryPreferenceStorage

  beforeEach(() => {
    storage = new MemoryPreferenceStorage()
    document.head.innerHTML = '<meta name="theme-color" content="#000000">'
    delete document.documentElement.dataset.theme
  })

  it('follows the system theme by default', () => {
    const controller = new ThemeController(document.documentElement, storage, mediaQuery(true))

    expect(controller.preference).toBe('system')
    expect(controller.apply(controller.preference)).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(document.querySelector('meta[name="theme-color"]')?.getAttribute('content')).toBe('#f6f6f8')
  })

  it('remembers an explicit choice over the system theme', () => {
    const controller = new ThemeController(document.documentElement, storage, mediaQuery(true))

    expect(controller.choose('dark')).toBe('dark')
    expect(storage.get(THEME_STORAGE_KEY)).toBe('dark')
    expect(new ThemeController(document.documentElement, storage, mediaQuery(true)).preference).toBe('dark')
  })

  it('ignores a corrupted stored value', () => {
    storage.set(THEME_STORAGE_KEY, 'neon')

    expect(new ThemeController(document.documentElement, storage, null).preference).toBe('system')
  })
})

describe('locale selection', () => {
  it('negotiates the first supported browser language', () => {
    expect(negotiateLocale(['de-DE', 'en-US', 'fr'])).toBe('en')
    expect(negotiateLocale(['fr-BE'])).toBe('fr')
    expect(negotiateLocale(['es'])).toBe('fr')
  })

  it('lets the URL decide the language of each home page', () => {
    const storage = new MemoryPreferenceStorage()
    const controller = new LocaleController(storage, () => ['en-US'])

    expect(controller.initialFor('/en')).toBe('en')
    // Robots and first visits on « / » get the French page, whatever their language
    expect(controller.initialFor('/')).toBe('fr')
    expect(controller.initialFor('/connexion')).toBe('en')
  })

  it('prefers the remembered choice everywhere except on the English home page', () => {
    const storage = new MemoryPreferenceStorage()
    const controller = new LocaleController(storage, () => ['fr-FR'])
    controller.remember('en')

    expect(storage.get(LOCALE_STORAGE_KEY)).toBe('en')
    expect(controller.initialFor('/')).toBe('en')
    expect(controller.initialFor('/compte')).toBe('en')

    controller.remember('fr')
    expect(controller.initialFor('/en')).toBe('en')
  })
})
