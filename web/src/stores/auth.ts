import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError } from '@/api/ApiError'
import type { Profile } from '@/models/Profile'
import { services } from '@/services'
import type { Registration } from '@/services/AuthService'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<Profile | null>(null)
  let initialization: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)

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

  async function login(email: string, password: string): Promise<void> {
    user.value = await services.auth.login(email, password)
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

  return { user, isAuthenticated, init, login, logout, register, setUser, forget }
})
