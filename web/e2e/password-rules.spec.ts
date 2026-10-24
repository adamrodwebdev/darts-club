import { test, expect, type Page } from '@playwright/test'

const admin = {
  id: '01a1b2c3-0000-7000-8000-000000000003',
  email: 'admin@example.com',
  displayName: 'Admin',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_ADMIN', 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: true,
  twoFactorSetupRequired: false,
}
const member = {
  id: 'm1', displayName: 'Claire', email: 'claire@example.com', phone: null, avatarUrl: null, roles: [], admin: false, badges: [],
  verified: true, approved: true, twoFactorEnabled: false, suspended: false, suspensionReason: null, membership: 'payee',
}

async function logInTo(page: Page, path: string, profile: object): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/admin/members?*', (route) => route.fulfill({ json: { items: [member] } }))
  await page.goto(`/connexion?redirect=${path}`)
  await page.getByLabel('Email').fill(profile === admin ? admin.email : 'president@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
}

test('the sign-up form ticks the password rules as they are met, and shows the password on demand', async ({ page }) => {
  await page.goto('/inscription')
  const password = page.getByLabel('Mot de passe')
  const rule = (text: string) => page.getByRole('listitem').filter({ hasText: text })

  await password.fill('flèche')
  await expect(rule('6 caractères au moins')).toContainText('(respecté)')
  await expect(rule('un chiffre')).toContainText('(à respecter)')
  await expect(rule('un caractère spécial')).toContainText('(à respecter)')

  await password.fill('fl3ch!')
  for (const text of ['6 caractères au moins', 'un chiffre', 'un caractère spécial']) {
    await expect(rule(text)).toContainText('(respecté)')
  }

  const reveal = page.getByRole('button', { name: 'Afficher la saisie' })
  await reveal.click()
  await expect(password).toHaveAttribute('type', 'text')
  await expect(reveal).toHaveAttribute('aria-pressed', 'true')
  await reveal.click()
  await expect(password).toHaveAttribute('type', 'password')
})

test('the administrator sends a password link to a member who forgot theirs', async ({ page }) => {
  let sent = false
  await page.route('**/api/admin/members/m1/password-link', (route) => {
    sent = true
    return route.fulfill({ status: 202 })
  })

  await logInTo(page, '/admin', admin)
  await page.getByRole('button', { name: 'Envoyer un lien de mot de passe' }).click()
  const dialog = page.getByRole('dialog', { name: 'Envoyer un lien de mot de passe à Claire ?' })
  await expect(dialog).toContainText('Claire reçoit à claire@example.com un lien valable une heure')
  await dialog.getByRole('button', { name: 'Envoyer', exact: true }).click()

  await expect(page.getByRole('status')).toHaveText('Lien envoyé à claire@example.com.')
  expect(sent).toBe(true)
})

test('the president does not get the password link button', async ({ page }) => {
  await logInTo(page, '/admin', { ...admin, email: 'president@example.com', roles: ['ROLE_PRESIDENT', 'ROLE_MEMBER'] })
  await expect(page.getByText('claire@example.com')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Envoyer un lien de mot de passe' })).toHaveCount(0)
})
