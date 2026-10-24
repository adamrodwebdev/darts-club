import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError } from '@/api/ApiError'
import type { Profile } from '@/models/Profile'
import { useServices } from '@/services'
import type { LoginResult, LoginStep, Registration } from '@/services/AuthService'

export const useAuthStore = defineStore('auth', () => {
  const services = useServices()
  const user = ref<Profile | null>(null)
  let initialization: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)
  /** Connecté et validé par le bureau : espace membres ouvert */
  const isMember = computed(() => user.value?.approved === true)

  /** Restaure la session au démarrage, une seule fois. */
  function init(): Promise<void> {
    initialization ??= (async () => {
      if (!services.auth.hasSessionHint()) return
      try {
        user.value = await services.auth.me()
      } catch (e) {
        if (!(e instanceof ApiError && e.isUnauthorized)) throw e
      }
    })()
    return initialization
  }

  async function login(email: string, password: string): Promise<LoginStep> {
    return open(await services.auth.login(email, password))
  }

  /** Connexion par clé d'accès, sans mot de passe */
  async function loginWithPasskey(): Promise<LoginStep> {
    return open(await services.passkeys.login())
  }

  async function verifyTwoFactor(code: string): Promise<void> {
    user.value = await services.auth.verifyTwoFactor(code)
  }

  /** Deuxième étape du bureau : la clé d'accès au lieu du code */
  async function confirmWithPasskey(): Promise<void> {
    user.value = await services.passkeys.confirmTwoFactor()
  }

  /** Session ouverte, ou deuxième étape attendue */
  function open(result: LoginResult): LoginStep {
    if (result.status === 'two-factor') return result.method
    user.value = result.profile
    return 'authenticated'
  }

  /** Profil relu après un changement de sécurité (clé ajoutée, code activé…) */
  async function refresh(): Promise<void> {
    user.value = await services.auth.me()
  }

  async function logout(): Promise<void> {
    try {
      await services.auth.logout()
    } finally {
      user.value = null
    }
  }

  function register(registration: Registration): Promise<void> {
    return services.auth.register(registration)
  }

  function setUser(profile: Profile): void {
    user.value = profile
  }

  /** Session expirée côté serveur. */
  function forget(): void {
    user.value = null
  }

  return {
    user,
    isAuthenticated,
    isMember,
    init,
    login,
    loginWithPasskey,
    verifyTwoFactor,
    confirmWithPasskey,
    refresh,
    logout,
    register,
    setUser,
    forget,
  }
})
