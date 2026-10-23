import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { i18n, loadLocaleMessages } from '@/i18n'
import type { Locale } from '@/i18n/locales'
import type { DisplayPreferences } from '@/models/Profile'
import { usePreferenceControllers } from '@/preferences'
import { MOTION_PREFERENCES, type Motion, type MotionPreference } from '@/preferences/MotionController'
import { THEME_PREFERENCES, type Theme, type ThemePreference } from '@/preferences/ThemeController'
import { useServices } from '@/services'
import { useAuthStore } from './auth'

/**
 * Préférences d'affichage : thème et langue de l'interface. Mémorisées dans le
 * navigateur et, une fois connecté, sur le compte (langue des emails et notifications).
 */
export const usePreferencesStore = defineStore('preferences', () => {
  const services = useServices()
  const { theme: themeController, motion: motionController, locale: localeController } = usePreferenceControllers()
  const theme = ref<ThemePreference>(themeController.preference)
  const appliedTheme = ref<Theme>(themeController.apply(theme.value))
  themeController.onSystemChange(() => {
    appliedTheme.value = themeController.apply(theme.value)
  })

  const locale = computed<Locale>(() => i18n.global.locale.value)

  /** Animations : mémorisées sur l'appareil (choix d'usage, pas de donnée de compte) */
  const motion = ref<MotionPreference>(motionController.preference)
  const appliedMotion = ref<Motion>(motionController.apply(motion.value))
  motionController.onSystemChange(() => {
    appliedMotion.value = motionController.apply(motion.value)
  })

  function setMotion(preference: MotionPreference): void {
    motion.value = MOTION_PREFERENCES.includes(preference) ? preference : 'system'
    appliedMotion.value = motionController.choose(motion.value)
  }

  /** Interrupteur du pied de page : bascule l'état affiché */
  function toggleMotion(): void {
    setMotion(appliedMotion.value === 'on' ? 'off' : 'on')
  }

  /** Enregistre le choix sur le compte ; l'interface n'attend pas la réponse. */
  function saveToAccount(): void {
    const auth = useAuthStore()
    if (!auth.user) return
    services.profile
      .updatePreferences({ language: locale.value, theme: theme.value })
      .then((profile) => auth.setUser(profile))
      .catch(() => undefined)
  }

  function applyTheme(preference: ThemePreference): void {
    theme.value = preference
    appliedTheme.value = themeController.choose(preference)
  }

  async function applyLocale(next: Locale, remember: boolean): Promise<void> {
    await loadLocaleMessages(next)
    i18n.global.locale.value = next
    if (remember) localeController.remember(next)
  }

  function setTheme(preference: ThemePreference): void {
    applyTheme(preference)
    saveToAccount()
  }

  /** Bouton compact de l'en-tête : système → clair → sombre → système… */
  function cycleTheme(): void {
    const next = THEME_PREFERENCES[(THEME_PREFERENCES.indexOf(theme.value) + 1) % THEME_PREFERENCES.length]
    setTheme(next ?? 'system')
  }

  /** remember = false : langue imposée par l'URL, sans écraser le choix mémorisé ni le compte. */
  async function setLocale(next: Locale, remember = true): Promise<void> {
    await applyLocale(next, remember)
    if (remember) saveToAccount()
  }

  /**
   * À la connexion : un choix déjà fait sur cet appareil prime et devient celui du
   * compte ; sinon l'appareil reprend le choix du compte (fait sur un autre appareil).
   */
  async function adoptAccount(preferences: DisplayPreferences): Promise<void> {
    const localTheme = themeController.stored
    const localLocale = localeController.stored

    if (localTheme === null && preferences.theme !== theme.value) applyTheme(preferences.theme)
    if (localLocale === null && preferences.language !== locale.value) await applyLocale(preferences.language, true)

    if ((localTheme !== null && localTheme !== preferences.theme) || (localLocale !== null && localLocale !== preferences.language)) {
      saveToAccount()
    }
  }

  return { theme, appliedTheme, motion, appliedMotion, locale, setTheme, cycleTheme, setMotion, toggleMotion, setLocale, adoptAccount }
})
