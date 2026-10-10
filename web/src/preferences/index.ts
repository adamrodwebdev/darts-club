import { LocaleController } from './LocaleController'
import { BrowserPreferenceStorage } from './PreferenceStorage'
import { ThemeController } from './ThemeController'

/** Contrôleurs de préférences liés au navigateur (une instance chacun). */
const storage = new BrowserPreferenceStorage()

export const themeController = new ThemeController(
  document.documentElement,
  storage,
  typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: light)') : null,
)

export const localeController = new LocaleController(storage, () => navigator.languages ?? [navigator.language])
