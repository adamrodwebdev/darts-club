import { test, expect, type Page } from '@playwright/test'

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001',
  email: 'alice@example.com',
  displayName: 'Alice',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}

/** API simulée : le front est testé sans backend. */
async function mockApi(page: Page): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 2 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/conversations', (route) =>
    route.fulfill({ json: { items: [], mailboxes: [{ id: 'president', label: 'Présidence' }] } }),
  )
}

test('protected pages redirect to the login form', async ({ page }) => {
  await page.goto('/messages')
  await expect(page).toHaveURL(/\/connexion\?redirect=\/messages$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Connexion')
})

test('member logs in and lands on the requested page', async ({ page }) => {
  await mockApi(page)
  await page.goto('/messages')

  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(page).toHaveURL(/\/messages$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Messages')
  await expect(page.getByRole('link', { name: /Notifications, 2 non lues/ })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Mon compte' })).toBeVisible()
})

test('registration shows a neutral confirmation', async ({ page }) => {
  await page.route('**/api/auth/register', (route) => route.fulfill({ status: 202, json: { message: 'ok' } }))
  await page.goto('/inscription')

  await page.getByLabel('Email').fill('new@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Créer mon compte' }).click()

  await expect(page.getByRole('status')).toContainText('Vérifie ta boîte mail')
})

test('two-factor login asks for a code after the password', async ({ page }) => {
  await page.route('**/api/auth/login', (route) => route.fulfill({ status: 202, json: { twoFactorRequired: true } }))
  await page.route('**/api/auth/2fa/check', (route) =>
    route.fulfill({ json: { ...profile, twoFactorEnabled: true } }),
  )
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.goto('/connexion')

  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await page.getByLabel('Code de vérification').fill('123456')
  await page.getByRole('button', { name: 'Vérifier' }).click()
  await expect(page).toHaveURL(/\/compte$/)
})
