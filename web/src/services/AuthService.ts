import type { ApiClient } from '@/api/ApiClient'
import { Profile } from '@/models/Profile'

export interface Registration {
  email: string
  password: string
  phone?: string
}

export type OAuthProvider = 'google' | 'discord'

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

  async login(email: string, password: string): Promise<Profile> {
    return Profile.parse(await this.api.post('/api/auth/login', { email, password }))
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

  oauthUrl(provider: OAuthProvider): string {
    return `/api/auth/oauth/${provider}`
  }
}
