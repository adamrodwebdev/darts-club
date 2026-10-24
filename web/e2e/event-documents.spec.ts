import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

/** Identifiant de la soirée (UUID, comme dans l'adresse) */
const EVENT = '01a1b2c3-0000-7000-8000-0000000000e2'
const PROCEDURES = `**/api/events/${EVENT}/procedures`

/** Jour « AAAA-MM-JJ » à quelques jours d'aujourd'hui : délais et retards restent vrais quel que soit le jour du test */
function day(offset: number): string {
  return new Date(Date.now() + offset * 86_400_000).toISOString().slice(0, 10)
}

function frenchDay(value: string): string {
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', timeZone: 'Europe/Paris' }).format(new Date(`${value}T12:00:00Z`))
}

const event = { id: EVENT, title: 'Soirée fléchettes', startsAt: `${day(21)}T19:00:00+00:00` }

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

const summary = (type: string, status: string, deadline: string | null, dates: { sentOn?: string; receivedOn?: string } = {}) => ({
  type, status, recipient: 'Mairie de Bellevesvre', deadline, sentOn: dates.sentOn ?? null, receivedOn: dates.receivedOn ?? null, updatedAt: null, edited: false,
})

function listJson(overrides: Record<string, unknown> = {}) {
  return {
    event,
    procedures: [
      summary('buvette', 'a-faire', day(6)),
      summary('salle', 'a-faire', day(-3)),
      summary('domaine-public', 'pas-necessaire', day(-9)),
      summary('sacem', 'envoyee', day(6), { sentOn: day(-1) }),
      summary('budget', 'prete', null),
      summary('billets', 'pas-necessaire', day(14)),
      summary('assurance', 'recue', day(6), { receivedOn: day(-2) }),
      summary('fermeture-tardive', 'pas-necessaire', day(-9)),
    ],
    profile: { missing: ['insurer'], certificateValidUntil: null, certificateCoversEvent: false },
    mayEdit: true,
    ...overrides,
  }
}

const block = (id: string, kind: string, text: string, rewritten = false) => ({ id, kind, text, label: null, rewritten })

function letterBlocks(commune: string, politeness: string | null = null) {
  return [
    block('sender', 'expediteur', 'Skull Darts 71\nAssociation loi 1901 · RNA W712001234\n12 rue des Cibles\n71270 Bellevesvre'),
    block('recipient', 'destinataire', `Madame, Monsieur le Maire\nMairie de ${commune}`),
    block('place-date', 'lieu-date', 'Bellevesvre, le 24 octobre 2026'),
    block('subject', 'objet', 'Objet : demande d’autorisation d’ouverture temporaire d’un débit de boissons'),
    block('salutation', 'paragraphe', 'Madame, Monsieur le Maire,'),
    block('event', 'paragraphe', 'L’association Skull Darts 71 organise « Soirée fléchettes », Salle des fêtes, Bellevesvre.'),
    block('request', 'paragraphe', 'Nous sollicitons l’autorisation d’y tenir une buvette temporaire.'),
    block('count', 'paragraphe', 'Il s’agit de la 1re demande de l’association pour l’année 2026.'),
    block('politeness', 'paragraphe', politeness ?? 'Nous vous prions d’agréer, Madame, Monsieur le Maire, l’expression de nos salutations distinguées.', politeness !== null),
    block('signature', 'signature', 'Camille Martin\nPrésidence de Skull Darts 71'),
    block('limits', 'note', 'Cinq autorisations par association et par an.'),
  ]
}

function letterJson(overrides: Record<string, unknown> = {}) {
  return {
    event,
    ...summary('buvette', 'a-faire', day(6)),
    updatedBy: null,
    fields: [
      { key: 'commune', kind: 'text', value: 'Bellevesvre', choices: [] },
      { key: 'when', kind: 'text', value: 'le samedi 14 novembre 2026, de 20 h à 0 h', choices: [] },
      { key: 'place', kind: 'text', value: 'Salle des fêtes, Bellevesvre', choices: [] },
      { key: 'group3', kind: 'checkbox', value: true, choices: [] },
      { key: 'signer', kind: 'choice', value: 'presidence', choices: ['presidence', 'secretariat'] },
    ],
    blocks: letterBlocks('Bellevesvre'),
    links: [{ label: 'Fiche service-public : buvette temporaire', url: 'https://www.service-public.gouv.fr/associations/vosdroits/F24345' }],
    tickets: null,
    missing: ['insurer'],
    mayEdit: true,
    ...overrides,
  }
}

const associationJson = (overrides: Record<string, unknown> = {}) => ({
  officialName: 'Skull Darts 71', rnaNumber: null, siret: null, seatAddress: null, presidentName: null, secretaryName: null, treasurerName: null,
  insurerName: null, insurancePolicy: null, missing: ['rna', 'seat', 'president', 'insurer'], certificate: null, updatedAt: null, updatedBy: null, mayEdit: true,
  ...overrides,
})

async function signIn(page: Page, roles: string[], path: string, heading: string): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile(roles) }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.goto(`/connexion?redirect=${path}`)
  await page.getByLabel('Email').fill('board@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading)
}

async function openList(page: Page, roles: string[], list: object): Promise<void> {
  await page.route(PROCEDURES, (route) => route.fulfill({ json: list }))
  await signIn(page, roles, `/evenements/${EVENT}/demarches`, 'Démarches')
}

test('the secretary sees every procedure with its deadline and progress', async ({ page }) => {
  await openList(page, ['ROLE_SECRETARY'], listJson())
  const list = page.getByRole('list', { name: 'Démarches de la soirée' })
  await expect(list.getByRole('listitem')).toHaveCount(8)

  const bar = list.getByRole('listitem').filter({ hasText: 'Buvette temporaire' })
  await expect(bar).toContainText(`Autorisation du maire · 5 par an · avant le ${frenchDay(day(6))}`)
  await expect(bar.getByRole('link', { name: 'Préparer : Buvette temporaire' })).toHaveAttribute('href', `/evenements/${EVENT}/demarches/buvette`)
  await expect(list.getByRole('listitem').filter({ hasText: 'Salle' }).first()).toContainText('Date conseillée dépassée')
  await expect(list.getByRole('listitem').filter({ hasText: 'SACEM' })).toContainText(`Envoyée le ${frenchDay(day(-1))}`)
  await expect(list.getByRole('link', { name: 'Ajouter : Billets d’entrée' })).toBeVisible()
  // Démarche inutile pour cette soirée : pas de retard affiché
  await expect(list.getByRole('listitem').filter({ hasText: 'Fermeture tardive' })).not.toContainText('Date conseillée dépassée')

  await expect(page.getByText('À compléter : assureur')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Modifier la fiche' })).toHaveAttribute('href', '/association')
})

test('the secretary adjusts a letter, saves it and marks it sent', async ({ page }) => {
  let saved: Record<string, unknown> | null = null
  await page.route(`${PROCEDURES}/buvette`, (route) => {
    if (route.request().method() !== 'PUT') return route.fulfill({ json: letterJson() })
    saved = route.request().postDataJSON() as Record<string, unknown>
    return route.fulfill({
      json: letterJson({ status: 'prete', edited: true, updatedAt: '2026-10-24T09:00:00+00:00', updatedBy: 'Nina', blocks: letterBlocks('Pierre-de-Bresse', 'Avec nos remerciements.') }),
    })
  })
  await page.route(`${PROCEDURES}/buvette/preview`, (route) => {
    const draft = route.request().postDataJSON() as { values: Record<string, string>; rewrites: Record<string, string> }
    return route.fulfill({ json: { ...letterJson(), blocks: letterBlocks(draft.values.commune ?? 'Bellevesvre', draft.rewrites.politeness ?? null) } })
  })
  await page.route(`${PROCEDURES}/buvette/status`, (route) =>
    route.fulfill({ json: letterJson({ status: 'envoyee', sentOn: day(0), edited: true, blocks: letterBlocks('Pierre-de-Bresse', 'Avec nos remerciements.') }) }),
  )
  await openList(page, ['ROLE_SECRETARY'], listJson())
  await page.getByRole('link', { name: 'Préparer : Buvette temporaire' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Buvette temporaire')

  const sheet = page.getByRole('article', { name: 'Document à imprimer' })
  await expect(sheet).toContainText('Mairie de Bellevesvre')
  await expect(page.getByText('Cinq autorisations par association et par an.')).toBeVisible()
  await expect(sheet).not.toContainText('Cinq autorisations')

  // Un champ change : l'aperçu rendu par l'API suit
  const preview = page.waitForRequest(`${PROCEDURES}/buvette/preview`)
  await page.getByLabel('Commune de la soirée').fill('Pierre-de-Bresse')
  expect(((await preview).postDataJSON() as { values: Record<string, unknown> }).values.commune).toBe('Pierre-de-Bresse')
  await expect(sheet).toContainText('Mairie de Pierre-de-Bresse')
  await expect(page.getByText('Modifications non enregistrées.')).toBeVisible()

  // Un passage se réécrit directement sur la feuille, et se rétablit
  await sheet.getByRole('textbox', { name: 'Paragraphe 5' }).fill('Avec nos remerciements.')
  await expect(sheet.getByRole('button', { name: 'Rétablir le texte d’origine : Paragraphe 5' })).toBeVisible()

  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByText('Enregistré.', { exact: true })).toBeVisible()
  expect(saved).toEqual({
    values: { commune: 'Pierre-de-Bresse', when: 'le samedi 14 novembre 2026, de 20 h à 0 h', place: 'Salle des fêtes, Bellevesvre', group3: true, signer: 'presidence' },
    rewrites: { politeness: 'Avec nos remerciements.' },
  })
  const progress = page.getByRole('region', { name: 'Avancement' })
  await expect(progress.getByRole('button', { name: 'Prête', exact: true })).toHaveAttribute('aria-pressed', 'true')

  await progress.getByRole('button', { name: 'Envoyée', exact: true }).click()
  await expect(progress).toContainText(`Envoyée le ${frenchDay(day(0))}`)
  await expect(page.getByRole('link', { name: /Fiche service-public : buvette temporaire/ })).toHaveAttribute('target', '_blank')
})

test('leaving with unsaved changes asks first', async ({ page }) => {
  await page.route(`${PROCEDURES}/buvette`, (route) => route.fulfill({ json: letterJson() }))
  await page.route(`${PROCEDURES}/buvette/preview`, (route) => route.fulfill({ json: letterJson() }))
  await signIn(page, ['ROLE_SECRETARY'], `/evenements/${EVENT}/demarches/buvette`, 'Buvette temporaire')

  await page.getByLabel('Commune de la soirée').fill('Pierre-de-Bresse')
  page.once('dialog', (dialog) => {
    // Typographie française : espace insécable avant le point d'interrogation
    expect(dialog.message()).toBe('Quitter sans enregistrer les modifications ?')
    void dialog.dismiss()
  })
  await page.getByRole('link', { name: '← Démarches · Soirée fléchettes' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Buvette temporaire')
})

test('entry tickets are numbered and printed as a series', async ({ page }) => {
  const tickets = letterJson({
    ...summary('billets', 'pas-necessaire', day(14)),
    recipient: 'Entrée de la soirée',
    fields: [
      { key: 'count', kind: 'text', value: '100', choices: [] },
      { key: 'first', kind: 'text', value: '101', choices: [] },
      { key: 'price', kind: 'text', value: '2,00 €', choices: [] },
      { key: 'category', kind: 'text', value: 'Entrée', choices: [] },
    ],
    blocks: [block('heading', 'titre', 'Billets d’entrée numérotés'), block('stubs', 'note', 'Garder les souches.')],
    links: [],
    tickets: { organizer: 'Skull Darts 71', event: 'Soirée fléchettes · samedi 14 novembre 2026', category: 'Entrée', price: '2,00 €', first: 101, count: 100 },
  })
  await page.route(`${PROCEDURES}/billets`, (route) => route.fulfill({ json: tickets }))
  await signIn(page, ['ROLE_SECRETARY'], `/evenements/${EVENT}/demarches/billets`, 'Billets d’entrée')

  await expect(page.getByText('N° 101').first()).toBeVisible()
  await expect(page.getByText('N° 104').first()).toBeVisible()
  await expect(page.getByText('N° 105')).toHaveCount(0)
  await expect(page.getByText('100 billets, n° 101 à 200 · 9 pages A4')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Imprimer les billets' })).toBeVisible()
})

test('the treasurer reads the paperwork without changing it', async ({ page }) => {
  await page.route(`${PROCEDURES}/buvette`, (route) => route.fulfill({ json: letterJson({ mayEdit: false }) }))
  await openList(page, ['ROLE_TREASURER'], listJson({ mayEdit: false }))
  await expect(page.getByText('Lecture seule : le secrétariat et la présidence préparent les démarches.')).toBeVisible()
  await page.getByRole('link', { name: 'Voir : Buvette temporaire' }).click()

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Buvette temporaire')
  await expect(page.getByRole('button', { name: 'Enregistrer' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Envoyée', exact: true })).toHaveCount(0)
  await expect(page.getByRole('article', { name: 'Document à imprimer' }).getByRole('textbox')).toHaveCount(0)
  await expect(page.getByLabel('Commune de la soirée')).toBeDisabled()
})

test('the association details stay private and keep the insurance certificate', async ({ page }) => {
  let saved: Record<string, unknown> | null = null
  await page.route('**/api/association-profile', (route) => {
    if (route.request().method() !== 'PUT') return route.fulfill({ json: associationJson() })
    saved = route.request().postDataJSON() as Record<string, unknown>
    return route.fulfill({ json: associationJson({ ...saved, rnaNumber: 'W712001234', missing: ['insurer'], updatedAt: '2026-10-24T09:00:00+00:00', updatedBy: 'Nina' }) })
  })
  await page.route('**/api/association-profile/insurance', (route) =>
    route.fulfill({ json: associationJson({ rnaNumber: 'W712001234', certificate: { validUntil: '2027-08-31', valid: true } }) }),
  )
  await signIn(page, ['ROLE_SECRETARY'], '/association', 'Fiche de l’association')
  await expect(page.getByText('Privée : jamais affichée sur le site, où seule « Bellevesvre » apparaît.')).toBeVisible()

  await page.getByLabel('N° RNA').fill('w712 001 234')
  await page.getByLabel('Adresse du siège').fill('12 rue des Cibles\n71270 Bellevesvre')
  await page.getByLabel('Présidence').fill('Camille Martin')
  await page.getByRole('button', { name: 'Enregistrer la fiche' }).click()
  await expect(page.getByText('Fiche enregistrée.')).toBeVisible()
  expect(saved).toMatchObject({ officialName: 'Skull Darts 71', rnaNumber: 'w712 001 234', seatAddress: '12 rue des Cibles\n71270 Bellevesvre', presidentName: 'Camille Martin' })
  await expect(page.getByLabel('N° RNA')).toHaveValue('W712001234')

  await page.getByLabel('Attestation (PDF, 10 Mo au plus)').setInputFiles({ name: 'attestation.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') })
  await page.getByLabel('Valable jusqu’au').fill('2027-08-31')
  await page.getByRole('button', { name: 'Ranger l’attestation' }).click()
  await expect(page.getByText('Attestation rangée.')).toBeVisible()
  await expect(page.getByText('Valable jusqu’au 31 août 2027')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Télécharger l’attestation (PDF)' })).toHaveAttribute('href', '/api/association-profile/insurance')
})

test('the letter editor reflows down to 320 px', async ({ page }) => {
  await page.route(`${PROCEDURES}/buvette`, (route) => route.fulfill({ json: letterJson() }))
  await page.setViewportSize({ width: 320, height: 640 })
  await signIn(page, ['ROLE_SECRETARY'], `/evenements/${EVENT}/demarches/buvette`, 'Buvette temporaire')
  await expect(page.getByRole('article', { name: 'Document à imprimer' })).toBeVisible()
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
  expect(overflow).toBeLessThanOrEqual(0)
})

test('the paperwork pages meet WCAG 2.2 AA in both themes', async ({ page }) => {
  await page.route(`${PROCEDURES}/buvette`, (route) => route.fulfill({ json: letterJson() }))
  await page.route('**/api/association-profile', (route) => route.fulfill({ json: associationJson() }))
  await openList(page, ['ROLE_SECRETARY'], listJson())
  await auditBothThemes(page)

  await page.getByRole('link', { name: 'Préparer : Buvette temporaire' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Buvette temporaire')
  await auditBothThemes(page)

  await page.getByRole('link', { name: 'Compléter la fiche' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Fiche de l’association')
  await auditBothThemes(page)
})
