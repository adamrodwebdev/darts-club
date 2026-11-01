import { test, expect, type Page } from '@playwright/test'
import { auditBothThemes } from './a11y'

const EVENT = '01a1b2c3-0000-7000-8000-0000000000g1'
const VIDEO = '01a1b2c3-0000-7000-8000-0000000000v1'
const PHOTO = '01a1b2c3-0000-7000-8000-0000000000p1'

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

const video = (overrides: Record<string, unknown> = {}) => ({
  id: VIDEO, status: 'prete', fileName: 'IMG_2041.MOV', size: 12, received: 12, durationMs: 42_000, width: 720, height: 1280,
  videoUrl: `/api/event-videos/${VIDEO}/file`, av1Url: `/api/event-videos/${VIDEO}/file/av1`, posterUrl: `/api/event-videos/${VIDEO}/poster`,
  failure: null, hidden: false, hiddenBy: null, uploader: 'Présidente', uploaderId: 'p1', uploadedAt: '2026-10-10T20:30:00+02:00', mine: true, ...overrides,
})

const detail = (videos: unknown[], gallery: Record<string, boolean>) => ({
  id: EVENT, title: 'Soirée fléchettes', location: 'Salle des fêtes, Bellevesvre', startsAt: '2026-10-10T18:00:00+00:00', endsAt: '2026-10-10T22:00:00+00:00',
  season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 12, maybe: 0, isRegistered: false, myAnswer: null,
  full: false, started: true, past: true, hasSummary: false, poster: null, public: false, city: null, publicPath: null,
  description: '', organizer: 'Alice', summary: null, canManage: false, photos: [photo], videos, gallery, posts: [], participants: null, guests: null,
})

async function openEvent(page: Page, roles: string[], videos: unknown[], gallery: Record<string, boolean>): Promise<void> {
  await page.route('**/api/auth/login', (route) => route.fulfill({ json: profile(roles) }))
  await page.route('**/api/notifications', (route) => route.fulfill({ json: { items: [], unread: 0 } }))
  await page.route('**/api/realtime/authorization', (route) => route.fulfill({ status: 503, json: {} }))
  await page.route(`**/api/events/${EVENT}/view`, (route) => route.fulfill({ status: 204, body: '' }))
  await page.route(`**/api/events/${EVENT}`, (route) => route.fulfill({ json: detail(videos, gallery) }))
  await page.route('**/api/event-photos/*/thumb', (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route('**/api/event-photos/*/large', (route) => route.fulfill({ body: png, contentType: 'image/png' }))
  await page.route('**/api/event-videos/*/poster', (route) => route.fulfill({ body: png, contentType: 'image/png' }))

  await page.goto(`/evenements/${EVENT}`)
  await page.getByLabel('Email').fill('presidente@example.com')
  await page.getByLabel('Mot de passe').fill('Tr1ple-20-Bullseye!')
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
  await expect(page.getByRole('heading', { name: /Photos et vidéos/ })).toBeVisible()
}

test('the gallery plays the lightest video format first and filters by kind', async ({ page }) => {
  await openEvent(page, ['ROLE_PRESIDENT', 'ROLE_MEMBER'], [video()], { canAddPhotos: true, canAddVideos: true, canModerate: true })

  await expect(page.getByText('Visibles par les membres validés seulement')).toBeVisible()
  // Typographie française : espace insécable avant les deux-points (\s la couvre)
  await expect(page.getByText(/Vidéos\s:\s60 secondes au plus/)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Lire la vidéo 1 sur 2, 0:42' })).toBeVisible()
  // Grille auditée ; le lecteur montre des vidéos d'adhérents, sans sous-titres
  await auditBothThemes(page)

  await page.getByRole('button', { name: 'Vidéos · 1' }).click()
  await expect(page.getByRole('button', { name: /Agrandir la photo/ })).toHaveCount(0)
  await page.getByRole('button', { name: 'Lire la vidéo 1 sur 1, 0:42' }).click()

  const player = page.getByRole('dialog').locator('video')
  await expect(player).toHaveAttribute('preload', 'none')
  await expect(player.locator('source').first()).toHaveAttribute('src', `/api/event-videos/${VIDEO}/file/av1`)
  await expect(player.locator('source').nth(1)).toHaveAttribute('src', `/api/event-videos/${VIDEO}/file`)
})

test('a member asks for a photo to be removed', async ({ page }) => {
  const requests: string[] = []
  await page.route(`**/api/event-photos/${PHOTO}/removal-request`, (route) => {
    requests.push(route.request().method())
    return route.fulfill({ status: 204, body: '' })
  })
  await openEvent(page, ['ROLE_MEMBER'], [], { canAddPhotos: true, canAddVideos: false, canModerate: false })

  // Un membre ajoute des photos, pas de vidéos
  await expect(page.getByText('Ajouter des photos', { exact: true })).toBeVisible()
  await expect(page.getByText('Ajouter des photos ou des vidéos')).toHaveCount(0)
  await page.getByRole('button', { name: 'Agrandir la photo 1 sur 1' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Demander le retrait' }).click()
  await expect(page.getByText(/disparaît tout de suite de la galerie/)).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: 'Demander le retrait' }).last().click()

  await expect.poll(() => requests).toEqual(['POST'])
})

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
  await openEvent(page, ['ROLE_PRESIDENT', 'ROLE_MEMBER'], [], { canAddPhotos: true, canAddVideos: true, canModerate: true })

  await page.locator('input[type=file]').setInputFiles({ name: 'IMG_2041.MOV', mimeType: 'video/quicktime', buffer: Buffer.from('012345678901') })

  await expect(page.getByRole('status').filter({ hasText: 'Ajoutée à la galerie' })).toBeVisible()
  expect(chunks).toEqual(['0:application/octet-stream', '8:application/octet-stream'])
})
