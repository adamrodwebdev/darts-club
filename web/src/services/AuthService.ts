import type { ApiClient } from '@/api/ApiClient'
import { bool, obj, str, strList } from '@/models/guards'
import { Profile } from '@/models/Profile'

export interface Registration {
  email: string
  password: string
  phone?: string
}

export const OAUTH_PROVIDERS = ['google', 'discord'] as const
export type OAuthProvider = (typeof OAUTH_PROVIDERS)[number]

/** Deuxième étape : code TOTP (administrateur, membres qui l'ont choisi) ou clé d'accès (bureau) */
export type TwoFactorMethod = 'totp' | 'passkey'

/** Identifiants acceptés : session complète, ou deuxième étape attendue. */
export type LoginResult = { status: 'authenticated'; profile: Profile } | { status: 'two-factor'; method: TwoFactorMethod }

/** Suite d'une connexion : session ouverte, ou méthode de la deuxième étape */
export type LoginStep = 'authenticated' | TwoFactorMethod

export interface TwoFactorSetup {
  secret: string
  uri: string
  qrCode: string
}

export class AuthService {
  /** Cookie non sensible posé par l'API quand une session existe. */
  static readonly SESSION_HINT = 'dc_session'

  constructor(
    private readonly api: ApiClient,
    private readonly cookies: () => string = () => document.cookie,
  ) {}

  hasSessionHint(): boolean {
    return this.cookies()
      .split(';')
      .some((c) => c.trim() === `${AuthService.SESSION_HINT}=1`)
  }

  async me(): Promise<Profile> {
    return Profile.parse(await this.api.get('/api/auth/me'))
  }

  async login(email: string, password: string): Promise<LoginResult> {
    return AuthService.loginResult(await this.api.post('/api/auth/login', { email, password }))
  }

  /** Réponse d'une connexion, par mot de passe ou par clé d'accès */
  static loginResult(data: unknown): LoginResult {
    const d = obj(data)
    if (d.twoFactorRequired !== undefined && bool(d.twoFactorRequired)) {
      // Sans « method » (API plus ancienne) : le code TOTP
      return { status: 'two-factor', method: d.method === 'passkey' ? 'passkey' : 'totp' }
    }
    return { status: 'authenticated', profile: Profile.parse(data) }
  }

  async verifyTwoFactor(code: string): Promise<Profile> {
    return Profile.parse(await this.api.post('/api/auth/2fa/check', { code }))
  }

  async logout(): Promise<void> {
    await this.api.post('/api/auth/logout')
  }

  async register(registration: Registration): Promise<void> {
    const payload: Record<string, string> = { email: registration.email, password: registration.password }
    if (registration.phone) payload.phone = registration.phone
    await this.api.post('/api/auth/register', payload)
  }

  async resendVerification(email: string): Promise<void> {
    await this.api.post('/api/auth/verify/resend', { email })
  }

  /**
   * Paramètres d'un lien reçu par email (confirmation d'adresse, nouveau mot de passe), recopiés tels
   * quels vers l'API : seulement les clés attendues, une seule valeur chacune.
   */
  static linkQuery(query: Readonly<Record<string, unknown>>, keys: readonly string[]): string {
    const params = new URLSearchParams()
    for (const key of keys) {
      const value = query[key]
      if (typeof value === 'string') params.set(key, value)
    }
    return params.toString()
  }

  /** Confirme l'adresse avec le mot de passe choisi à l'inscription ; renvoie l'email du compte. */
  async confirmEmail(linkQuery: string, password: string): Promise<string> {
    return str(obj(await this.api.post(`/api/auth/verify?${linkQuery}`, { password })).email)
  }

  async forgotPassword(email: string): Promise<void> {
    await this.api.post('/api/auth/password/forgot', { email })
  }

  async resetPassword(linkQuery: string, password: string): Promise<void> {
    await this.api.post(`/api/auth/password/reset?${linkQuery}`, { password })
  }

  /** Fournisseurs configurés sur le serveur (aucun bouton si l'API est injoignable). */
  async oauthProviders(): Promise<OAuthProvider[]> {
    try {
      const configured = strList(obj(await this.api.get('/api/auth/oauth/providers')).providers)
      return OAUTH_PROVIDERS.filter((provider) => configured.includes(provider))
    } catch {
      return []
    }
  }

  oauthUrl(provider: OAuthProvider): string {
    return `/api/auth/oauth/${provider}`
  }
}
