import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e2'
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000007',
  email: 'nadia@example.com',
  displayName: 'Nadia',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}

const tokens = [
  { name: 'Jeton vert', value: '1.00', bag: false, color: 'vert' },
  { name: 'Jeton jaune', value: '0.50', bag: false, color: 'jaune' },
  { name: 'Sachet', value: '10.00', bag: true, color: null },
]

const sale = (id: string, total: string, cancelled = false) => ({
  id,
  lines: [{ name: 'Sachet', value: '10.00', bag: true, color: null, count: 1 }],
  total,
  method: 'especes',
  soldAt: '2026-11-21T19:42:00+00:00',
  soldBy: 'Nadia',
  cancelled,
  cancelledAt: cancelled ? '2026-11-21T19:50:00+00:00' : null,
  cancelledBy: cancelled ? 'Nadia' : null,
})

function screenJson(overrides: Record<string, unknown> = {}) {
  return {
    event: { id: EVENT, title: 'Soirée fléchettes', startsAt: '2026-11-21T19:00:00+00:00' },
    tokensEnabled: true,
    tokens,
    cashFloat: '100.00',
    sales: [],
    taken: { cash: '0.00', card: '0.00' },
    ...overrides,
  }
}

async function openEntrance(page: Page, screen: object): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization*', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/service/entrance`, (route) => route.fulfill({ json: screen }))
  await page.goto(`/connexion?redirect=/evenements/${EVENT}/accueil`)
  await page.getByLabel('Email').fill('nadia@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Accueil')
}

test('the entrance sells tokens in cash and works out the change', async ({ page }) => {
  await openEntrance(page, screenJson())
  let posted: Record<string, unknown> | null = null
  await page.route(`**/api/events/${EVENT}/service/sales`, (route) => {
    posted = route.request().postDataJSON() as Record<string, unknown>
    return route.fulfill({ status: 201, json: screenJson({ sales: [sale('s1', '23.00')], taken: { cash: '23.00', card: '0.00' } }) })
  })

  await page.getByRole('button', { name: 'Ajouter : Sachet' }).click()
  await page.getByRole('button', { name: 'Ajouter : Sachet' }).click()
  for (let i = 0; i < 3; i++) await page.getByRole('button', { name: 'Ajouter : Jeton vert' }).click()
  await expect(page.getByText('3 × Jeton vert + 2 × Sachet')).toBeVisible()
  const saleCard = page.getByRole('region', { name: 'Vente de jetons' })
  await expect(saleCard).toContainText('23,00 €')

  await page.getByRole('button', { name: 'Billet de 20,00 €' }).click()
  await expect(saleCard).toContainText('billet trop petit')
  await page.getByRole('button', { name: 'Billet de 50,00 €' }).click()
  await expect(saleCard).toContainText('27,00 €')

  await page.getByRole('button', { name: 'Espèces', exact: true }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Vente enregistrée' })).toContainText('3 × Jeton vert + 2 × Sachet, 23,00 € en espèces.')
  expect(posted).toMatchObject({ method: 'especes', lines: [{ name: 'Jeton vert', count: 3 }, { name: 'Sachet', count: 2 }] })
  expect(String(posted?.['id'])).toMatch(UUID)
  await expect(page.getByRole('region', { name: 'Caisse de l’accueil ce soir' })).toContainText('23,00 €')
})

test('a sale is cancelled, kept crossed out', async ({ page }) => {
  await openEntrance(page, screenJson({ sales: [sale('s1', '10.00')], taken: { cash: '10.00', card: '0.00' } }))
  await page.route('**/api/token-sales/s1/cancel', (route) => route.fulfill({ json: screenJson({ sales: [sale('s1', '10.00', true)] }) }))
  await page.getByRole('button', { name: /^Annuler la vente de/ }).click()
  await expect(page.getByRole('region', { name: 'Dernières ventes' })).toContainText('Annulée')
  await expect(page.getByRole('status').filter({ hasText: 'annulée, inscrite au journal' })).toBeVisible()
})

test('without network the sale waits on the device, then leaves once with the same id', async ({ page }) => {
  await openEntrance(page, screenJson())
  const ids: string[] = []
  await page.route(`**/api/events/${EVENT}/service/sales`, (route) => {
    ids.push(String((route.request().postDataJSON() as Record<string, unknown>)['id']))
    return route.abort('internetdisconnected')
  })
  await page.getByRole('button', { name: 'Ajouter : Sachet' }).click()
  await page.getByRole('button', { name: 'Carte', exact: true }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Vente gardée sur l’appareil' })).toBeVisible()
  await expect(page.getByText('en attente d’envoi')).toBeVisible()
  await expect(page.getByText('Hors ligne')).toBeVisible()

  await page.unroute(`**/api/events/${EVENT}/service/sales`)
  await page.route(`**/api/events/${EVENT}/service/sales`, (route) => {
    ids.push(String((route.request().postDataJSON() as Record<string, unknown>)['id']))
    return route.fulfill({ status: 201, json: screenJson({ sales: [sale(ids[0] ?? 's1', '10.00')], taken: { cash: '0.00', card: '10.00' } }) })
  })
  await page.evaluate(() => window.dispatchEvent(new Event('online')))
  await expect(page.getByText('en attente d’envoi')).toHaveCount(0)
  await expect(page.getByText('En ligne')).toBeVisible()
  expect(new Set(ids).size).toBe(1)
})

test('the entrance screen meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await openEntrance(page, screenJson({ sales: [sale('s1', '10.00'), sale('s2', '10.00', true)] }))
  await page.getByRole('button', { name: 'Ajouter : Jeton jaune' }).click()
  await auditBothThemes(page)
})
