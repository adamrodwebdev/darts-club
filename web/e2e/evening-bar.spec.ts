import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e3'
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000008',
  email: 'marc@example.com',
  displayName: 'Marc',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}

const event = { id: EVENT, title: 'Soirée fléchettes', startsAt: '2026-11-21T19:00:00+00:00' }
const beer = {
  id: 'i1', label: 'Bière pression', station: 'bar', unit: 'litre', garnishes: [], soldOut: false, markedSoldOut: false, left: 1550,
  formats: [{ name: 'Verre', volume: 25, price: '2.00' }, { name: 'Pichet', volume: 150, price: '10.00' }],
}
const hotDog = {
  id: 'i2', label: 'Hot dog', station: 'cuisine', unit: 'portion', garnishes: [{ name: 'Ketchup', out: false }, { name: 'Moutarde', out: true }],
  soldOut: false, markedSoldOut: false, left: 59, formats: [{ name: 'Portion', volume: 1, price: '3.00' }],
}

const order = (id: string, number: number, kitchen: string | null, extra: Record<string, unknown> = {}) => ({
  id, number, note: 'Table 4', total: '3.00', kitchen, takenAt: '2026-11-21T20:00:00+00:00', takenBy: 'Marc', readyAt: null, deliveredAt: null, deliveredBy: null,
  lines: [{ itemId: 'i2', label: 'Hot dog', station: 'cuisine', format: 'Portion', price: '3.00', count: 1, garnishes: ['Ketchup'] }],
  ...extra,
})

function barJson(overrides: Record<string, unknown> = {}) {
  return {
    event, nextNumber: 7, tokensEnabled: true,
    tokens: [{ name: 'Jeton vert', value: '1.00', color: 'vert' }, { name: 'Jeton jaune', value: '0.50', color: 'jaune' }],
    menu: [beer, hotDog],
    orders: { waiting: [], ready: [], delivered: [] },
    ...overrides,
  }
}

function kitchenJson(overrides: Record<string, unknown> = {}) {
  return { event, pending: [order('o5', 5, 'en_attente')], sent: [], stock: [hotDog], ...overrides }
}

async function open(page: Page, path: string, heading: string): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization*', (route) => route.fulfill({ status: 503, json: {} }))
  await page.goto(`/connexion?redirect=/evenements/${EVENT}/${path}`)
  await page.getByLabel('Email').fill('marc@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)
}

test('the bar takes an order, tells the tokens to collect and sends the food to the kitchen', async ({ page }) => {
  await page.route(`**/api/events/${EVENT}/service/bar`, (route) => route.fulfill({ json: barJson() }))
  await open(page, 'bar', 'Bar')
  let posted: Record<string, unknown> | null = null
  await page.route(`**/api/events/${EVENT}/service/orders`, (route) => {
    posted = route.request().postDataJSON() as Record<string, unknown>
    return route.fulfill({ status: 201, json: barJson({ nextNumber: 8, orders: { waiting: [order(String(posted['id']), 7, 'en_attente')], ready: [], delivered: [] } }) })
  })

  await page.getByRole('button', { name: 'Ajouter : Bière pression · Verre 25 cl' }).click()
  await page.getByRole('button', { name: 'Ajouter : Bière pression · Verre 25 cl' }).click()
  await expect(page.getByRole('button', { name: /Moutarde · épuisée/ })).toBeDisabled()
  await page.getByRole('button', { name: 'Ketchup', exact: true }).click()
  await page.getByRole('button', { name: /^\+ Hot dog \(Ketchup\)/ }).click()
  const summary = page.getByRole('region', { name: 'Commande n° 7' })
  await expect(summary).toContainText('7,00 €')
  await expect(summary).toContainText('7 × Jeton vert')
  await summary.getByLabel('Repère du client').fill('Table 4')
  await summary.getByRole('button', { name: 'Valider la commande' }).click()

  await expect(page.getByRole('status').filter({ hasText: 'Commande 7 validée' })).toContainText('1 plat part en cuisine.')
  expect(posted).toMatchObject({ note: 'Table 4', lines: [{ itemId: 'i1', format: 'Verre', count: 2, garnishes: [] }, { itemId: 'i2', format: 'Portion', count: 1, garnishes: ['Ketchup'] }] })
  expect(String(posted?.['id'])).toMatch(UUID)
  await expect(page.getByRole('button', { name: 'En cuisine · 1' })).toBeVisible()
})

test('a ready order is handed over once, then locked', async ({ page }) => {
  const ready = order('01a1b2c3-0000-7000-8000-00000000c006', 6, 'prete', { readyAt: '2026-11-21T20:06:00+00:00' })
  await page.route(`**/api/events/${EVENT}/service/bar`, (route) => route.fulfill({ json: barJson({ orders: { waiting: [], ready: [ready], delivered: [] } }) }))
  await open(page, 'bar', 'Bar')
  await page.route(`**/api/evening-orders/${ready.id}/deliver`, (route) =>
    route.fulfill({ json: barJson({ orders: { waiting: [], ready: [], delivered: [{ ...ready, kitchen: 'remise', deliveredAt: '2026-11-21T20:09:00+00:00', deliveredBy: 'Marc' }] } }) }),
  )
  await page.getByRole('button', { name: 'Prêtes · 1' }).click()
  await page.getByRole('button', { name: 'Remise au client' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Commande 6 remise' })).toBeVisible()
  await expect(page.getByText('Verrouillée', { exact: true })).toBeVisible()
})

test('the kitchen marks an order ready and a topping sold out', async ({ page }) => {
  await page.route(`**/api/events/${EVENT}/service/kitchen`, (route) => route.fulfill({ json: kitchenJson() }))
  await open(page, 'cuisine', 'Cuisine')
  await expect(page.getByRole('article', { name: 'Commande n° 5' })).toContainText('1 × Hot dog')
  await page.route('**/api/evening-orders/o5/ready', (route) => route.fulfill({ json: kitchenJson({ pending: [], sent: [order('o5', 5, 'prete', { readyAt: '2026-11-21T20:06:00+00:00' })] }) }))
  await page.getByRole('button', { name: 'Prête', exact: true }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Commande 5 prête' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Envoyées au bar' })).toContainText('attend la remise')

  let availability: unknown = null
  await page.route('**/api/event-accounts/items/i2/availability', (route) => {
    availability = route.request().postDataJSON()
    return route.fulfill({ json: kitchenJson({ pending: [] }) })
  })
  await page.getByRole('button', { name: 'Marquer comme épuisé : Ketchup' }).click()
  await expect.poll(() => availability).toEqual({ soldOut: false, garnishesOut: ['Moutarde', 'Ketchup'] })
})

test('the bar screen meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await page.route(`**/api/events/${EVENT}/service/bar`, (route) => route.fulfill({ json: barJson({ orders: { waiting: [order('o5', 5, 'en_attente')], ready: [], delivered: [] } }) }))
  await open(page, 'bar', 'Bar')
  await page.getByRole('button', { name: 'Ajouter : Bière pression · Verre 25 cl' }).click()
  await expect(page.getByRole('region', { name: 'Commande n° 7' })).toContainText('2,00 €')
  await auditBothThemes(page)
})

test('the kitchen screen meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await page.route(`**/api/events/${EVENT}/service/kitchen`, (route) => route.fulfill({ json: kitchenJson() }))
  await open(page, 'cuisine', 'Cuisine')
  await expect(page.getByRole('article', { name: 'Commande n° 5' })).toBeVisible()
  await auditBothThemes(page)
})
