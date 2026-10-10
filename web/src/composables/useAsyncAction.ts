import { ref } from 'vue'
import { ApiError } from '@/api/ApiError'
import { i18n } from '@/i18n'

/**
 * État d'une action asynchrone de formulaire : envoi en cours, message
 * d'erreur global et erreurs par champ issues de la validation de l'API.
 */
export function useAsyncAction() {
  const pending = ref(false)
  const error = ref<string | null>(null)
  /** Code d'erreur stable de l'API, pour réagir sans dépendre du texte traduit */
  const errorCode = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string>>({})

  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    if (pending.value) return undefined
    pending.value = true
    error.value = null
    errorCode.value = null
    fieldErrors.value = {}

    try {
      return await action()
    } catch (e) {
      if (e instanceof ApiError) {
        fieldErrors.value = { ...e.violations }
        errorCode.value = e.code
        const { t } = i18n.global
        if (e.status === 0) error.value = t('errors.network')
        else error.value = Object.keys(e.violations).length > 0 ? t('errors.fields') : e.message
      } else {
        error.value = e instanceof Error ? e.message : i18n.global.t('errors.unexpected')
      }
      return undefined
    } finally {
      pending.value = false
    }
  }

  return { pending, error, errorCode, fieldErrors, run }
}
