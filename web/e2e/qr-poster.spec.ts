import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

const secretary = {
  id: '01a1b2c3-0000-7000-8000-000000000004',
  email: 'nina@example.com',
  displayName: 'Nina',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_SECRETARY', 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: true,
  twoFactorSetupRequired: false,
}
const evening = {
  id: 'e1', title: 'Soirée fléchettes', location: 'Salle des fêtes', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00',
  season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 3, isRegistered: false, full: false, started: false,
  past: false, hasSummary: false, public: true, city: 'Bellevesvre', publicPath: '/e/soiree-flechettes-2026-11-14-a1b2c3',
}
const membersOnly = { ...evening, id: 'e2', title: 'Soirée du club', public: false, city: null, publicPath: null }

async function openPosterPage(page: Page, path = '/evenements/affiche'): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: secretary }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/events?*', (route) => route.fulfill({ json: { items: [evening, membersOnly], canManage: true } }))
  // QR codes servis par l'API : une image SVG quelconque suffit à l'aperçu
  await page.route('**/qr.svg', (route) => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>' }))
  await page.goto(`/connexion?redirect=${encodeURIComponent(path)}`)
  await page.getByLabel('Email').fill(secretary.email)
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Affiche QR')
}

test('the secretary prints a QR poster of the site or of a public evening', async ({ page }) => {
  await openPosterPage(page)
  const poster = page.locator('.qr-poster')
  await expect(poster.getByRole('heading')).toHaveText('Rejoins le club')
  await expect(poster.getByRole('img', { name: /QR code vers/ })).toHaveAttribute('src', '/api/qr/site.svg')
  // Seules les soirées publiques ont une page à scanner
  await expect(page.getByRole('radio', { name: /Soirée du club/ })).toHaveCount(0)

  await page.getByRole('radio', { name: /Soirée fléchettes/ }).check()
  await expect(poster.getByRole('heading')).toHaveText('Samedi 14 novembre')
  await expect(poster).toContainText('20 h · Bellevesvre')
  await expect(poster).toContainText('/e/soiree-flechettes-2026-11-14-a1b2c3')
  await expect(poster.getByRole('img', { name: /QR code vers/ })).toHaveAttribute('src', '/api/events/e1/qr.svg')
  await expect(page.getByRole('link', { name: 'Télécharger le QR code (SVG)' })).toHaveAttribute('href', '/api/events/e1/qr.svg')

  await page.getByRole('radio', { name: /Noir/ }).check()
  await expect(poster).toHaveClass(/qr-poster--dark/)
  await page.getByRole('radio', { name: 'A4' }).check()
  await expect(poster).toHaveClass(/qr-poster--a4/)

  // L'impression passe par la boîte du navigateur : seule l'affiche est marquée pour sortir
  await page.evaluate(() => {
    window.print = () => {
      document.body.dataset.printedWith = document.documentElement.dataset.print ?? ''
    }
  })
  await page.getByRole('button', { name: 'Imprimer l’affiche' }).click()
  await expect(page.locator('body')).toHaveAttribute('data-printed-with', 'qr-poster')
})

test('the evening page links to its poster', async ({ page }) => {
  await openPosterPage(page, '/evenements/affiche?soiree=e1')
  await expect(page.getByRole('radio', { name: /Soirée fléchettes/ })).toBeChecked()
  await expect(page.locator('.qr-poster').getByRole('heading')).toHaveText('Samedi 14 novembre')
})

test('the poster page meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await openPosterPage(page)
  await expect(page.locator('.qr-poster')).toBeVisible()
  await auditBothThemes(page)
})
