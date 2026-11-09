import { expect, type Locator, type Page } from '@playwright/test'

/**
 * « Dans la presse » : une soirée passée et ses coupures, servies par une API simulée. Titres,
 * journaux et noms inventés ; images en PNG 1×1 (jamais de vraie coupure ni de vraie photo).
 */
export const EVENT = '01a1b2c3-0000-7000-8000-0000000000e1'

const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')

const profile = (roles: string[]) => ({
  id: '01a1b2c3-0000-7000-8000-000000000001', email: 'membre@example.com', displayName: 'Alice', phone: null, avatarUrl: null,
  roles, hasPassword: true, twoFactorEnabled: true, twoFactorSetupRequired: false,
})

interface Press {
  outlet: string | null
  publishedOn: string | null
  title: string | null
  credit: string | null
}

export const photo = (n: number, overrides: Record<string, unknown> = {}) => ({
  id: `01a1b2c3-0000-7000-8000-00000000c00${n}`, thumbUrl: `/api/event-photos/c${n}/thumb`, largeUrl: `/api/event-photos/c${n}/large`,
  width: 900, height: 1200, caption: null, uploader: 'Bob', uploaderId: 'b0b', uploadedAt: `2026-11-0${n}T10:00:00+01:00`,
  mine: false, hidden: false, hiddenBy: null, press: null, ...overrides,
})

export const press = (outlet: string | null, publishedOn: string | null, title: string | null, credit: string | null): Press => ({ outlet, publishedOn, title, credit })

export const FIRST_CLIPPING = photo(1, { press: press('La Gazette de la Bresse', '2026-11-03', 'Une Halloween Party qui a fait le plein à Bellevesvre', 'Texte et photos : Camille Martin') })

/** Cinq coupures, de la plus ancienne parution à la plus récente, puis sans date */
export const CLIPPINGS = [
  FIRST_CLIPPING,
  photo(2, { press: press('La Gazette de la Bresse', '2026-11-03', 'Goûter, boum déguisée, happy hour et DJ', null) }),
  photo(3, { press: press('L’Écho de la Seille', '2026-11-04', 'Les petits monstres ont investi la piste de danse', 'Photo : Lucas Petit') }),
  photo(4, { width: 1600, height: 900, press: press('Le Courrier du 71', null, null, null) }),
  photo(5, { press: press(null, null, 'Déguisements, bonbons et animation DJ', 'Texte et photo : Camille Martin') }),
]

const MEMBER = { canAddPhotos: true, canAddVideos: false, canModerate: false }

export const detail = (overrides: Record<string, unknown> = {}) => ({
  id: EVENT, title: 'Halloween Party', location: 'Salle des fêtes, Bellevesvre', startsAt: '2026-10-31T13:00:00+00:00', endsAt: '2026-10-31T23:00:00+00:00',
  season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 48, maybe: 0, isRegistered: false, myAnswer: null,
  full: false, started: true, past: true, hasSummary: false, poster: null, public: false, city: null, publicPath: null,
  description: '', organizer: 'Sandrine', summary: null, canManage: false, photos: [photo(6), photo(7)], pressClippings: CLIPPINGS,
  videos: [], gallery: MEMBER, posts: [], participants: null, guests: null, ...overrides,
})

/** L'événement servi par l'API simulée : relu à chaque appel (la page le recharge après une action) */
export async function openEvent(page: Page, roles: string[], event: () => Record<string, unknown>): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile(roles) }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/view`, (route) => route.fulfill({ status: 204, body: '' }))
  await page.route(`**/api/events/${EVENT}`, (route) => route.fulfill({ json: event() }))
  await page.route('**/api/event-photos/*/thumb', (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route('**/api/event-photos/*/large', (route) => route.fulfill({ body: png, contentType: 'image/png' }))

  await page.goto(`/evenements/${EVENT}`)
  await page.getByLabel('Email').fill('membre@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Halloween Party' })).toBeVisible()
}

export const pressSection = (page: Page) => page.getByRole('region', { name: 'Dans la presse' })
export const ringOf = (page: Page) => pressSection(page).getByRole('group', { name: 'Coupures de presse' })
export const statusOf = (page: Page) => pressSection(page).locator('.orbit__status')
export const card = (page: Page, index: number) => pressSection(page).getByRole('button', { name: `Agrandir la coupure ${index} sur 5`, exact: true })

export async function boxOf(locator: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
  const box = await locator.boundingBox()
  if (box === null) throw new Error(`${locator.toString()} n'est pas affiché`)
  return box
}

/**
 * Les animations du bloc « Dans la presse » finies (l'anneau posé, le reflet du titre passé) : rien
 * n'est lu à mi-chemin. Celles du bloc seulement : les animations liées au défilement de la galerie
 * restent « en cours » tant que la page est là.
 */
export async function settled(page: Page): Promise<void> {
  await page.waitForFunction(() =>
    document.getAnimations().every((animation) => {
      const target = animation.effect instanceof KeyframeEffect ? animation.effect.target : null
      return (target?.closest('section.press') ?? null) === null || animation.playState !== 'running'
    }),
  )
}
