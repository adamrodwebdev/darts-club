import { inject, type InjectionKey } from 'vue'
import { LocaleController } from './LocaleController'
import { MotionController } from './MotionController'
import { BrowserPreferenceStorage, type PreferenceStorage } from './PreferenceStorage'
import { ThemeController } from './ThemeController'

/** Contrôleurs des préférences d'affichage liés au navigateur */
export interface PreferenceControllers {
  theme: ThemeController
  motion: MotionController
  locale: LocaleController
}

/** Créés une fois au démarrage (main.ts) pour cette fenêtre, puis injectés. */
export function createPreferenceControllers(view: Window, storage: PreferenceStorage = new BrowserPreferenceStorage()): PreferenceControllers {
  const root = view.document.documentElement
  const media = (query: string) => (typeof view.matchMedia === 'function' ? view.matchMedia(query) : null)
  return {
    theme: new ThemeController(root, storage, media('(prefers-color-scheme: light)')),
    motion: new MotionController(root, storage, media('(prefers-reduced-motion: reduce)')),
    locale: new LocaleController(storage, () => view.navigator.languages ?? [view.navigator.language]),
  }
}

export const PREFERENCES: InjectionKey<PreferenceControllers> = Symbol('preferences')

export function usePreferenceControllers(): PreferenceControllers {
  const controllers = inject(PREFERENCES)
  if (!controllers) throw new Error('Préférences absentes : app.provide(PREFERENCES, createPreferenceControllers(window)) dans main.ts')
  return controllers
}
