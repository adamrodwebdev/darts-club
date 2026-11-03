import { test, expect, type Locator, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

const EVENT = '01a1b2c3-0000-7000-8000-0000000000a7'
const VIDEO = '01a1b2c3-0000-7000-8000-0000000000b7'
const PHOTO = '01a1b2c3-0000-7000-8000-0000000000c7'

// PNG 1×1 : vignettes et aperçus de l'API simulée
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')

const profile = (roles: string[]) => ({
  id: '01a1b2c3-0000-7000-8000-000000000001', email: 'presidente@example.com', displayName: 'Présidente', phone: null, avatarUrl: null,
  roles, hasPassword: true, twoFactorEnabled: true, twoFactorSetupRequired: false,
})

const photo = {
  id: PHOTO, thumbUrl: `/api/event-photos/${PHOTO}/thumb`, largeUrl: `/api/event-photos/${PHOTO}/large`, width: 480, height: 320,
  caption: null, uploader: 'Bob', uploaderId: 'b0b', uploadedAt: '2026-10-10T21:00:00+02:00', mine: false, hidden: false, hiddenBy: null,
}

/** Trois photos de la soirée, de trois membres, dans l'ordre d'arrivée */
const threePhotos = ['Bob', 'Chloé', 'David'].map((uploader, n) => ({
  ...photo, id: `${PHOTO.slice(0, -1)}${n}`, thumbUrl: `/api/event-photos/p${n}/thumb`, largeUrl: `/api/event-photos/p${n}/large`, uploader,
  uploadedAt: `2026-10-10T21:0${n}:00+02:00`,
}))

const video = (overrides: Record<string, unknown> = {}) => ({
  id: VIDEO, status: 'prete', fileName: 'IMG_2041.MOV', size: 12, received: 12, durationMs: 42_000, width: 720, height: 1280,
  videoUrl: `/api/event-videos/${VIDEO}/file`, av1Url: `/api/event-videos/${VIDEO}/file/av1`, posterUrl: `/api/event-videos/${VIDEO}/poster`,
  failure: null, hidden: false, hiddenBy: null, uploader: 'Présidente', uploaderId: 'p1', uploadedAt: '2026-10-10T20:30:00+02:00', mine: true, ...overrides,
})

const MEMBER = { canAddPhotos: true, canAddVideos: false, canModerate: false }
const PRESIDENCY = { canAddPhotos: true, canAddVideos: true, canModerate: true }

const detail = (videos: unknown[], gallery: Record<string, boolean>, photos: unknown[]) => ({
  id: EVENT, title: 'Soirée fléchettes', location: 'Salle des fêtes, Bellevesvre', startsAt: '2026-10-10T18:00:00+00:00', endsAt: '2026-10-10T22:00:00+00:00',
  season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 12, maybe: 0, isRegistered: false, myAnswer: null,
  full: false, started: true, past: true, hasSummary: false, poster: null, public: false, city: null, publicPath: null,
  description: '', organizer: 'Alice', summary: null, canManage: false, photos, videos, gallery, posts: [], participants: null, guests: null,
})

async function openEvent(page: Page, roles: string[], videos: unknown[], gallery: Record<string, boolean>, photos: unknown[] = [photo]): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile(roles) }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/view`, (route) => route.fulfill({ status: 204, body: '' }))
  await page.route(`**/api/events/${EVENT}`, (route) => route.fulfill({ json: detail(videos, gallery, photos) }))
  await page.route('**/api/event-photos/*/thumb', (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route('**/api/event-photos/*/large', (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route('**/api/event-videos/*/poster', (route) => route.fulfill({ body: png, contentType: 'image/png' }))

  await page.goto(`/evenements/${EVENT}`)
  await page.getByLabel('Email').fill('presidente@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { name: /Photos et vidéos/ })).toBeVisible()
}

/** Le carrousel de la page, amené à l'écran (ses grandes images ne se chargent qu'à son approche) */
async function carouselOf(page: Page): Promise<Locator> {
  const carousel = page.getByRole('group', { name: 'Photos et vidéos', exact: true })
  await carousel.scrollIntoViewIfNeeded()
  return carousel
}

/** Le média à l'écran : les autres diapositives sont cachées aux lecteurs d'écran */
const shown = (scope: Locator | Page, name: string) => scope.getByRole('group', { name, exact: true })

async function boxOf(locator: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
  const box = await locator.boundingBox()
  if (box === null) throw new Error(`${locator.toString()} n'est pas affiché`)
  return box
}

test('the gallery plays the lightest video format first and filters by kind', async ({ page }) => {
  await openEvent(page, ['ROLE_PRESIDENT', 'ROLE_MEMBER'], [video()], PRESIDENCY)

  await expect(page.getByText('Visibles par les membres validés seulement')).toBeVisible()
  // Typographie française : espace insécable avant les deux-points (\s la couvre)
  await expect(page.getByText(/Vidéos\s:\s60 secondes au plus/)).toBeVisible()
  // La vidéo (20 h 30) passe avant la photo (21 h), et rien ne se lit tout seul
  const carousel = await carouselOf(page)
  await expect(shown(carousel, 'Vidéo 1 sur 2')).toBeVisible()
  await expect(carousel.getByRole('button', { name: 'Lire la vidéo 1 sur 2, 0:42' })).toBeVisible()
  await auditBothThemes(page)

  await page.getByRole('button', { name: 'Vidéos · 1' }).click()
  await expect(page.getByRole('button', { name: /^Photo \d sur/ })).toHaveCount(0)
  const player = shown(page, 'Vidéo 1 sur 1').locator('video')
  // Sans légende : une légende tirée de la soirée (lecteurs d'écran, moteurs)
  await expect(player).toHaveAttribute('aria-label', /^Vidéo de «\s?Soirée fléchettes\s?», le 10 octobre 2026, ajoutée par Présidente$/)
  await expect(player).toHaveAttribute('preload', 'none')
  await expect(player.locator('source').first()).toHaveAttribute('src', `/api/event-videos/${VIDEO}/file/av1`)
  await expect(player.locator('source').nth(1)).toHaveAttribute('src', `/api/event-videos/${VIDEO}/file`)
})

test('a member asks for a photo to be removed, after a confirmation', async ({ page }) => {
  const requests: string[] = []
  await page.route(`**/api/event-photos/${PHOTO}/removal-request`, (route) => {
    requests.push(route.request().method())
    return route.fulfill({ status: 204, body: '' })
  })
  await openEvent(page, ['ROLE_MEMBER'], [], MEMBER)

  // Un membre ajoute des photos, pas de vidéos ; au téléphone, le bouton dit « + Ajouter » mais le champ garde son nom
  await expect(page.getByLabel('Ajouter des photos', { exact: true })).toBeAttached()
  await expect(page.getByText('Ajouter des photos ou des vidéos')).toHaveCount(0)
  const carousel = await carouselOf(page)
  // Photo envoyée sans légende : son texte alternatif est tiré de la soirée, jamais vide
  await expect(shown(carousel, 'Photo 1 sur 1').getByRole('img')).toHaveAttribute('alt', /^Photo de «\s?Soirée fléchettes\s?», le 10 octobre 2026, ajoutée par Bob$/)

  await page.getByRole('button', { name: 'Demander le retrait' }).click()
  const confirmation = page.getByRole('dialog', { name: /^Demander le retrait\s\?$/ })
  await expect(confirmation.getByText(/disparaît tout de suite de la galerie/)).toBeVisible()
  await confirmation.getByRole('button', { name: 'Demander le retrait' }).click()

  await expect.poll(() => requests).toEqual(['POST'])
})

test('deleting your own photo asks first', async ({ page }) => {
  const deletions: string[] = []
  await page.route(`**/api/event-photos/${PHOTO}`, (route) => {
    if (route.request().method() !== 'DELETE') return route.fallback()
    deletions.push(route.request().method())
    return route.fulfill({ status: 204, body: '' })
  })
  await openEvent(page, ['ROLE_MEMBER'], [], MEMBER, [{ ...photo, mine: true }])

  await page.getByRole('button', { name: 'Supprimer' }).click()
  const confirmation = page.getByRole('dialog', { name: /^Supprimer cette photo\s\?$/ })
  await expect(confirmation.getByText('Elle disparaît de la galerie pour tout le monde.')).toBeVisible()
  await confirmation.getByRole('button', { name: 'Annuler' }).click()
  await expect(confirmation).toBeHidden()
  expect(deletions).toEqual([])

  await page.getByRole('button', { name: 'Supprimer' }).click()
  await confirmation.getByRole('button', { name: 'Supprimer' }).click()
  await expect.poll(() => deletions).toEqual(['DELETE'])
})

test('the swiper moves one photo at a time, or jumps straight to one, by thumbnail, arrow or key', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], [], MEMBER, threePhotos)
  const carousel = await carouselOf(page)
  await expect(shown(carousel, 'Photo 1 sur 3')).toBeVisible()

  await page.getByRole('button', { name: 'Photo 3 sur 3', exact: true }).click()
  await expect(shown(carousel, 'Photo 3 sur 3')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Photo 3 sur 3', exact: true })).toHaveAttribute('aria-current', 'true')
  await expect(page.getByText('Ajoutée par David', { exact: true })).toBeVisible()
  // Annoncé aux lecteurs d'écran
  await expect(page.getByText('Photo 3 sur 3, ajoutée par David')).toBeAttached()

  await page.getByRole('button', { name: 'Précédente' }).click()
  await expect(shown(carousel, 'Photo 2 sur 3')).toBeVisible()

  await carousel.focus()
  await page.keyboard.press('ArrowRight')
  await expect(shown(carousel, 'Photo 3 sur 3')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Suivante' })).toHaveAttribute('aria-disabled', 'true')
  await page.keyboard.press('Home')
  await expect(shown(carousel, 'Photo 1 sur 3')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Précédente' })).toHaveAttribute('aria-disabled', 'true')
})

test('tapping the photo opens it full screen, and leaving comes back to the last one seen', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], [], MEMBER, threePhotos)
  const carousel = await carouselOf(page)
  await page.getByRole('button', { name: 'Photo 2 sur 3', exact: true }).click()
  await shown(carousel, 'Photo 2 sur 3').getByRole('img').click()

  const viewer = page.getByRole('dialog')
  await expect(viewer).toHaveAccessibleName('Photo 2 / 3')
  await expect(shown(viewer, 'Photo 2 sur 3').getByRole('img')).toBeVisible()
  await auditBothThemes(page)

  await viewer.getByRole('button', { name: 'Suivante' }).click()
  await expect(viewer).toHaveAccessibleName('Photo 3 / 3')
  await viewer.getByRole('button', { name: 'Fermer la fenêtre' }).click()

  await expect(viewer).toBeHidden()
  await expect(shown(carousel, 'Photo 3 sur 3')).toBeVisible()
})

// Signalé le 2 novembre 2026 : au doigt, sans touche Échap, la visionneuse ne se refermait plus
test('the full-screen viewer closes with its own button, a removal request pending', async ({ page }) => {
  await openEvent(page, ['ROLE_PRESIDENT', 'ROLE_MEMBER'], [], PRESIDENCY, [{ ...photo, hidden: true, hiddenBy: 'Bob' }])

  await (await carouselOf(page)).getByRole('img').waitFor()
  await page.getByRole('button', { name: 'Voir en plein écran' }).click()
  const viewer = page.getByRole('dialog')
  await expect(viewer).toHaveAccessibleName('Photo 1 / 1')
  await expect(viewer.getByRole('button', { name: 'Remettre en ligne' })).toBeVisible()
  await viewer.getByRole('button', { name: 'Fermer la fenêtre' }).click()

  await expect(viewer).toBeHidden()
  await expect(page.getByRole('button', { name: 'Voir en plein écran' })).toBeVisible()
})

test('a mouse drag swipes to the next photo without opening it', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], [], MEMBER, threePhotos)
  const carousel = await carouselOf(page)
  await expect(shown(carousel, 'Photo 1 sur 3').getByRole('img')).toBeVisible()
  const box = await boxOf(carousel)

  await page.mouse.move(box.x + box.width * 0.8, box.y + box.height / 2)
  await page.mouse.down()
  await page.mouse.move(box.x + box.width * 0.4, box.y + box.height / 2, { steps: 8 })
  await page.mouse.up()

  await expect(shown(carousel, 'Photo 2 sur 3')).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

// Demande du 3 novembre 2026 : regarder les photos sans faire défiler la page
for (const screen of [{ width: 390, height: 844 }, { width: 393, height: 659 }]) {
  test(`title, filters, photo and thumbnails fit one ${screen.width}×${screen.height} phone screen`, async ({ page }) => {
    await page.setViewportSize(screen)
    await openEvent(page, ['ROLE_MEMBER'], [video({ mine: false })], MEMBER, threePhotos)
    const heading = page.getByRole('heading', { name: /Photos et vidéos/ })
    // Le titre de la galerie en haut, sous l'en-tête collant (scroll-padding de la page)
    await heading.evaluate((element) => element.scrollIntoView({ block: 'start' }))

    const top = await boxOf(heading)
    const strip = await boxOf(page.getByRole('list', { name: 'Aller à une photo ou une vidéo' }))
    expect(top.y).toBeGreaterThanOrEqual(0)
    expect(strip.y + strip.height).toBeLessThanOrEqual(screen.height)
    // La photo va d'un bord à l'autre de l'écran, sans jamais élargir la page
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0)
  })
}

test('a video is sent in chunks with its progress', async ({ page }) => {
  const chunks: string[] = []
  await page.route(`**/api/events/${EVENT}/videos`, (route) =>
    route.fulfill({ status: 201, json: { ...video({ status: 'envoi', received: 0, videoUrl: null, av1Url: null, posterUrl: null }), chunkSize: 8 } }),
  )
  await page.route(`**/api/event-videos/${VIDEO}/chunks/*`, (route) => {
    const offset = Number(route.request().url().split('/').pop())
    chunks.push(`${offset}:${route.request().headers()['content-type']}`)
    const received = Math.min(12, offset + 8)
    return route.fulfill({ json: video(received === 12 ? {} : { status: 'envoi', received, videoUrl: null, av1Url: null, posterUrl: null }) })
  })
  await openEvent(page, ['ROLE_PRESIDENT', 'ROLE_MEMBER'], [], PRESIDENCY)

  await page.locator('input[type=file]').setInputFiles({ name: 'IMG_2041.MOV', mimeType: 'video/quicktime', buffer: Buffer.from('012345678901') })

  await expect(page.getByRole('status').filter({ hasText: 'Ajoutée à la galerie' })).toBeVisible()
  expect(chunks).toEqual(['0:application/octet-stream', '8:application/octet-stream'])
})
