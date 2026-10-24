import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e1'

const profile = (roles: string[]) => ({
  id: '01a1b2c3-0000-7000-8000-000000000005',
  email: 'board@example.com',
  displayName: 'Nina',
  phone: null,
  avatarUrl: null,
  roles: [...roles, 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: true,
  twoFactorSetupRequired: false,
})

const item = (id: string, label: string, q: number[], prices: string[], amounts: string[]) => ({
  id, position: 1, label, planned: q[0], bought: q[1], returned: q[2], sold: q[3], left: q[1]! - q[2]! - q[3]!,
  purchasePrice: prices[0], salePrice: prices[1], cost: amounts[0], income: amounts[1],
})

function accountsJson(overrides: Record<string, unknown> = {}) {
  return {
    event: { id: EVENT, title: 'Soirée fléchettes', startsAt: '2026-11-14T19:00:00+00:00' },
    closed: false, closedAt: null, closedBy: null,
    flags: { temporaryBar: true, taxExempt: true },
    items: [
      item('i1', 'Bière 33 cl', [120, 144, 24, 112], ['0.95', '3.00'], ['114.00', '336.00']),
      item('i2', 'Hot-dog', [80, 80, 0, 71], ['0.90', '3.00'], ['72.00', '213.00']),
    ],
    entries: [
      { id: 'n1', position: 1, kind: 'depense', label: 'Location de la salle', planned: '80.00', actual: '80.00' },
      { id: 'n2', position: 2, kind: 'recette', label: 'Participation aux frais', planned: '100.00', actual: '96.00' },
    ],
    receipts: [{ id: 'r1', label: 'Facture brasserie', amount: '136.80', credit: false, type: 'pdf', uploadedBy: 'Nina', uploadedAt: '2026-11-15T10:00:00+00:00' }],
    totals: { barCosts: '186.00', barIncome: '549.00', costs: '266.00', income: '645.00', result: '379.00', plannedCosts: '266.00', plannedIncome: '700.00', plannedResult: '434.00' },
    counters: { year: 2026, temporaryBars: { used: 2, limit: 5 }, taxExempt: { used: 2, limit: 6 } },
    permissions: { edit: true, reopen: false },
    ...overrides,
  }
}

async function openAccounts(page: Page, roles: string[], accounts: object): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile(roles) }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/accounts`, (route) => route.fulfill({ json: accounts }))
  await page.goto(`/connexion?redirect=/evenements/${EVENT}/bilan`)
  await page.getByLabel('Email').fill('board@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bilan de la soirée')
}

test('the secretary sees the totals and adds a bar item', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  const summary = page.getByRole('region', { name: 'Résumé' })
  await expect(summary).toContainText('266,00')
  await expect(summary).toContainText('+379,00')
  await expect(page.getByText('Bière 33 cl').filter({ visible: true }).first()).toBeVisible()

  let posted: unknown = null
  await page.route(`**/api/events/${EVENT}/accounts/items`, (route) => {
    posted = route.request().postDataJSON()
    return route.fulfill({ status: 201, json: accountsJson({ items: [...accountsJson().items, item('i3', 'Chips', [40, 40, 0, 33], ['0.45', '1.00'], ['18.00', '33.00'])] }) })
  })
  await page.getByRole('button', { name: '+ Ajouter un article' }).click()
  const dialog = page.getByRole('dialog', { name: 'Nouvel article' })
  await dialog.getByLabel('Nom').fill('Chips')
  await dialog.getByLabel('Quantité prévue').fill('40')
  await dialog.getByLabel('Achetée').fill('40')
  await dialog.getByLabel('Vendue').fill('33')
  await dialog.getByLabel('Prix d’achat (€)').fill('0,45')
  await dialog.getByLabel('Prix de vente (€)').fill('1')
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(dialog).toHaveCount(0)
  expect(posted).toEqual({ label: 'Chips', planned: 40, bought: 40, returned: 0, sold: 33, purchasePrice: '0,45', salePrice: '1' })
  await expect(page.getByText('Chips').filter({ visible: true }).first()).toBeVisible()
})

test('the presidency reads the accounts without editing them', async ({ page }) => {
  await openAccounts(page, ['ROLE_PRESIDENT'], accountsJson({ permissions: { edit: false, reopen: false } }))
  await expect(page.getByText('Lecture seule : la secrétaire et le trésorier tiennent le bilan.')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Clôturer le bilan' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Exporter (CSV)' })).toHaveAttribute('href', `/api/events/${EVENT}/accounts/export.csv`)
})

test('the treasurer reopens closed accounts', async ({ page }) => {
  const closed = accountsJson({ closed: true, closedAt: '2026-11-16T10:00:00+00:00', closedBy: 'Nina', permissions: { edit: true, reopen: true } })
  await openAccounts(page, ['ROLE_TREASURER'], closed)
  await expect(page.getByText('Clôturé le 16 novembre 2026 par Nina')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toHaveCount(0)

  await page.route(`**/api/events/${EVENT}/accounts/reopen`, (route) => route.fulfill({ json: accountsJson({ permissions: { edit: true, reopen: true } }) }))
  await page.getByRole('button', { name: 'Rouvrir le bilan' }).click()
  await expect(page.getByText('En cours')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toBeVisible()
})

test('bar items are a table on wide screens and cards on phones', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 })
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  await expect(page.getByRole('rowheader', { name: 'Bière 33 cl' })).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.getByRole('heading', { name: 'Bière 33 cl', level: 3 })).toBeVisible()
  await expect(page.getByRole('table')).toBeHidden()
})

test('the accounts page meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  await expect(page.getByRole('region', { name: 'Résumé' })).toBeVisible()
  await auditBothThemes(page)
})
