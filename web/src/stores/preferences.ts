import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { i18n, loadLocaleMessages } from '@/i18n'
import type { Locale } from '@/i18n/locales'
import { localeController, themeController } from '@/preferences'
import { THEME_PREFERENCES, type Theme, type ThemePreference } from '@/preferences/ThemeController'

/** Préférences d'affichage : thème et langue de l'interface. */
export const usePreferencesStore = defineStore('preferences', () => {
  const theme = ref<ThemePreference>(themeController.preference)
  const appliedTheme = ref<Theme>(themeController.apply(theme.value))
  themeController.onSystemChange(() => {
    appliedTheme.value = themeController.apply(theme.value)
  })

  const locale = computed<Locale>(() => i18n.global.locale.value)

  function setTheme(preference: ThemePreference): void {
    theme.value = preference
    appliedTheme.value = themeController.choose(preference)
  }

  /** Bouton compact de l'en-tête : système → clair → sombre → système… */
  function cycleTheme(): void {
    const next = THEME_PREFERENCES[(THEME_PREFERENCES.indexOf(theme.value) + 1) % THEME_PREFERENCES.length]
    setTheme(next ?? 'system')
  }

  /** remember = false : langue imposée par l'URL, sans écraser le choix mémorisé. */
  async function setLocale(next: Locale, remember = true): Promise<void> {
    await loadLocaleMessages(next)
    i18n.global.locale.value = next
    if (remember) localeController.remember(next)
  }

  return { theme, appliedTheme, locale, setTheme, cycleTheme, setLocale }
})
