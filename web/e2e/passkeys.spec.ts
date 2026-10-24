import { generateKeyPairSync } from 'node:crypto'
import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

const board = {
  id: '01a1b2c3-0000-7000-8000-000000000002',
  email: 'claire@example.com',
  displayName: 'Claire',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_PRESIDENT', 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
  passkeyRequired: true,
  passkeySetupRequired: true,
  hasPasskey: false,
  approved: true,
}
const withPasskey = { ...board, passkeySetupRequired: false, hasPasskey: true }
const userHandle = 'claire-handle'
const base64url = (text: string): string => Buffer.from(text).toString('base64url')
const requestOptions = { challenge: base64url('request-challenge-0123456789'), rpId: 'localhost', allowCredentials: [], userVerification: 'required', timeout: 60000 }

/** API simulée commune aux parcours connectés */
async function mockSession(page: Page, profile: object): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
}

async function logIn(page: Page, path: string): Promise<void> {
  await page.goto(`/connexion?redirect=${path}`)
  await page.getByLabel('Email').fill(board.email)
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
}

/**
 * Authentificateur virtuel de Chromium : il répond comme un téléphone (empreinte acceptée), sans
 * fenêtre. Renvoie de quoi y déposer une clé existante.
 */
async function virtualAuthenticator(page: Page): Promise<(credentialId: string) => Promise<void>> {
  const cdp = await page.context().newCDPSession(page)
  await cdp.send('WebAuthn.enable')
  const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true },
  })
  return async (credentialId) => {
    const { privateKey } = generateKeyPairSync('ec', { namedCurve: 'P-256' })
    await cdp.send('WebAuthn.addCredential', {
      authenticatorId,
      credential: {
        credentialId: Buffer.from(credentialId).toString('base64'),
        isResidentCredential: true,
        rpId: 'localhost',
        privateKey: privateKey.export({ format: 'der', type: 'pkcs8' }).toString('base64'),
        userHandle: Buffer.from(userHandle).toString('base64'),
        signCount: 0,
      },
    })
  }
}

// Authentificateur virtuel par le protocole de Chromium : projets Firefox et WebKit exclus (playwright.config.ts)
test.describe('passkeys in the browser', { tag: '@webauthn' }, () => {
  test('a board member creates their passkey after the password, then logs in with it alone', async ({ page }) => {
    await virtualAuthenticator(page)
    await mockSession(page, board)
    const registered: unknown[] = []
    await page.route('**/api/me/passkeys/options', (route) =>
      route.fulfill({
        json: {
          rp: { name: 'Skull Darts 71', id: 'localhost' },
          user: { id: base64url(userHandle), name: board.email, displayName: board.displayName },
          challenge: base64url('creation-challenge-0123456789'),
          pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
          authenticatorSelection: { residentKey: 'required', userVerification: 'required' },
          attestation: 'none',
          timeout: 60000,
        },
      }),
    )
    await page.route('**/api/me/passkeys', (route) => {
      registered.push(route.request().postDataJSON())
      return route.fulfill({ status: 201, json: { items: [{ id: 'k1', label: 'Chrome', synced: false, createdAt: '2026-10-24T09:00:00+02:00', lastUsedAt: null }] } })
    })
    await page.route('**/api/auth/me', (route) => route.fulfill({ json: withPasskey }))

    await logIn(page, '/evenements')
    await expect(page).toHaveURL(/:\d+\/compte\/cle-d-acces\?redirect=\/evenements$/)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Crée ta clé d’accès')
    await auditBothThemes(page)
    await page.getByRole('button', { name: 'Créer ma clé d’accès' }).click()

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Clé d’accès créée')
    await auditBothThemes(page)
    expect(registered).toEqual([expect.objectContaining({ type: 'public-key', response: expect.objectContaining({ attestationObject: expect.any(String) }) })])
    await expect(page.getByRole('link', { name: 'Continuer' })).toHaveAttribute('href', '/evenements')

    // Nouvelle visite sans session : la clé restée sur l'appareil suffit
    let signed: unknown = null
    await page.route('**/api/auth/passkey/options', (route) => route.fulfill({ json: requestOptions }))
    await page.route('**/api/auth/passkey/login', (route) => {
      signed = route.request().postDataJSON()
      return route.fulfill({ json: withPasskey })
    })
    await page.goto('/connexion')
    await page.getByRole('button', { name: 'Se connecter avec une clé d’accès' }).click()

    await expect(page).toHaveURL(/:\d+\/compte$/)
    expect(signed).toMatchObject({ type: 'public-key', response: { signature: expect.any(String), userHandle: base64url(userHandle) } })
  })

  test('the board confirms with its passkey instead of a code', async ({ page }) => {
    const addKey = await virtualAuthenticator(page)
    await addKey('cred-claire')
    await mockSession(page, withPasskey)
    await page.route('**/api/auth/login', (route) => route.fulfill({ status: 202, json: { twoFactorRequired: true, method: 'passkey' } }))
    await page.route('**/api/auth/2fa/passkey', (route) =>
      route.fulfill({ json: { ...requestOptions, allowCredentials: [{ type: 'public-key', id: base64url('cred-claire') }] } }),
    )
    let code = ''
    await page.route('**/api/auth/2fa/check', (route) => {
      code = String(route.request().postDataJSON().code)
      return route.fulfill({ json: withPasskey })
    })

    // La clé est demandée d'elle-même après le mot de passe
    await logIn(page, '/compte')
    await expect(page).toHaveURL(/:\d+\/compte$/)
    expect(JSON.parse(code)).toMatchObject({ id: base64url('cred-claire'), response: { signature: expect.any(String) } })
  })

  test('the passkey step waits for a tap when the device does not answer by itself', async ({ page }) => {
    // Aucune clé sur l'appareil : le lancement automatique échoue comme sans geste (Safari)
    await virtualAuthenticator(page)
    await mockSession(page, withPasskey)
    await page.route('**/api/auth/login', (route) => route.fulfill({ status: 202, json: { twoFactorRequired: true, method: 'passkey' } }))
    await page.route('**/api/auth/2fa/passkey', (route) =>
      route.fulfill({ json: { ...requestOptions, allowCredentials: [{ type: 'public-key', id: base64url('absent') }], timeout: 1000 } }),
    )
    let loggedOut = false
    await page.route('**/api/auth/logout', (route) => {
      loggedOut = true
      return route.fulfill({ status: 204 })
    })

    await logIn(page, '/compte')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Confirme que c’est bien toi')
    await expect(page.getByRole('button', { name: 'Utiliser ma clé d’accès' })).toBeEnabled()
    await expect(page.getByRole('alert')).toHaveCount(0)
    await auditBothThemes(page)

    await page.getByRole('button', { name: 'Annuler' }).click()
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Connexion')
    expect(loggedOut).toBe(true)
  })

  test('keys are listed, and changing them long after the login asks to log in again', async ({ page }) => {
    await virtualAuthenticator(page)
    await mockSession(page, withPasskey)
    await page.route('**/api/me/passkeys', (route) =>
      route.fulfill({ json: { items: [{ id: 'k1', label: 'iPhone', synced: true, createdAt: '2026-10-24T09:00:00+02:00', lastUsedAt: null }] } }),
    )
    await page.route('**/api/me/passkeys/options', (route) =>
      route.fulfill({ status: 403, json: { error: 'Reconnecte-toi pour modifier tes clés d’accès (sécurité).', code: 'reauthentication_required' } }),
    )

    await logIn(page, '/compte/securite')
    const card = page.getByRole('region', { name: 'Clés d’accès' })
    await expect(card.getByRole('listitem')).toHaveCount(1)
    await expect(card).toContainText('iPhone')
    await expect(card).toContainText('synchronisée')
    // Le code à 6 chiffres n'a plus lieu d'être pour le bureau
    await expect(page.getByRole('heading', { name: 'Double authentification' })).toHaveCount(0)
    await auditBothThemes(page)

    await card.getByRole('button', { name: 'Ajouter une clé d’accès' }).click()
    await expect(card.getByRole('alert')).toHaveText('Reconnecte-toi pour modifier tes clés d’accès (sécurité).')
    await expect(card.getByRole('button', { name: 'Me reconnecter' })).toBeVisible()
  })
})

test('the administrator resets the access of a member who lost their device', async ({ page }) => {
  await mockSession(page, { ...board, roles: ['ROLE_ADMIN', 'ROLE_MEMBER'], twoFactorEnabled: true, passkeyRequired: false, passkeySetupRequired: false })
  const member = {
    id: 'm1', displayName: 'Claire', email: 'claire@example.com', phone: null, avatarUrl: null, roles: ['president'], admin: false, badges: [],
    verified: true, approved: true, twoFactorEnabled: false, passkeys: 2, suspended: false, suspensionReason: null, membership: 'payee',
  }
  await page.route('**/api/admin/members?*', (route) => route.fulfill({ json: { items: [member] } }))
  let reset = false
  await page.route('**/api/admin/members/m1/passkeys/reset', (route) => {
    reset = true
    return route.fulfill({ status: 204 })
  })

  await logIn(page, '/admin')
  await expect(page.getByText('2 clés d’accès')).toBeVisible()
  await page.getByRole('button', { name: 'Réinitialiser l’accès' }).click()

  const dialog = page.getByRole('dialog', { name: 'Réinitialiser l’accès de Claire ?' })
  await expect(dialog).toContainText('Ses 2 clés d’accès seront retirées et ses sessions fermées.')
  await auditBothThemes(page)
  await dialog.getByRole('button', { name: 'Réinitialiser', exact: true }).click()
  await expect(page.getByRole('status')).toHaveText('Accès de Claire réinitialisé.')
  expect(reset).toBe(true)
})
