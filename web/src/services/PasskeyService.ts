import type {
  AuthenticationResponseJSON,
  PublicKeyCredentialCreationOptionsJSON,
  PublicKeyCredentialRequestOptionsJSON,
} from '@simplewebauthn/browser'
import type { ApiClient } from '@/api/ApiClient'
import { ApiError } from '@/api/ApiError'
import { i18n } from '@/i18n'
import { obj, str } from '@/models/guards'
import { Passkey } from '@/models/Passkey'
import { Profile } from '@/models/Profile'
import { AuthService, type LoginResult } from './AuthService'

/** Ce que le site utilise de la bibliothèque WebAuthn du navigateur */
export type WebAuthnBrowser = Pick<typeof import('@simplewebauthn/browser'), 'startAuthentication' | 'startRegistration'>

/** Pourquoi le geste n'a pas abouti, du point de vue de l'adhérent */
export type PasskeyFailure = 'cancelled' | 'duplicate' | 'unsupported' | 'failed'

const MESSAGES = {
  cancelled: 'auth.passkeyErrors.cancelled',
  duplicate: 'auth.passkeyErrors.duplicate',
  unsupported: 'auth.passkeyErrors.unsupported',
  failed: 'auth.passkeyErrors.failed',
} as const satisfies Record<PasskeyFailure, string>

/** Codes de la bibliothèque (les plus précis), puis noms d'erreur WebAuthn du navigateur */
const FAILURES_BY_CODE: Readonly<Record<string, PasskeyFailure>> = {
  ERROR_CEREMONY_ABORTED: 'cancelled',
  ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED: 'duplicate',
  ERROR_AUTHENTICATOR_MISSING_DISCOVERABLE_CREDENTIAL_SUPPORT: 'unsupported',
  ERROR_AUTHENTICATOR_MISSING_USER_VERIFICATION_SUPPORT: 'unsupported',
  ERROR_AUTHENTICATOR_NO_SUPPORTED_PUBKEYCREDPARAMS_ALG: 'unsupported',
}
const FAILURES_BY_NAME: Readonly<Record<string, PasskeyFailure>> = {
  // Fenêtre fermée, délai dépassé ou aucune clé choisie : le navigateur ne dit pas lequel
  NotAllowedError: 'cancelled',
  AbortError: 'cancelled',
  InvalidStateError: 'duplicate',
  NotSupportedError: 'unsupported',
}

/** Geste refusé ou impossible sur l'appareil, avec un message traduit pour l'adhérent. */
export class PasskeyError extends Error {
  constructor(readonly failure: PasskeyFailure) {
    super(i18n.global.t(MESSAGES[failure]))
    this.name = 'PasskeyError'
  }

  get cancelled(): boolean {
    return this.failure === 'cancelled'
  }

  static from(error: unknown): PasskeyError {
    if (!(error instanceof Error)) return new PasskeyError('failed')
    const code = 'code' in error && typeof error.code === 'string' ? error.code : ''
    return new PasskeyError(FAILURES_BY_CODE[code] ?? FAILURES_BY_NAME[error.name] ?? 'failed')
  }
}

function requestOptions(value: unknown): PublicKeyCredentialRequestOptionsJSON {
  const d = obj(value, 'options de clé d’accès')
  str(d.challenge, 'défi')
  return d as unknown as PublicKeyCredentialRequestOptionsJSON
}

function creationOptions(value: unknown): PublicKeyCredentialCreationOptionsJSON {
  const d = obj(value, 'options de clé d’accès')
  str(d.challenge, 'défi')
  str(obj(d.user, 'compte').id, 'identifiant')
  return d as unknown as PublicKeyCredentialCreationOptionsJSON
}

/**
 * Clés d'accès (WebAuthn) : connexion sans mot de passe, deuxième étape du bureau et clés du
 * compte. L'API fournit les options et vérifie chaque réponse ; la bibliothèque du navigateur
 * n'est téléchargée qu'au premier geste.
 */
export class PasskeyService {
  constructor(
    private readonly api: ApiClient,
    private readonly webAuthn: () => Promise<WebAuthnBrowser> = () => import('@simplewebauthn/browser'),
    private readonly available: () => boolean = () => typeof globalThis.PublicKeyCredential === 'function',
  ) {}

  /** Navigateur capable d'utiliser une clé d'accès (sinon, aucun bouton n'en propose) */
  isSupported(): boolean {
    return this.available()
  }

  /** Connexion directe : l'appareil propose ses clés pour ce site */
  async login(): Promise<LoginResult> {
    const assertion = await this.sign(() => this.api.post('/api/auth/passkey/options'))
    return AuthService.loginResult(await this.api.post('/api/auth/passkey/login', { ...assertion }))
  }

  /** Deuxième étape du bureau, après le mot de passe : la signature de la clé tient lieu de code */
  async confirmTwoFactor(): Promise<Profile> {
    const assertion = await this.sign(() => this.api.post('/api/auth/2fa/passkey'))
    return Profile.parse(await this.api.post('/api/auth/2fa/check', { code: JSON.stringify(assertion) }))
  }

  async list(): Promise<Passkey[]> {
    return Passkey.parseList(await this.api.get('/api/me/passkeys'))
  }

  /** Crée une clé sur cet appareil ; renvoie les clés du compte */
  async add(): Promise<Passkey[]> {
    const [{ startRegistration }, options] = await this.prepare(() => this.api.post('/api/me/passkeys/options'))
    const optionsJSON = creationOptions(options)
    const credential = await PasskeyService.attempt(() => startRegistration({ optionsJSON }))
    return Passkey.parseList(await this.api.post('/api/me/passkeys', { ...credential }))
  }

  async remove(id: string): Promise<void> {
    await this.api.delete(`/api/me/passkeys/${encodeURIComponent(id)}`)
  }

  private async sign(request: () => Promise<unknown>): Promise<AuthenticationResponseJSON> {
    const [{ startAuthentication }, options] = await this.prepare(request)
    const optionsJSON = requestOptions(options)
    return PasskeyService.attempt(() => startAuthentication({ optionsJSON }))
  }

  /**
   * Bibliothèque et options de l'API en parallèle : la fenêtre de l'appareil suit vite le geste
   * (Safari l'exige). Sans WebAuthn, rien n'est demandé.
   */
  private async prepare(request: () => Promise<unknown>): Promise<[WebAuthnBrowser, unknown]> {
    if (!this.available()) throw new PasskeyError('unsupported')
    return Promise.all([this.library(), request()])
  }

  private async library(): Promise<WebAuthnBrowser> {
    try {
      return await this.webAuthn()
    } catch {
      throw new ApiError(0, i18n.global.t('errors.network'))
    }
  }

  private static async attempt<T>(ceremony: () => Promise<T>): Promise<T> {
    try {
      return await ceremony()
    } catch (e) {
      throw PasskeyError.from(e)
    }
  }
}
