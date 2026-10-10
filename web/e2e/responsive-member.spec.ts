import { test, expect } from '@playwright/test'

/** Pages connectées : l'en-tête complet (5 icônes) ne doit jamais déborder. */
const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001', email: 'alice@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
  roles: ['ROLE_MEMBER'], hasPassword: true, twoFactorEnabled: false, twoFactorSetupRequired: false, badges: [],
}

for (const width of [320, 360, 390, 768, 1024, 1440]) {
  test(`member header fits at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
    await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 3 } }))
    await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
    await page.route('**/api/events?*', (route) => route.fulfill({ json: { items: [], canManage: false } }))

    await page.goto('/connexion?redirect=/evenements')
    await page.getByLabel('Email').fill('alice@example.com')
    await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Événements')

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(0)
  })
}
