import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e4'

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000009',
  email: 'tresorier@example.com',
  displayName: 'Paul',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_TREASURER', 'ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: true,
  twoFactorSetupRequired: false,
}

const event = { id: EVENT, title: 'Soirée fléchettes', startsAt: '2026-11-21T19:00:00+00:00' }

/** Bilan minimal : la page s'ouvre sur la carte, les recettes se lisent à part */
function accountsJson(totals: Record<string, string> = {}) {
  return {
    event, closed: false, closedAt: null, closedBy: null,
    flags: { temporaryBar: true, taxExempt: true },
    items: [],
    cash: { tokensEnabled: true, tokens: [{ name: 'Jeton vert', value: '1.00', bag: false }], cashFloat: '100.00', frozen: true },
    teams: { accueil: [], bar: [], cuisine: [] },
    entries: [], receipts: [],
    totals: { barCosts: '298.00', barIncome: '0.00', barIncomeSource: 'menu', barSales: '0.00', costs: '298.00', income: '0.00', result: '-298.00', plannedCosts: '298.00', plannedIncome: '700.00', plannedResult: '402.00', ...totals },
    counters: { year: 2026, temporaryBars: { used: 2, limit: 5 }, taxExempt: { used: 2, limit: 6 } },
    permissions: { edit: true, editMenu: true, reopen: true },
  }
}

const item = (id: string, label: string, station: string, unit: string, formats: object[], figures: Record<string, unknown>) => ({ id, label, station, unit, formats, ...figures })

/** Chiffres de la maquette : 186 commandes, 810 € à l'accueil, 656 € dépensés en jetons */
function incomeJson(overrides: Record<string, unknown> = {}) {
  return {
    event,
    tokensEnabled: true,
    entrance: {
      cash: '312.00', card: '498.00', total: '810.00', cashFloat: '100.00', expectedCash: '412.00',
      tokens: [
        { name: 'Sachet', value: '10.00', bag: true, color: null, count: 42, amount: '420.00' },
        { name: 'Jeton vert', value: '1.00', bag: false, color: 'vert', count: 312, amount: '312.00' },
        { name: 'Jeton jaune', value: '0.50', bag: false, color: 'jaune', count: 156, amount: '78.00' },
      ],
    },
    bar: { amount: '401.00', served: 185 },
    kitchen: { amount: '255.00', served: 97 },
    spent: '656.00',
    circulation: '154.00',
    orders: { count: 186, waiting: 4, ready: 2, cancelled: 3 },
    items: [
      item('i1', 'Bière pression', 'bar', 'litre', [{ name: 'Verre', volume: 25, count: 124, amount: '248.00' }, { name: 'Pichet', volume: 150, count: 9, amount: '90.00' }], { amount: '338.00', served: 4450, stock: 6000, left: 1550 }),
      item('i2', 'Eau', 'bar', 'unite', [{ name: 'Bouteille', volume: 1, count: 30, amount: '30.00' }], { amount: '30.00', served: 30, stock: 48, left: 18 }),
      item('i3', 'Soda', 'bar', 'unite', [{ name: 'Canette', volume: 1, count: 22, amount: '33.00' }], { amount: '33.00', served: 22, stock: 72, left: 50 }),
      item('i4', 'Hot dog', 'cuisine', 'portion', [{ name: 'Portion', volume: 1, count: 61, amount: '183.00' }], { amount: '183.00', served: 61, stock: 120, left: 59 }),
      item('i5', 'Frites', 'cuisine', 'portion', [{ name: 'Portion', volume: 1, count: 36, amount: '72.00' }], { amount: '72.00', served: 36, stock: 80, left: 44 }),
    ],
    ...overrides,
  }
}

async function openAccounts(page: Page, accounts: object): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization*', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/accounts`, (route) => route.fulfill({ json: accounts }))
  await page.goto(`/connexion?redirect=/evenements/${EVENT}/bilan`)
  await page.getByLabel('Email').fill('tresorier@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bilan de la soirée')
}

async function openIncome(page: Page, income: object): Promise<void> {
  await page.route(`**/api/events/${EVENT}/accounts/income`, (route) => route.fulfill({ json: income }))
  await openAccounts(page, accountsJson())
  await page.getByRole('link', { name: 'Recettes en direct' }).click()
  await expect(page).toHaveURL(/partie=recettes/)
  await expect(page.getByText(/En direct · mis à jour à \d\d:\d\d/)).toBeVisible()
}

test('the board reads the money taken, the tokens spent and those still in circulation', async ({ page }) => {
  await openIncome(page, incomeJson())

  const entrance = page.getByRole('region', { name: 'Accueil · encaissé' })
  await expect(entrance).toContainText('810,00 €')
  await expect(entrance).toContainText('Espèces 312,00 €')
  await expect(entrance).toContainText('Carte 498,00 €')
  await expect(entrance).toContainText('42 × Sachet à 10,00 € · 312 × Jeton vert à 1,00 € · 156 × Jeton jaune à 0,50 €')
  await expect(entrance).toContainText('Attendu en caisse : 412,00 € (fond 100,00 € + espèces)')
  const bar = page.getByRole('region', { name: 'Bar · boissons' })
  await expect(bar).toContainText('401,00 €')
  await expect(bar).toContainText('payés en jetons')
  await expect(bar).toContainText('124 × Verre · 9 × Pichet · 30 × Eau · 22 × Soda')
  await expect(page.getByRole('region', { name: 'Cuisine · plats' })).toContainText('61 × Hot dog · 36 × Frites · 4 en préparation')
  await expect(page.getByRole('region', { name: 'Jetons en circulation' })).toContainText('810,00 € vendus à l’accueil − 656,00 € dépensés au bar et en cuisine.')

  const perItem = page.getByRole('region', { name: 'Ventes par article' })
  await expect(perItem).toContainText('186 commandes · 4 en cuisine · 2 prêtes · 3 annulées')
  const beer = perItem.getByRole('article', { name: 'Bière pression' })
  await expect(beer).toContainText('124 × Verre + 9 × Pichet = 44,5 L')
  await expect(beer).toContainText('338,00 €')
  await expect(beer).toContainText('15,5 L · ≈ 62 × Verre')
  await expect(perItem.getByRole('article', { name: 'Hot dog' })).toContainText('59 portions')
  await expect(perItem).toContainText('Total dépensé en jetons 656,00 €')
  await expect(page.getByRole('link', { name: 'Exporter les recettes (CSV)' })).toHaveAttribute('href', `/api/events/${EVENT}/accounts/income.csv`)
})

test('the figures follow the evening without reloading the page', async ({ page }) => {
  await page.clock.install()
  await openIncome(page, incomeJson())
  await expect(page.getByRole('region', { name: 'Accueil · encaissé' })).toContainText('810,00 €')

  await page.route(`**/api/events/${EVENT}/accounts/income`, (route) =>
    route.fulfill({ json: incomeJson({ entrance: { ...incomeJson().entrance, cash: '322.00', total: '820.00', expectedCash: '422.00' }, circulation: '164.00' }) }),
  )
  await page.clock.fastForward('00:16')
  await expect(page.getByRole('region', { name: 'Accueil · encaissé' })).toContainText('820,00 €')
  await expect(page.getByRole('region', { name: 'Jetons en circulation' })).toContainText('164,00 €')
})

test('an evening without tokens counts what the bar and the kitchen sold', async ({ page }) => {
  await openIncome(page, incomeJson({ tokensEnabled: false }))
  await expect(page.getByRole('region', { name: 'Accueil · encaissé' })).toHaveCount(0)
  await expect(page.getByRole('region', { name: 'Jetons en circulation' })).toHaveCount(0)
  await expect(page.getByRole('region', { name: 'Bar · boissons' })).toContainText('vendus')
  await expect(page.getByText('Pas de jetons ce soir')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Ventes par article' })).toContainText('Total vendu 656,00 €')
})

test('the accounts count the money taken at the entrance once tokens are sold', async ({ page }) => {
  await openAccounts(page, accountsJson({ barIncome: '810.00', barIncomeSource: 'entrance', barSales: '656.00', income: '810.00', result: '512.00' }))
  await page.getByRole('link', { name: 'Dépenses et recettes' }).click()
  const summary = page.getByRole('region', { name: 'Résumé' })
  await expect(summary).toContainText('dont 810,00 € encaissés à l’accueil')
  await expect(summary).toContainText('+512,00')
})

test('the live income meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await openIncome(page, incomeJson())
  await expect(page.getByRole('article', { name: 'Frites' })).toBeVisible()
  await auditBothThemes(page)
})

for (const width of [320, 390, 768, 1280]) {
  test(`the live income fits ${width}px without scrolling sideways`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 })
    await openIncome(page, incomeJson())
    await expect(page.getByRole('article', { name: 'Frites' })).toBeVisible()
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(0)
  })
}
