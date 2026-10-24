import { test, expect } from '@playwright/test'

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001',
  email: 'new@example.com',
  displayName: 'new',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}

test('a new member confirms their address with their password and is logged in', async ({ page }) => {
  const confirmations: string[] = []
  await page.route(/\/api\/auth\/verify\?/, (route) => {
    confirmations.push(route.request().url())
    return route.fulfill({ json: { email: 'new@example.com' } })
  })
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))

  await page.goto('/confirmer-email?expires=1792770480&id=01a1b2c3-0000-7000-8000-000000000001&signature=abc_def&token=x%2By%3D')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Confirmer mon adresse')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Confirmer et me connecter' }).click()

  await expect(page).toHaveURL(/\/compte$/)
  const sent = new URL(confirmations[0] ?? 'http://invalid')
  expect([sent.searchParams.get('signature'), sent.searchParams.get('token')]).toEqual(['abc_def', 'x+y='])
})

test('a forgotten password is replaced through the emailed link', async ({ page }) => {
  await page.route('**/api/auth/password/forgot', (route) => route.fulfill({ status: 202, json: { message: 'ok' } }))
  await page.route(/\/api\/auth\/password\/reset\?/, (route) => route.fulfill({ status: 204, body: '' }))

  await page.goto('/connexion')
  await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click()
  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByRole('button', { name: 'Recevoir le lien' }).click()
  await expect(page.getByRole('status')).toContainText('le lien vient de partir')

  await page.goto('/nouveau-mot-de-passe?id=01a1b2c3-0000-7000-8000-000000000001&expires=1792770480&token=abc')
  await page.getByLabel('Nouveau mot de passe').fill('Nouveau-Mot-De-Passe-2026!')
  await page.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(page).toHaveURL(/\/connexion\?reset=success$/)
  await expect(page.getByRole('status')).toContainText('Mot de passe changé')
})
