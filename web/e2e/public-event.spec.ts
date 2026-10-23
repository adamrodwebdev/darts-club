import { test, expect } from '@playwright/test'

const slug = 'soiree-cricket-2026-11-14-a1b2c3'
const event = {
  slug, title: 'Soirée cricket', description: 'Initiation et tournoi amical.', city: 'Louhans',
  startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00', status: 'prevu', cancelReason: null,
  capacity: 20, going: 7, maybe: 3, full: false, started: false, past: false, poster: null,
}
const token = 'A'.repeat(43)

test('a visitor without an account answers a public event', async ({ page }) => {
  const sent: unknown[] = []
  await page.route(`**/api/public/events/${slug}`, (route) => route.fulfill({ json: event }))
  await page.route(`**/api/public/events/${slug}/responses`, (route) => {
    sent.push(route.request().postDataJSON())
    return route.fulfill({ status: 202, json: { status: 'pending' } })
  })

  await page.goto(`/e/${slug}`)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Soirée cricket')
  await expect(page.getByText('Louhans')).toBeVisible()
  await expect(page.getByText('7 personnes viennent · 3 « pas sûr »')).toBeVisible()
  await expect(page).toHaveTitle('Soirée cricket · Skull Darts 71')
  const structured = await page.locator('script[type="application/ld+json"][data-i18n-head]').textContent()
  expect(JSON.parse(structured ?? '{}')).toMatchObject({ '@type': 'Event', name: 'Soirée cricket', location: { name: 'Louhans' } })

  await page.getByRole('radio', { name: 'Pas sûr' }).check()
  await page.getByLabel('Prénom ou pseudo').fill('Zoé')
  await page.getByRole('textbox', { name: 'Email' }).fill('zoe@example.net')
  const submit = page.getByRole('button', { name: 'Envoyer ma réponse' })
  await expect(submit).toBeDisabled()
  await page.getByRole('checkbox', { name: /J’accepte/ }).check()
  await submit.click()

  await expect(page.getByRole('status')).toContainText('Plus qu’une étape')
  expect(sent).toEqual([{ name: 'Zoé', email: 'zoe@example.net', answer: 'pas-sur', consent: true, website: '' }])
})

test('the personal link confirms, then cancels the answer', async ({ page }) => {
  const tokens: string[] = []
  const pending = { name: 'Zoé', answer: null, pendingAnswer: 'je-viens', confirmed: false, expired: false, event: { ...event, location: null } }
  const confirmed = { ...pending, answer: 'je-viens', pendingAnswer: null, confirmed: true, event: { ...event, going: 8, location: 'Bar du Centre, 3 rue Neuve, Louhans' } }
  await page.route('**/api/public/responses/*', (route) => {
    const body = route.request().postDataJSON() as { token: string }
    tokens.push(body.token)
    const action = new URL(route.request().url()).pathname.split('/').pop()
    if (action === 'lookup') return route.fulfill({ json: pending })
    if (action === 'confirm') return route.fulfill({ json: confirmed })
    return route.fulfill({ status: 204, body: '' })
  })

  await page.goto(`/e/${slug}/reponse#${token}`)
  await expect(page.getByText('Zoé, tu as répondu « Je viens »')).toBeVisible()
  // Jeton retiré de la barre d'adresse, jamais envoyé dans une URL
  await expect(page).toHaveURL(new RegExp(`/e/${slug}/reponse$`))

  await page.getByRole('button', { name: 'Confirmer ma réponse' }).click()
  await expect(page.getByText('C’est noté, Zoé : « Je viens ».')).toBeVisible()
  await expect(page.getByText('Adresse : Bar du Centre, 3 rue Neuve, Louhans')).toBeVisible()

  await page.getByRole('button', { name: 'Me désinscrire' }).click()
  await expect(page.getByRole('status')).toContainText('Ta réponse est retirée')
  expect(tokens).toEqual([token, token, token])
})

test('an unknown link explains what to do', async ({ page }) => {
  await page.route('**/api/public/responses/lookup', (route) => route.fulfill({ status: 404, json: { title: 'Not Found', status: 404 } }))
  await page.goto(`/e/${slug}/reponse#nope`)
  await expect(page.getByRole('alert')).toContainText('Ce lien n’est pas (ou plus) valable.')
  await expect(page.getByRole('link', { name: 'Revenir à l’événement' })).toHaveAttribute('href', `/e/${slug}`)
})

test('a view is counted after the page stays visible for 2 seconds, without cookies', async ({ page, context }) => {
  const views: string[] = []
  await page.route(`**/api/public/events/${slug}`, (route) => route.fulfill({ json: event }))
  await page.route(`**/api/public/events/${slug}/view`, (route) => {
    views.push(route.request().method())
    return route.fulfill({ status: 204, body: '' })
  })

  await page.goto(`/e/${slug}`)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Soirée cricket')
  expect(views).toEqual([])
  await expect.poll(() => views, { timeout: 5000 }).toEqual(['POST'])
  expect(await context.cookies()).toEqual([])
})

test('visitors without an account discover open events from the home page and the calendar', async ({ page }) => {
  await page.route('**/api/public/events', (route) => route.fulfill({ json: { items: [event], past: [] } }))
  await page.route(`**/api/public/events/${slug}`, (route) => route.fulfill({ json: event }))

  await page.goto('/')
  const section = page.getByRole('region', { name: 'Tous les rendez-vous' })
  await expect(section.getByRole('link', { name: /Soirée cricket/ })).toHaveAttribute('href', `/e/${slug}`)

  await page.getByRole('link', { name: 'Événements', exact: true }).click()
  await expect(page).toHaveURL(/\/evenements$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Événements')
  await page.getByRole('link', { name: /Soirée cricket/ }).click()
  await expect(page).toHaveURL(new RegExp(`/e/${slug}$`))
  await expect(page.getByRole('button', { name: 'Envoyer ma réponse' })).toBeVisible()
})

test('a member link to a public event opens its public page for someone without an account', async ({ page }) => {
  const id = '01a1b2c3-0000-7000-8000-0000000000e9'
  await page.route(`**/api/public/events/by-id/${id}`, (route) => route.fulfill({ json: { slug } }))
  await page.route(`**/api/public/events/${slug}`, (route) => route.fulfill({ json: event }))

  await page.goto(`/evenements/${id}`)
  await expect(page).toHaveURL(new RegExp(`/e/${slug}$`))
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Soirée cricket')
})

test('the home page hides the section when no event is open', async ({ page }) => {
  await page.route('**/api/public/events', (route) => route.fulfill({ json: { items: [], past: [] } }))
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Skull Darts 71')
  await expect(page.getByRole('region', { name: 'Tous les rendez-vous' })).toBeHidden()
})

test('the home carousel keeps track of every open event, the next one first', async ({ page }) => {
  const next = { ...event, slug: 'halloween-party-2026-10-31-d4e5f6', title: 'Halloween Party', startsAt: '2026-10-31T16:00:00+00:00' }
  const later = event
  const recent = { ...event, slug: 'soiree-de-rentree-2026-10-03-a7b8c9', title: 'Soirée de rentrée', startsAt: '2026-10-03T17:30:00+00:00', past: true, started: true }
  const older = { ...event, slug: 'tournoi-d-ete-2026-07-12-c1d2e3', title: 'Tournoi d’été', startsAt: '2026-07-12T13:00:00+00:00', going: 1, past: true, started: true }
  await page.route('**/api/public/events', (route) => route.fulfill({ json: { items: [next, later], past: [recent, older] } }))
  await page.route(`**/api/public/events/${slug}`, (route) => route.fulfill({ json: event }))

  await page.goto('/')
  const carousel = page.getByRole('list', { name: 'Événements ouverts à tous, du prochain au plus ancien', exact: true })
  const slides = carousel.getByRole('listitem')
  await expect(slides).toHaveCount(4)
  await expect(slides.nth(0)).toContainText('Prochain')
  await expect(slides.nth(0)).toContainText('Halloween Party')
  await expect(slides.nth(1)).toContainText('À venir')
  await expect(slides.nth(2)).toContainText('Passé')
  await expect(slides.nth(2)).toContainText('Soirée de rentrée')
  await expect(slides.nth(3)).toContainText('1 participant')

  // Les repères suivent la carte centrale ; une carte de côté vient au centre au lieu de s'ouvrir
  const frets = page.getByRole('list', { name: /Aller à une carte/ })
  await expect(frets.getByRole('button', { name: 'Halloween Party' })).toHaveAttribute('aria-current', 'true')
  await page.getByRole('region', { name: 'Tous les rendez-vous' }).getByRole('button', { name: 'Carte suivante' }).click()
  await expect(frets.getByRole('button', { name: 'Soirée cricket' })).toHaveAttribute('aria-current', 'true')
  await slides.nth(2).getByRole('link').click()
  await expect(frets.getByRole('button', { name: 'Soirée de rentrée' })).toHaveAttribute('aria-current', 'true')
  await expect(page).toHaveURL(/\/$/)

  await frets.getByRole('button', { name: 'Soirée cricket' }).click()
  await expect(frets.getByRole('button', { name: 'Soirée cricket' })).toHaveAttribute('aria-current', 'true')
  await slides.nth(1).getByRole('link').click()
  await expect(page).toHaveURL(new RegExp(`/e/${slug}$`))
})
