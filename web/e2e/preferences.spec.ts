import { test, expect } from '@playwright/test'

test.describe('theme', () => {
  test('follows the system theme, then remembers the chosen one without flash', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' })
    await page.goto('/connexion')
    const html = page.locator('html')
    await expect(html).toHaveAttribute('data-theme', 'light')

    // Système → clair → sombre
    await page.getByRole('button', { name: /^Thème : Système/ }).click()
    await page.getByRole('button', { name: /^Thème : Clair/ }).click()
    await expect(html).toHaveAttribute('data-theme', 'dark')
    await expect(page.getByRole('button', { name: /^Thème : Sombre/ })).toBeVisible()

    // Sans l'application (scripts bloqués), theme-init.js pose déjà le thème : aucun flash
    await page.route(/\/(assets\/.+\.js|src\/main\.ts)$/, (route) => route.abort())
    await page.reload()
    await expect(html).toHaveAttribute('data-theme', 'dark')
    await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#0b0b0d')
  })

  test('can be chosen explicitly from the account display settings', async ({ page }) => {
    await page.route('**/api/auth/login', (route) =>
      route.fulfill({
        json: {
          id: '01a1b2c3-0000-7000-8000-000000000001', email: 'alice@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
          roles: ['ROLE_MEMBER'], hasPassword: true, twoFactorEnabled: false, twoFactorSetupRequired: false, badges: [],
        },
      }),
    )
    await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
    await page.route('**/api/notifications*', (route) => route.fulfill({ json: { items: [], unread: 0 } }))

    await page.goto('/connexion')
    await page.getByLabel('Email').fill('alice@example.com')
    await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
    await page.getByRole('button', { name: 'Se connecter' }).click()

    const display = page.getByRole('region', { name: 'Affichage' })
    await display.getByRole('radio', { name: 'Clair' }).check()
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
    await display.getByRole('radio', { name: 'English' }).check()
    await expect(page.getByRole('heading', { name: 'Display' })).toBeVisible()
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  })
})

test.describe('animations', () => {
  test('follow the device setting, and the footer switch turns them off for good', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto('/')
    await expect(page.locator('html')).toHaveAttribute('data-motion', 'off')

    await page.emulateMedia({ reducedMotion: 'no-preference' })
    await page.reload()
    const html = page.locator('html')
    await expect(html).toHaveAttribute('data-motion', 'on')
    await expect(page.locator('.hero__title .split__word').first()).toHaveCSS('animation-name', /word-rise/)

    await page.getByRole('button', { name: /^Animations : Activées/ }).click()
    await expect(html).toHaveAttribute('data-motion', 'off')
    await expect(page.locator('.marquee__track')).toHaveCSS('animation-name', 'none')

    // Choix mémorisé, appliqué avant le rendu
    await page.reload()
    await expect(html).toHaveAttribute('data-motion', 'off')
    await expect(page.getByRole('button', { name: /^Animations : Désactivées/ })).toBeVisible()
  })
})

test.describe('account preferences', () => {
  test('are applied at login and saved when changed', async ({ page }) => {
    const profile = {
      id: '01a1b2c3-0000-7000-8000-000000000001', email: 'alice@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
      roles: ['ROLE_MEMBER'], hasPassword: true, twoFactorEnabled: false, twoFactorSetupRequired: false, badges: [],
      preferences: { language: 'en', theme: 'dark' },
    }
    const saved: unknown[] = []
    await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
    await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
    await page.route('**/api/notifications*', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
    await page.route('**/api/me/preferences', (route) => {
      saved.push(route.request().postDataJSON())
      return route.fulfill({ json: { ...profile, preferences: route.request().postDataJSON() } })
    })

    await page.emulateMedia({ colorScheme: 'light' })
    await page.goto('/connexion')
    await page.getByLabel('Email').fill('alice@example.com')
    await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
    await page.getByRole('button', { name: 'Se connecter' }).click()

    // Choix faits sur un autre appareil : anglais, thème sombre
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('My account')
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

    await page.getByRole('region', { name: 'Display' }).getByRole('radio', { name: 'Light' }).check()
    await expect.poll(() => saved).toContainEqual({ language: 'en', theme: 'light' })
  })
})

test.describe('language', () => {
  test('switches the home page to its English URL and remembers it', async ({ page }) => {
    await page.goto('/')
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr')

    await page.getByRole('button', { name: 'Switch to English' }).click()
    await expect(page).toHaveURL(/\/en$/)
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect(page.getByRole('link', { name: 'Join the club' }).first()).toBeVisible()
    await expect(page).toHaveTitle('Skull Darts 71 · Darts, fuzz and friendship')

    // Choix mémorisé : « / » mène à l'accueil anglais
    await page.goto('/')
    await expect(page).toHaveURL(/\/en$/)

    await page.getByRole('button', { name: 'Passer en français' }).click()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('link', { name: 'Rejoindre le club' }).first()).toBeVisible()
  })

  test('declares both home pages to search engines', async ({ page }) => {
    await page.goto('/')
    const origin = new URL(page.url()).origin

    await expect(page.locator('link[rel="alternate"][hreflang="fr"]')).toHaveAttribute('href', `${origin}/`)
    await expect(page.locator('link[rel="alternate"][hreflang="en"]')).toHaveAttribute('href', `${origin}/en`)
    await expect(page.locator('link[rel="alternate"][hreflang="x-default"]')).toHaveAttribute('href', `${origin}/`)
  })

  test.describe('with an English browser', () => {
    test.use({ locale: 'en-US' })

    test('detects English on app pages but keeps the French home page for indexing', async ({ page }) => {
      await page.goto('/connexion')
      await expect(page.getByRole('heading', { level: 1 })).toHaveText('Log in')

      await page.goto('/')
      await expect(page.getByRole('link', { name: 'Rejoindre le club' }).first()).toBeVisible()
    })
  })
})
