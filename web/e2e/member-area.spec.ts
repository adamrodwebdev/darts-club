import { test, expect, type Page } from '@playwright/test'

const profile = {
  id: '01a1b2c3-0000-7000-8000-000000000001',
  email: 'alice@example.com',
  displayName: 'Alice',
  phone: null,
  avatarUrl: null,
  roles: ['ROLE_MEMBER'],
  hasPassword: true,
  twoFactorEnabled: false,
  twoFactorSetupRequired: false,
}

/** API simulée : le front est testé sans backend. */
async function mockApi(page: Page): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 2 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/conversations', (route) =>
    route.fulfill({ json: { items: [], mailboxes: [{ id: 'president', label: 'Présidence' }] } }),
  )
}

test('protected pages redirect to the login form', async ({ page }) => {
  await page.goto('/messages')
  await expect(page).toHaveURL(/\/connexion\?redirect=\/messages$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Connexion')
})

test('member logs in and lands on the requested page', async ({ page }) => {
  await mockApi(page)
  await page.goto('/messages')

  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(page).toHaveURL(/\/messages$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Messages')
  await expect(page.getByRole('link', { name: /Notifications, 2 non lues/ })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Mon compte' })).toBeVisible()
})

test('registration shows a neutral confirmation', async ({ page }) => {
  await page.route('**/api/auth/register', (route) => route.fulfill({ status: 202, json: { message: 'ok' } }))
  await page.goto('/inscription')

  await page.getByLabel('Email').fill('new@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Créer mon compte' }).click()

  await expect(page.getByRole('status')).toContainText('Vérifie ta boîte mail')
})

test('two-factor login asks for a code after the password', async ({ page }) => {
  await page.route('**/api/auth/login', (route) => route.fulfill({ status: 202, json: { twoFactorRequired: true } }))
  await page.route('**/api/auth/2fa/check', (route) =>
    route.fulfill({ json: { ...profile, twoFactorEnabled: true } }),
  )
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.goto('/connexion')

  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await page.getByLabel('Code de vérification').fill('123456')
  await page.getByRole('button', { name: 'Vérifier' }).click()
  await expect(page).toHaveURL(/\/compte$/)
})

test('treasurer records a payment from the treasury screen', async ({ page }) => {
  const treasurer = { ...profile, roles: ['ROLE_TREASURER', 'ROLE_MEMBER'], twoFactorEnabled: true, badges: [{ id: 'tresorier', label: 'Trésorier' }] }
  const membership = {
    id: 'm1', season: '2026-2027', holder: { id: 'u2', displayName: 'Bob', email: 'bob@example.com' },
    amountDue: '30.00', paid: '0.00', remaining: '30.00', status: { id: 'impayee', label: 'Non payée' },
    overdue: true, dueDate: '2026-10-01', payments: [],
  }
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: treasurer }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/treasury/dashboard*', (route) =>
    route.fulfill({ json: { season: '2026-2027', dueDate: '2026-10-01', members: 1, byStatus: { payee: 0, partielle: 0, impayee: 1 }, overdue: 1, expected: '30.00', collected: '0.00', remaining: '30.00', byMethod: {} } }),
  )
  await page.route('**/api/treasury/memberships?*', (route) =>
    route.fulfill({ json: { season: '2026-2027', items: [membership], methods: [{ id: 'especes', label: 'Espèces' }] } }),
  )
  let recorded: unknown = null
  await page.route('**/api/treasury/memberships/m1/payments', async (route) => {
    recorded = route.request().postDataJSON()
    await route.fulfill({ status: 201, json: { ...membership, paid: '30.00', remaining: '0.00', status: { id: 'payee', label: 'Payée' }, overdue: false } })
  })

  await page.goto('/connexion?redirect=/tresorerie')
  await page.getByLabel('Email').fill('treasurer@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Trésorerie')
  await page.getByRole('button', { name: 'Encaisser' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByRole('status')).toContainText('Paiement enregistré pour Bob')
  expect(recorded).toMatchObject({ amount: '30.00', method: 'especes' })
})
