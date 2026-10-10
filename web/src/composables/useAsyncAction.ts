import { ref } from 'vue'
import { ApiError } from '@/api/ApiError'

/**
 * État d'une action asynchrone de formulaire : envoi en cours, message
 * d'erreur global et erreurs par champ issues de la validation de l'API.
 */
export function useAsyncAction() {
  const pending = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string>>({})

  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    if (pending.value) return undefined
    pending.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      return await action()
    } catch (e) {
      if (e instanceof ApiError) {
        fieldErrors.value = { ...e.violations }
        error.value = Object.keys(e.violations).length > 0 ? 'Vérifie les champs indiqués.' : e.message
      } else {
        error.value = e instanceof Error ? e.message : 'Une erreur inattendue est survenue.'
      }
      return undefined
    } finally {
      pending.value = false
    }
  }

  return { pending, error, fieldErrors, run }
}
