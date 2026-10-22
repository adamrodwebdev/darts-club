import { config } from '@vue/test-utils'
import { i18n } from '@/i18n'
import { createPreferenceControllers, PREFERENCES } from '@/preferences'
import { MemoryPreferenceStorage } from '@/preferences/PreferenceStorage'
import { createServices, SERVICES } from '@/services'

// Composants montés avec les traductions et les dépendances injectées, comme dans l'application
config.global.plugins.push(i18n)
config.global.provide = {
  [SERVICES as symbol]: createServices(),
  [PREFERENCES as symbol]: createPreferenceControllers(window, new MemoryPreferenceStorage()),
}
