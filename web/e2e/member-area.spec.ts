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

test('president broadcasts a labelled message to all members', async ({ page }) => {
  const president = { ...profile, roles: ['ROLE_PRESIDENT', 'ROLE_MEMBER'], twoFactorEnabled: true }
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: president }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/admin/members*', (route) => route.fulfill({ json: { season: '2026-2027', items: [] } }))
  await page.route('**/api/admin/broadcasts', async (route) => {
    if (route.request().method() === 'GET') return route.fulfill({ json: { items: [], labels: [] } })
    const draft = route.request().postDataJSON() as { label: string; title: string; body: string; userIds?: string[] }
    expect(draft.userIds).toBeUndefined()
    return route.fulfill({
      status: 201,
      json: { id: 'b1', label: draft.label, labelText: 'Annulation', title: draft.title, body: draft.body, author: 'Alice', toAllMembers: true, recipientCount: 42, sentAt: '2026-10-15T10:00:00+00:00' },
    })
  })

  await page.goto('/connexion?redirect=/admin/diffusion')
  await page.getByLabel('Email').fill('president@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await page.getByText('Annulation', { exact: true }).click()
  await page.getByLabel('Titre').fill('Tournoi annulé')
  await page.getByLabel('Message').fill('Le tournoi de samedi est reporté.')
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page.getByRole('status')).toContainText('envoyé à 42 adhérent(s)')
})

test('an organizer publishes an event with its poster', async ({ page }) => {
  const secretary = { ...profile, roles: ['ROLE_SECRETARY', 'ROLE_MEMBER'], twoFactorEnabled: true }
  const id = '01a1b2c3-0000-7000-8000-0000000000e1'
  const poster = { cardUrl: `/api/events/${id}/poster/${'c'.repeat(32)}`, largeUrl: `/api/events/${id}/poster/${'b'.repeat(32)}`, width: 600, height: 848 }
  const summary = {
    id, title: 'Soirée cricket', location: 'Bar du club', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00',
    season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 0, isRegistered: false,
    full: false, started: false, past: false, hasSummary: false, poster: null,
  }
  // PNG 1×1 : l'API simulée renvoie la même image pour l'aperçu et l'affiche
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')
  const uploads: string[] = []

  await page.route('**/api/auth/login', (route) => route.fulfill({ json: secretary }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/events', (route) => route.fulfill({ status: 201, json: summary }))
  await page.route(`**/api/events/${id}/poster`, (route) => {
    uploads.push(route.request().headers()['content-type'] ?? '')
    return route.fulfill({ status: 201, json: poster })
  })
  await page.route(`**/api/events/${id}/poster/*`, (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route(`**/api/events/${id}`, (route) =>
    route.fulfill({
      json: { ...summary, poster, description: '', organizer: 'Alice', summary: null, canManage: true, photos: [], posts: [], participants: [] },
    }),
  )

  await page.goto('/evenements/nouveau')
  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Nouvel événement')

  await page.getByLabel('Titre').fill('Soirée cricket')
  await page.getByLabel('Lieu').fill('Bar du club')
  await page.getByLabel('Début').fill('2026-11-14T20:00')
  await page.getByLabel('Fin').fill('2026-11-15T00:00')
  await page.getByRole('group', { name: 'Affiche (facultatif)' }).locator('input[type=file]').setInputFiles({ name: 'affiche.png', mimeType: 'image/png', buffer: png })
  await expect(page.getByRole('img', { name: 'Aperçu de l’affiche' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Retirer l’affiche' })).toBeVisible()

  await page.getByRole('button', { name: 'Publier l’événement' }).click()
  await expect(page).toHaveURL(new RegExp(`/evenements/${id}$`))
  expect(uploads).toHaveLength(1)
  expect(uploads[0]).toMatch(/^multipart\/form-data/)
  await expect(page.getByRole('img', { name: 'Affiche de l’événement « Soirée cricket »' })).toBeVisible()
})

test('only the administrator sees the announcement audience', async ({ page }) => {
  const admin = { ...profile, roles: ['ROLE_ADMIN', 'ROLE_MEMBER'], twoFactorEnabled: true }
  const id = '01a1b2c3-0000-7000-8000-0000000000e2'
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: admin }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${id}/view`, (route) => route.fulfill({ status: 204, body: '' }))
  await page.route(`**/api/events/${id}`, (route) =>
    route.fulfill({
      json: {
        id, title: 'Soirée cricket', location: 'Bar du Centre', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00',
        season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 5, maybe: 2, isRegistered: false, myAnswer: null,
        full: false, started: false, past: false, hasSummary: false, poster: null, public: true, city: 'Louhans', publicPath: '/e/soiree-cricket-a1b2c3',
        description: '', organizer: 'Alice', summary: null, canManage: true, photos: [], posts: [], participants: [], guests: [],
      },
    }),
  )
  await page.route(`**/api/admin/events/${id}/audience`, (route) =>
    route.fulfill({
      json: {
        unique: 28, members: 12, visitors: 16, going: 5, maybe: 2, archived: false, closed: false,
        days: [{ day: '2026-10-12', members: 8, visitors: 10 }, { day: '2026-10-13', members: 4, visitors: 6 }],
      },
    }),
  )

  await page.goto(`/evenements/${id}`)
  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  const panel = page.getByRole('region', { name: 'Audience de l’annonce' })
  await expect(panel.getByText('Personnes uniques')).toBeVisible()
  await expect(panel.getByText('28', { exact: true })).toBeVisible()
  await expect(panel.getByText('≈ 16')).toBeVisible()
  await expect(panel.getByText('25 %')).toBeVisible()
  await expect(panel.getByRole('table', { name: 'Nouvelles personnes par jour' }).getByRole('row')).toHaveCount(3)
  await expect(panel.getByRole('link', { name: 'Exporter (CSV)' })).toHaveAttribute('href', `/api/admin/events/${id}/audience.csv`)
})

test('an organizer removes a fake guest after confirming', async ({ page }) => {
  const organizer = { ...profile, roles: ['ROLE_SECRETARY', 'ROLE_MEMBER'], twoFactorEnabled: true }
  const id = '01a1b2c3-0000-7000-8000-0000000000e4'
  const guestId = '01a1b2c3-0000-7000-8000-0000000000f1'
  let guests = [{ id: guestId, name: 'Robot', email: 'bot@example.net', answer: 'je-viens' }]
  const removals: string[] = []
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: organizer }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${id}/view`, (route) => route.fulfill({ status: 204, body: '' }))
  await page.route(`**/api/events/${id}/guests/${guestId}`, (route) => {
    removals.push(route.request().method())
    guests = []
    return route.fulfill({ status: 204, body: '' })
  })
  await page.route(`**/api/events/${id}`, (route) =>
    route.fulfill({
      json: {
        id, title: 'Soirée cricket', location: 'Bar du Centre', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00',
        season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 1, isRegistered: false,
        full: false, started: false, past: false, hasSummary: false, public: true, city: 'Louhans', publicPath: '/e/soiree-cricket-a1b2c3',
        description: '', organizer: 'Alice', summary: null, canManage: true, photos: [], posts: [], participants: [], guests,
      },
    }),
  )

  await page.goto(`/evenements/${id}`)
  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await page.getByText('Invités sans compte (1)').click()
  await page.getByRole('button', { name: 'Retirer' }).click()
  const dialog = page.getByRole('dialog', { name: 'Retirer Robot des invités ?' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: 'Retirer' }).click()

  await expect(dialog).toHaveCount(0)
  await expect(page.getByText('Invités sans compte')).toHaveCount(0)
  expect(removals).toEqual(['DELETE'])
})

test('the president does not get the audience panel', async ({ page }) => {
  const president = { ...profile, roles: ['ROLE_PRESIDENT', 'ROLE_MEMBER'], twoFactorEnabled: true }
  const id = '01a1b2c3-0000-7000-8000-0000000000e3'
  const audienceCalls: string[] = []
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: president }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route('**/api/admin/events/**', (route) => {
    audienceCalls.push(route.request().url())
    return route.fulfill({ status: 403, json: {} })
  })
  await page.route(`**/api/events/${id}`, (route) =>
    route.fulfill({
      json: {
        id, title: 'Soirée cricket', location: 'Bar du Centre', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00',
        season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 0, isRegistered: false,
        full: false, started: false, past: false, hasSummary: false, description: '', organizer: 'Alice', summary: null,
        canManage: true, photos: [], posts: [], participants: [], guests: [],
      },
    }),
  )

  await page.goto(`/evenements/${id}`)
  await page.getByLabel('Email').fill('alice@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Soirée cricket')
  await expect(page.getByRole('region', { name: 'Audience de l’annonce' })).toHaveCount(0)
  expect(audienceCalls).toEqual([])
})
