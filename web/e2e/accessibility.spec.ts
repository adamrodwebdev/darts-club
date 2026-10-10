import AxeBuilder from '@axe-core/playwright'
import { test, expect, type Page } from '@playwright/test'

/** Audit WCAG 2.2 niveau AA (axe-core) des pages principales. */
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

async function audit(page: Page): Promise<void> {
  // Laisse les animations d'apparition se terminer
  await page.emulateMedia({ reducedMotion: 'reduce' })
  const results = await new AxeBuilder({ page }).withTags(WCAG).analyze()
  const summary = results.violations.map((v) => `${v.id} (${v.impact}) : ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)
  expect(summary).toEqual([])
}

for (const path of ['/', '/connexion', '/inscription', '/mentions-legales', '/confidentialite']) {
  test(`public page ${path} meets WCAG 2.2 AA`, async ({ page }) => {
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    await audit(page)
  })
}

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001', email: 'alice@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
  roles: ['ROLE_MEMBER'], hasPassword: true, twoFactorEnabled: false, twoFactorSetupRequired: false,
  badges: [{ id: 'cotisant', label: 'Cotisant' }],
}

const memberPages: [string, string][] = [
  ['/evenements', 'Événements'],
  ['/messages', 'Messages'],
  ['/notifications', 'Notifications'],
  ['/compte', 'Mon compte'],
]

for (const [path, heading] of memberPages) {
  test(`member page ${path} meets WCAG 2.2 AA`, async ({ page }) => {
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

    await page.goto(`/connexion?redirect=${path}`)
    await page.getByLabel('Email').fill('alice@example.com')
    await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)
    await audit(page)
  })
}
