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
