import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e1'
const JULIE = '01a1b2c3-0000-7000-8000-0000000000f1'
const ASSIGNMENT = '01a1b2c3-0000-7000-8000-0000000000f2'

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

/** Article de la carte comme l'API le renvoie : chiffres de la maquette (2 fûts de 30 L) */
const beer = {
  id: 'i1', position: 1, label: 'Bière pression', station: 'bar', unit: 'litre', garnishes: [], alcoholic: true,
  container: { name: 'Fût', size: 3000 }, planned: 2, bought: 2, returned: 0, purchasePrice: '95.00',
  formats: [{ name: 'Verre', volume: 25, price: '2.00', sold: 124 }, { name: 'Pichet', volume: 150, price: '10.00', sold: 9 }],
  stock: 6000, served: 4450, soldCount: 133, left: 1550, cost: '190.00', income: '338.00',
}
const hotDog = {
  id: 'i2', position: 2, label: 'Hot dog', station: 'cuisine', unit: 'portion', garnishes: ['Ketchup', 'Mayonnaise'], alcoholic: false,
  container: { name: null, size: 1 }, planned: 120, bought: 120, returned: 0, purchasePrice: '0.90',
  formats: [{ name: 'Portion', volume: 1, price: '3.00', sold: 61 }],
  stock: 120, served: 61, soldCount: 61, left: 59, cost: '108.00', income: '183.00',
}
const julie = { assignmentId: ASSIGNMENT, assignedAt: '2026-11-10T10:00:00+00:00', id: JULIE, displayName: 'Julie', avatarUrl: null, badges: [] }

function accountsJson(overrides: Record<string, unknown> = {}) {
  return {
    event: { id: EVENT, title: 'Soirée fléchettes', startsAt: '2026-11-14T19:00:00+00:00' },
    closed: false, closedAt: null, closedBy: null,
    flags: { temporaryBar: true, taxExempt: true },
    items: [beer, hotDog],
    cash: {
      tokensEnabled: true,
      tokens: [{ name: 'Jeton vert', value: '1.00', bag: false }, { name: 'Jeton jaune', value: '0.50', bag: false }, { name: 'Sachet', value: '10.00', bag: true }],
      cashFloat: '100.00',
    },
    teams: { accueil: [], bar: [], cuisine: [] },
    entries: [
      { id: 'n1', position: 1, kind: 'depense', label: 'Location de la salle', planned: '80.00', actual: '80.00' },
      { id: 'n2', position: 2, kind: 'recette', label: 'Participation aux frais', planned: '100.00', actual: '96.00' },
    ],
    receipts: [{ id: 'r1', label: 'Facture brasserie', amount: '136.80', credit: false, type: 'pdf', uploadedBy: 'Nina', uploadedAt: '2026-11-15T10:00:00+00:00' }],
    totals: { barCosts: '298.00', barIncome: '521.00', costs: '378.00', income: '617.00', result: '239.00', plannedCosts: '378.00', plannedIncome: '700.00', plannedResult: '322.00' },
    counters: { year: 2026, temporaryBars: { used: 2, limit: 5 }, taxExempt: { used: 2, limit: 6 } },
    permissions: { edit: true, editMenu: true, reopen: false },
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

test('the menu shows the stock, what was sold and what is left', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  const menu = page.getByRole('region', { name: 'Carte de la soirée' })
  const draught = menu.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Bière pression' }) })
  await expect(draught).toContainText('60 L')
  await expect(draught).toContainText('2 × Fût de 30 L')
  await expect(draught).toContainText('Verre 25 cl · 2,00 €')
  await expect(draught).toContainText('44,5 L')
  await expect(draught).toContainText('15,5 L')
  await expect(draught).toContainText('≈ 62 × Verre')
  const kitchen = menu.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Hot dog' }) })
  await expect(kitchen).toContainText('Cuisine')
  await expect(kitchen).toContainText('Garnitures : Ketchup, Mayonnaise')
  await expect(kitchen).toContainText('120 portions')
})

test('the secretary reads the totals in costs and income', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  await page.getByRole('link', { name: 'Dépenses et recettes' }).click()
  await expect(page).toHaveURL(/partie=comptes/)
  const summary = page.getByRole('region', { name: 'Résumé' })
  await expect(summary).toContainText('378,00')
  await expect(summary).toContainText('+239,00')
  await expect(page.getByRole('region', { name: 'Carte de la soirée' })).toBeHidden()
})

test('a board member adds draught beer sold by the glass and the pitcher', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson({ items: [] }))

  let posted: unknown = null
  await page.route(`**/api/events/${EVENT}/accounts/items`, (route) => {
    posted = route.request().postDataJSON()
    return route.fulfill({ status: 201, json: accountsJson() })
  })
  await page.getByRole('button', { name: '+ Ajouter un article' }).click()
  const dialog = page.getByRole('dialog', { name: 'Nouvel article' })
  await dialog.getByLabel('Nom').first().fill('Bière pression')
  await dialog.getByRole('radio', { name: 'Au litre' }).check()
  const purchase = dialog.getByRole('group', { name: 'Achat' })
  await purchase.getByLabel('Contenant').fill('Fût')
  await purchase.getByLabel('Contenance').fill('30 L')
  await purchase.getByLabel('Prix d’achat (€)').fill('95')
  await purchase.getByLabel('Prévu').fill('2')
  await purchase.getByLabel('Acheté').fill('2')
  await expect(purchase).toContainText('Stock de la soirée : 60 L · coût 190,00 €')

  const glass = dialog.getByRole('group', { name: 'Format 1' })
  await glass.getByLabel('Nom').fill('Verre')
  await glass.getByLabel('Contenance').fill('25 cl')
  await glass.getByLabel('Prix (€)').fill('2')
  await glass.getByLabel('Vendus').fill('124')
  await dialog.getByRole('button', { name: '+ Ajouter un format' }).click()
  const pitcher = dialog.getByRole('group', { name: 'Format 2' })
  await pitcher.getByLabel('Nom').fill('Pichet')
  await pitcher.getByLabel('Contenance').fill('1,5 L')
  await pitcher.getByLabel('Prix (€)').fill('10')
  await pitcher.getByLabel('Vendus').fill('9')
  await dialog.getByRole('checkbox', { name: /Boisson alcoolisée/ }).check()
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(dialog).toHaveCount(0)
  expect(posted).toEqual({
    label: 'Bière pression', station: 'bar', unit: 'litre', garnishes: [], alcoholic: true, containerName: 'Fût', containerSize: 3000,
    planned: 2, bought: 2, returned: 0, purchasePrice: '95',
    formats: [{ name: 'Verre', volume: 25, price: '2', sold: 124 }, { name: 'Pichet', volume: 150, price: '10', sold: 9 }],
  })
  await expect(page.getByRole('heading', { name: 'Bière pression', level: 3 })).toBeVisible()
})

test('an unreadable size is caught before sending', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson())
  let calls = 0
  await page.route(`**/api/event-accounts/items/*`, (route) => {
    calls++
    return route.fulfill({ json: accountsJson() })
  })
  await page.getByRole('button', { name: 'Modifier Bière pression' }).click()
  const dialog = page.getByRole('dialog', { name: 'Modifier : Bière pression' })
  await expect(dialog.getByRole('group', { name: 'Achat' }).getByLabel('Contenance')).toHaveValue('30 L')
  await dialog.getByRole('group', { name: 'Format 2' }).getByLabel('Contenance').fill('un grand')
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(dialog.getByText('Contenance illisible : par exemple 25 cl ou 1,5 L.')).toBeVisible()
  expect(calls).toBe(0)
})

test('the board adds a token sold at the entrance', async ({ page }) => {
  await openAccounts(page, ['ROLE_PRESIDENT'], accountsJson())
  let saved: unknown = null
  await page.route(`**/api/events/${EVENT}/accounts/cash`, (route) => {
    saved = route.request().postDataJSON()
    return route.fulfill({ json: accountsJson() })
  })
  await page.getByRole('button', { name: '+ Ajouter un jeton ou un sachet' }).click()
  const dialog = page.getByRole('dialog', { name: 'Nouveau jeton ou sachet' })
  await dialog.getByLabel('Nom (ex. Jeton vert)').fill('Jeton rouge')
  await dialog.getByLabel('Valeur (€)').fill('2')
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(dialog).toHaveCount(0)
  expect(saved).toEqual({
    tokensEnabled: true,
    tokens: [{ name: 'Jeton vert', value: '1.00', bag: false }, { name: 'Jeton jaune', value: '0.50', bag: false }, { name: 'Sachet', value: '10.00', bag: true }, { name: 'Jeton rouge', value: '2', bag: false }],
    cashFloat: '100.00',
  })
})

test('the board staffs the bar team and frees a member', async ({ page }) => {
  await openAccounts(page, ['ROLE_PRESIDENT'], accountsJson())
  await page.route('**/api/members?q=*', (route) => route.fulfill({ json: { items: [{ id: JULIE, displayName: 'Julie', avatarUrl: null, badges: [] }] } }))
  let assigned: unknown = null
  await page.route(`**/api/events/${EVENT}/teams`, (route) => {
    assigned = route.request().postDataJSON()
    return route.fulfill({ status: 201, json: accountsJson({ teams: { accueil: [], bar: [julie], cuisine: [] } }) })
  })
  await page.getByRole('button', { name: 'Ajouter un membre à l’équipe Bar' }).click()
  const dialog = page.getByRole('dialog', { name: 'Équipe Bar' })
  await dialog.getByLabel('Rechercher un adhérent').fill('Ju')
  await dialog.getByRole('button', { name: 'Julie' }).click()
  await expect(dialog).toHaveCount(0)
  expect(assigned).toEqual({ userId: JULIE, team: 'bar' })

  let removed = false
  await page.route(`**/api/evening-team-members/${ASSIGNMENT}`, (route) => {
    removed = route.request().method() === 'DELETE'
    return route.fulfill({ json: accountsJson() })
  })
  await page.getByRole('button', { name: 'Retirer Julie de l’équipe Bar' }).click()
  await expect(page.getByRole('button', { name: 'Retirer Julie de l’équipe Bar' })).toHaveCount(0)
  expect(removed).toBe(true)
})

test('the presidency prepares the menu but leaves the accounts to the secretary and the treasurer', async ({ page }) => {
  await openAccounts(page, ['ROLE_PRESIDENT'], accountsJson({ permissions: { edit: false, editMenu: true, reopen: false } }))
  await expect(page.getByText('Vous préparez la carte, les jetons et les équipes ; la secrétaire et le trésorier tiennent les comptes.')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Clôturer le bilan' })).toHaveCount(0)
  await page.getByRole('link', { name: 'Dépenses et recettes' }).click()
  await expect(page.getByRole('button', { name: '+ Ajouter une dépense' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Exporter (CSV)' })).toHaveAttribute('href', `/api/events/${EVENT}/accounts/export.csv`)
})

test('the treasurer reopens closed accounts', async ({ page }) => {
  const closed = accountsJson({ closed: true, closedAt: '2026-11-16T10:00:00+00:00', closedBy: 'Nina', permissions: { edit: true, editMenu: true, reopen: true } })
  await openAccounts(page, ['ROLE_TREASURER'], closed)
  await expect(page.getByText('Clôturé le 16 novembre 2026 par Nina')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toHaveCount(0)

  await page.route(`**/api/events/${EVENT}/accounts/reopen`, (route) => route.fulfill({ json: accountsJson({ permissions: { edit: true, editMenu: true, reopen: true } }) }))
  await page.getByRole('button', { name: 'Rouvrir le bilan' }).click()
  await expect(page.getByText('En cours')).toBeVisible()
  await expect(page.getByRole('button', { name: '+ Ajouter un article' })).toBeVisible()
})

test('each part of the accounts meets WCAG 2.2 AA in both themes', async ({ page }) => {
  await openAccounts(page, ['ROLE_SECRETARY'], accountsJson({ teams: { accueil: [], bar: [julie], cuisine: [] } }))
  await expect(page.getByRole('region', { name: 'Carte de la soirée' })).toBeVisible()
  await auditBothThemes(page)
  for (const part of ['Dépenses et recettes', 'Justificatifs']) {
    await page.getByRole('link', { name: part }).click()
    await expect(page.getByRole('link', { name: part })).toHaveAttribute('aria-current', 'page')
    await auditBothThemes(page)
  }
})
