import type { ApiClient } from '@/api/ApiClient'
import { bool, obj, strList } from '@/models/guards'
import { Profile } from '@/models/Profile'

export interface Registration {
  email: string
  password: string
  phone?: string
}

export const OAUTH_PROVIDERS = ['google', 'discord'] as const
export type OAuthProvider = (typeof OAUTH_PROVIDERS)[number]

/** Mot de passe accepté : session complète, ou code 2FA attendu. */
export type LoginResult = { status: 'authenticated'; profile: Profile } | { status: 'two-factor' }

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
    const data = await this.api.post('/api/auth/login', { email, password })
    if (obj(data).twoFactorRequired !== undefined && bool(obj(data).twoFactorRequired)) {
      return { status: 'two-factor' }
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
