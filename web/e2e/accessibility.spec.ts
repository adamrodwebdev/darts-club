import AxeBuilder from '@axe-core/playwright'
import { test, expect, type Page } from '@playwright/test'

/** Audit WCAG 2.2 niveau AA (axe-core) des pages principales. */
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const THEMES = ['light', 'dark'] as const

async function audit(page: Page): Promise<void> {
  // Laisse les animations d'apparition se terminer
  await page.emulateMedia({ reducedMotion: 'reduce' })
  const results = await new AxeBuilder({ page }).withTags(WCAG).analyze()
  const summary = results.violations.map((v) => `${v.id} (${v.impact}) : ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)
  expect(summary).toEqual([])
}

// Contrastes vérifiés dans les deux thèmes (thème du système émulé)
for (const theme of THEMES) {
  for (const path of ['/', '/en', '/connexion', '/inscription', '/mentions-legales', '/confidentialite']) {
    test(`public page ${path} meets WCAG 2.2 AA in ${theme} theme`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: theme })
      await page.goto(path)
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      await audit(page)
    })
  }
}

test.describe('in English', () => {
  test.use({ locale: 'en-GB' })

  for (const theme of THEMES) {
    for (const [path, heading] of [['/connexion', 'Log in'], ['/inscription', 'Join the club'], ['/confidentialite', 'Privacy policy']]) {
      test(`public page ${path} meets WCAG 2.2 AA in ${theme} theme`, async ({ page }) => {
        await page.emulateMedia({ colorScheme: theme })
        await page.goto(path)
        await expect(page.locator('html')).toHaveAttribute('lang', 'en')
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)
        await audit(page)
      })
    }
  }
})

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001', email: 'alice@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
  roles: ['ROLE_MEMBER'], hasPassword: true, twoFactorEnabled: false, twoFactorSetupRequired: false,
  badges: [{ id: 'cotisant', label: 'Cotisant' }],
}

/** Espace adhérent, dans chaque langue : chemin, titre attendu */
const memberPages: { locale: string; login: [string, string, string]; pages: [string, string][] }[] = [
  {
    locale: 'fr-FR',
    login: ['Email', 'Mot de passe', 'Se connecter'],
    pages: [
      ['/evenements', 'Événements'],
      ['/messages', 'Messages'],
      ['/notifications', 'Notifications'],
      ['/compte', 'Mon compte'],
    ],
  },
  {
    locale: 'en-GB',
    login: ['Email', 'Password', 'Log in'],
    pages: [
      ['/evenements', 'Events'],
      ['/compte', 'My account'],
    ],
  },
]

for (const { locale, login, pages } of memberPages) {
  test.describe(`member area (${locale})`, () => {
    test.use({ locale })

    for (const [path, heading] of pages) {
      test(`member page ${path} meets WCAG 2.2 AA in dark theme`, async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' })
        await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
        await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
        await page.route('**/api/notifications*', (route) =>
          route.fulfill({
            json: {
              items: [{ id: 'n1', category: 'evenement', label: 'Événement', title: 'Soirée vendredi', body: 'Bar du club', link: null, createdAt: '2026-10-10T10:00:00+00:00', read: false }],
              unread: 1,
            },
          }),
        )
        await page.route('**/api/events?*', (route) =>
          route.fulfill({
            json: {
              canManage: false,
              items: [{ id: 'e1', title: 'Soirée fléchettes', location: 'Bar du club', startsAt: '2026-10-16T18:00:00+00:00', endsAt: '2026-10-16T22:00:00+00:00', season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 3, isRegistered: true, full: false, started: false, past: false, hasSummary: false }],
            },
          }),
        )
        await page.route('**/api/conversations', (route) =>
          route.fulfill({ json: { items: [], mailboxes: [{ id: 'president', label: 'Présidence' }] } }),
        )

        const [emailLabel, passwordLabel, submit] = login
        await page.goto(`/connexion?redirect=${path}`)
        await page.getByLabel(emailLabel).fill('alice@example.com')
        await page.getByLabel(passwordLabel).fill('Tr1ple-20-Bullseye!')
        await page.getByRole('button', { name: submit }).click()
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)
        await audit(page)
      })
    }
  })
}
