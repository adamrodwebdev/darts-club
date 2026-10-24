import { describe, expect, it } from 'vitest'
import { ApiClient } from '@/api/ApiClient'
import { ApiError } from '@/api/ApiError'
import { missingProtection, parseAdminMember } from '@/models/Admin'
import { Passkey } from '@/models/Passkey'
import { Profile } from '@/models/Profile'
import { internalPath } from '@/router/redirect'
import { AuthService } from '../AuthService'
import { PasskeyError, PasskeyService, type WebAuthnBrowser } from '../PasskeyService'

const profile = {
  id: 'u1',
  email: 'tresor@example.com',
  displayName: 'Trésorier',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_TREASURER', 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}
const key = { id: 'k1', label: 'iPhone', synced: true, createdAt: '2026-10-24T09:00:00+02:00', lastUsedAt: null }
const requestOptions = { challenge: 'Y2hhbGxlbmdl', rpId: 'localhost', allowCredentials: [], userVerification: 'required' }
const creationOptions = { challenge: 'Y2hhbGxlbmdl', rp: { name: 'Skull Darts 71', id: 'localhost' }, user: { id: 'dXNlcg', name: 'a', displayName: 'A' } }
const assertion = { id: 'cred', rawId: 'cred', type: 'public-key', response: { signature: 'sig' }, clientExtensionResults: {} }

interface Call {
  method: string
  path: string
  body: unknown
}

/** API simulée : réponses par « MÉTHODE chemin », appels enregistrés */
function fakeApi(responses: Record<string, unknown>): { api: ApiClient; calls: Call[] } {
  const calls: Call[] = []
  const fetchFn: typeof fetch = async (input, init) => {
    const method = init?.method ?? 'GET'
    const path = String(input)
    calls.push({ method, path, body: typeof init?.body === 'string' ? JSON.parse(init.body) : undefined })
    return new Response(JSON.stringify(responses[`${method} ${path}`] ?? {}), { headers: { 'Content-Type': 'application/json' } })
  }
  return { api: new ApiClient('', fetchFn, () => 'fr'), calls }
}

/** Navigateur simulé : renvoie la réponse donnée, ou échoue comme WebAuthn */
function fakeBrowser(outcome: { result?: unknown; error?: Error }): { browser: () => Promise<WebAuthnBrowser>; options: unknown[] } {
  const options: unknown[] = []
  const ceremony = async (opts: { optionsJSON: unknown }) => {
    options.push(opts.optionsJSON)
    if (outcome.error) throw outcome.error
    return outcome.result as never
  }
  return { browser: async () => ({ startAuthentication: ceremony, startRegistration: ceremony }), options }
}

function webAuthnError(name: string, code?: string): Error {
  return Object.assign(new Error('browser message'), { name, code })
}

describe('PasskeyService', () => {
  it('logs in with a passkey alone and opens the session', async () => {
    const { api, calls } = fakeApi({ 'POST /api/auth/passkey/options': requestOptions, 'POST /api/auth/passkey/login': profile })
    const { browser, options } = fakeBrowser({ result: assertion })

    const result = await new PasskeyService(api, browser, () => true).login()

    expect(options).toEqual([requestOptions])
    expect(calls.map((c) => `${c.method} ${c.path}`)).toEqual(['POST /api/auth/passkey/options', 'POST /api/auth/passkey/login'])
    expect(calls[1]?.body).toEqual(assertion)
    expect(result.status === 'authenticated' && result.profile.email).toBe('tresor@example.com')
  })

  it('reports the administrator’s code step after a passkey login', async () => {
    const { api } = fakeApi({ 'POST /api/auth/passkey/options': requestOptions, 'POST /api/auth/passkey/login': { twoFactorRequired: true, method: 'totp' } })
    const { browser } = fakeBrowser({ result: assertion })

    expect(await new PasskeyService(api, browser, () => true).login()).toEqual({ status: 'two-factor', method: 'totp' })
  })

  it('sends the signature as the second-step code', async () => {
    const { api, calls } = fakeApi({ 'POST /api/auth/2fa/passkey': requestOptions, 'POST /api/auth/2fa/check': profile })
    const { browser } = fakeBrowser({ result: assertion })

    const signedIn = await new PasskeyService(api, browser, () => true).confirmTwoFactor()

    expect(calls[1]).toEqual({ method: 'POST', path: '/api/auth/2fa/check', body: { code: JSON.stringify(assertion) } })
    expect(signedIn.email).toBe('tresor@example.com')
  })

  it('creates a key on this device and returns the account’s keys', async () => {
    const credential = { id: 'cred', rawId: 'cred', type: 'public-key', response: { attestationObject: 'att' }, clientExtensionResults: {} }
    const { api, calls } = fakeApi({ 'POST /api/me/passkeys/options': creationOptions, 'POST /api/me/passkeys': { items: [key] } })
    const { browser, options } = fakeBrowser({ result: credential })

    const keys = await new PasskeyService(api, browser, () => true).add()

    expect(options).toEqual([creationOptions])
    expect(calls[1]?.body).toEqual(credential)
    expect(keys.map((k) => k.label)).toEqual(['iPhone'])
  })

  it('turns browser failures into translated reasons', async () => {
    const cases: [Error, string][] = [
      [webAuthnError('NotAllowedError', 'ERROR_PASSTHROUGH_SEE_CAUSE_PROPERTY'), 'cancelled'],
      [webAuthnError('InvalidStateError', 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED'), 'duplicate'],
      [webAuthnError('NotSupportedError'), 'unsupported'],
      [webAuthnError('SecurityError'), 'failed'],
    ]
    for (const [error, failure] of cases) {
      const { api } = fakeApi({ 'POST /api/auth/passkey/options': requestOptions })
      const attempt = new PasskeyService(api, fakeBrowser({ error }).browser, () => true).login()
      await expect(attempt).rejects.toMatchObject({ name: 'PasskeyError', failure })
    }
    // Typographie française : espace insécable avant les deux-points
    expect(new PasskeyError('cancelled').message).toBe('Clé d’accès non utilisée : réessaie quand tu veux.')
  })

  it('neither loads the library nor calls the API without WebAuthn', async () => {
    const { api, calls } = fakeApi({})
    let loaded = false
    const service = new PasskeyService(
      api,
      async () => {
        loaded = true
        return fakeBrowser({ result: assertion }).browser()
      },
      () => false,
    )

    expect(service.isSupported()).toBe(false)
    await expect(service.add()).rejects.toMatchObject({ failure: 'unsupported' })
    expect(loaded).toBe(false)
    expect(calls).toEqual([])
  })

  it('reports a library that fails to download as a network error', async () => {
    const { api } = fakeApi({ 'POST /api/auth/passkey/options': requestOptions })
    const service = new PasskeyService(api, () => Promise.reject(new Error('Failed to fetch')), () => true)

    await expect(service.login()).rejects.toBeInstanceOf(ApiError)
  })
})

describe('AuthService.loginResult', () => {
  it('reads the second-step method, the code by default', () => {
    expect(AuthService.loginResult({ twoFactorRequired: true, method: 'passkey' })).toEqual({ status: 'two-factor', method: 'passkey' })
    expect(AuthService.loginResult({ twoFactorRequired: true })).toEqual({ status: 'two-factor', method: 'totp' })
    expect(AuthService.loginResult(profile).status).toBe('authenticated')
  })
})

describe('passkey models', () => {
  it('parses a key and picks its pictogram', () => {
    const parsed = Passkey.parse(key)
    expect(parsed.createdAt.toISOString()).toBe('2026-10-24T07:00:00.000Z')
    expect(parsed.lastUsedAt).toBeNull()
    expect(parsed.device).toBe('phone')
    expect(Passkey.parse({ ...key, label: 'Windows Hello' }).device).toBe('computer')
    expect(Passkey.parse({ ...key, label: '' }).device).toBe('key')
  })

  it('defaults the passkey fields of an older API', () => {
    const parsed = Profile.parse(profile)
    expect([parsed.passkeyRequired, parsed.passkeySetupRequired, parsed.hasPasskey, parsed.approved]).toEqual([false, false, false, true])
    expect(Profile.parse({ ...profile, passkeyRequired: true, hasPasskey: true }).hasPasskey).toBe(true)
  })

  it('flags the protection a board member still lacks', () => {
    const member = parseAdminMember({
      id: 'm1', displayName: 'Bob', email: 'bob@example.com', phone: null, avatarUrl: null, roles: ['tresorier'], admin: false,
      badges: [], verified: true, approved: true, twoFactorEnabled: false, suspended: false, suspensionReason: null, membership: null,
    })
    expect(missingProtection(member)).toBe('passkey')
    expect(missingProtection({ ...member, passkeys: 1 })).toBeNull()
    expect(missingProtection({ ...member, twoFactorEnabled: true })).toBeNull()
    expect(missingProtection({ ...member, roles: [], admin: true, passkeys: 2 })).toBe('twoFactor')
    expect(missingProtection({ ...member, roles: [] })).toBeNull()
  })
})

describe('internalPath', () => {
  it('keeps the site’s own paths only', () => {
    expect(internalPath('/evenements?vue=liste')).toBe('/evenements?vue=liste')
    for (const outside of ['//evil.example', '/\\evil.example', 'https://evil.example', 'evenements', undefined, ['/a']]) {
      expect(internalPath(outside)).toBe('/compte')
    }
  })
})
