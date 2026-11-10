import AxeBuilder from '@axe-core/playwright'
import { test, expect } from '@playwright/test'
import { auditBothThemes, THEMES } from './a11y'
import { boxOf, card, CLIPPINGS, detail, FIRST_CLIPPING, openEvent, photo, press, pressSection, ringOf, settled, statusOf } from './press-fixtures'

/**
 * « Dans la presse » : les coupures en anneau au-dessus de la galerie, tournées à la demande, ouvertes
 * en grand ; le bureau les marque et les rend à la galerie. Le balayage des largeurs et la molette :
 * e2e/press-responsive.spec.ts (Chromium bureau).
 */
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

test('a member turns the clippings around the headline with the arrows, the keys and a drag', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  const ring = ringOf(page)
  await ring.scrollIntoViewIfNeeded()

  // Au-dessus de la galerie, qui ne les montre plus
  const pressTop = (await boxOf(pressSection(page).getByRole('heading', { name: 'Dans la presse' }))).y
  const galleryTop = (await boxOf(page.getByRole('heading', { name: /Photos et vidéos/ }))).y
  expect(pressTop).toBeLessThan(galleryTop)
  await expect(page.getByRole('heading', { name: /Photos et vidéos/ })).toContainText('(2)')
  await expect(statusOf(page)).toHaveText('Coupure 1 sur 5 · La Gazette de la Bresse')

  await pressSection(page).getByRole('button', { name: 'Coupure suivante' }).click()
  await expect(statusOf(page)).toHaveText('Coupure 2 sur 5 · La Gazette de la Bresse')
  await expect(pressSection(page).locator('[aria-live="polite"]')).toHaveText('Coupure 2 sur 5 · La Gazette de la Bresse')

  await card(page, 2).focus()
  await page.keyboard.press('ArrowRight')
  await expect(statusOf(page)).toHaveText('Coupure 3 sur 5 · L’Écho de la Seille')
  await expect(card(page, 3)).toBeFocused()
  await page.keyboard.press('ArrowLeft')
  await page.keyboard.press('ArrowLeft')
  await page.keyboard.press('ArrowLeft')
  await expect(statusOf(page)).toHaveText('Coupure 5 sur 5')

  // Un glissé d'une largeur de carte vers la gauche : la suivante vient devant, rien ne s'ouvre
  const width = await pressSection(page).locator('li.clipping').first().evaluate((element) => (element as HTMLElement).offsetWidth)
  const stage = await boxOf(ring)
  await page.mouse.move(stage.x + stage.width * 0.75, stage.y + stage.height * 0.6)
  await page.mouse.down()
  await page.mouse.move(stage.x + stage.width * 0.75 - width, stage.y + stage.height * 0.6, { steps: 10 })
  await page.mouse.up()
  await expect(statusOf(page)).toHaveText('Coupure 1 sur 5 · La Gazette de la Bresse')
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('a clipping opens large, the window turns to its neighbours and closes with its own button', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  await ringOf(page).scrollIntoViewIfNeeded()

  // Une carte du fond vient devant quand elle prend le focus ; Entrée l'ouvre
  await card(page, 3).focus()
  await expect(statusOf(page)).toHaveText('Coupure 3 sur 5 · L’Écho de la Seille')
  await page.keyboard.press('Enter')
  const viewer = page.getByRole('dialog', { name: 'Coupure 3 / 5' })
  await expect(viewer.getByRole('img')).toHaveAttribute('alt', /^Coupure de presse de L’Écho de la Seille\s:\sLes petits monstres ont investi la piste de danse$/)
  await expect(viewer.getByRole('img')).toHaveAttribute('src', '/api/event-photos/c3/large')
  await expect(viewer.getByText(/^Photo\s:\sLucas Petit$/)).toBeVisible()
  // Droit à l'image : la coupure garde « Demander le retrait »
  await expect(viewer.getByRole('button', { name: 'Demander le retrait' })).toBeVisible()

  await viewer.getByRole('button', { name: 'Coupure suivante' }).click()
  await expect(page.getByRole('dialog', { name: 'Coupure 4 / 5' })).toBeVisible()
  await page.keyboard.press('ArrowRight')
  const last = page.getByRole('dialog', { name: 'Coupure 5 / 5' })
  await expect(last.getByRole('img')).toHaveAttribute('alt', /^Coupure de presse\s:\sDéguisements, bonbons et animation DJ$/)
  await last.getByRole('button', { name: 'Fermer la fenêtre' }).click()

  await expect(page.getByRole('dialog')).toHaveCount(0)
  // L'anneau garde devant la dernière coupure vue
  await expect(statusOf(page)).toHaveText('Coupure 5 sur 5')
})

test('the turning ring and the flat grid both pass axe in the two themes', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  await ringOf(page).scrollIntoViewIfNeeded()
  await pressSection(page).getByRole('button', { name: 'Coupure suivante' }).click()

  // L'anneau arrêté entre deux tours : les textes du fond sont cachés, ceux de devant entiers
  for (const theme of THEMES) {
    await page.emulateMedia({ colorScheme: theme })
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
    await settled(page)
    const results = await new AxeBuilder({ page }).include('section.press').withTags(WCAG).analyze()
    expect(results.violations.map((v) => `${v.id} : ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([])
  }

  // Animations réduites : la grille, et toute la page
  await auditBothThemes(page)
  await expect(ringOf(page)).toHaveCount(0)
})

test('with motion reduced, the clippings lie flat in a grid, whole and in order', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await openEvent(page, ['ROLE_MEMBER'], () => detail())

  await expect(pressSection(page).getByRole('listitem')).toHaveCount(5)
  await expect(ringOf(page)).toHaveCount(0)
  await expect(pressSection(page).getByRole('button', { name: 'Coupure suivante' })).toHaveCount(0)
  await expect(pressSection(page).getByRole('listitem').first()).toContainText('Une Halloween Party qui a fait le plein à Bellevesvre')
  await expect(pressSection(page).getByRole('listitem').nth(3)).toContainText('Le Courrier du 71')
})

test('a finger swiping up or down over the ring always scrolls the page', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  await ringOf(page).scrollIntoViewIfNeeded()

  await expect(pressSection(page).locator('.orbit__stage')).toHaveCSS('touch-action', 'pan-y')
})

test('a manager marks a gallery photo as a press clipping', async ({ page }) => {
  const bodies: unknown[] = []
  let filed = false
  const target = photo(6)
  const clipped = { ...target, press: press('La Gazette de la Bresse', '2026-11-03', 'Une Halloween Party qui a fait le plein', 'Texte : Camille Martin') }
  await page.route('**/api/event-photos/*/press', (route) => {
    bodies.push(route.request().postDataJSON())
    filed = true
    return route.fulfill({ json: clipped })
  })
  await openEvent(page, ['ROLE_SECRETARY', 'ROLE_MEMBER'], () =>
    detail({ canManage: true, photos: filed ? [] : [target], pressClippings: filed ? [clipped] : [] }),
  )
  // Sans coupure, pas de bloc « Dans la presse »
  await expect(pressSection(page)).toHaveCount(0)
  await page.getByRole('group', { name: 'Photos et vidéos', exact: true }).scrollIntoViewIfNeeded()

  await page.getByRole('button', { name: 'Marquer comme coupure de presse' }).click()
  const dialog = page.getByRole('dialog', { name: 'Coupure de presse' })
  await dialog.getByLabel('Journal', { exact: true }).fill('La Gazette de la Bresse')
  await dialog.getByLabel('Date de parution', { exact: true }).fill('2026-11-03')
  await dialog.getByLabel('Titre de l’article', { exact: true }).fill('Une Halloween Party qui a fait le plein')
  await dialog.getByLabel('Crédit', { exact: true }).fill('Texte : Camille Martin')
  await expect(dialog.getByLabel('Titre de l’article', { exact: true })).toHaveAttribute('maxlength', '200')
  await dialog.getByRole('button', { name: 'Enregistrer' }).click()

  await expect(dialog).toBeHidden()
  expect(bodies).toEqual([{ outlet: 'La Gazette de la Bresse', publishedOn: '2026-11-03', title: 'Une Halloween Party qui a fait le plein', credit: 'Texte : Camille Martin' }])
  // Une seule coupure : posée à plat, au-dessus de la galerie désormais vide
  await expect(pressSection(page).getByRole('button', { name: 'Agrandir la coupure 1 sur 1' })).toBeVisible()
  await expect(page.getByText('Pas encore de photo ni de vidéo.')).toBeVisible()
})

test('a manager edits a clipping from its window, then gives it back to the gallery', async ({ page }) => {
  const calls: string[] = []
  let returned = false
  await page.route('**/api/event-photos/*/press', (route) => {
    calls.push(`${route.request().method()} ${new URL(route.request().url()).pathname}`)
    returned = route.request().method() === 'DELETE'
    return route.fulfill(returned ? { status: 204, body: '' } : { json: FIRST_CLIPPING })
  })
  await openEvent(page, ['ROLE_SECRETARY', 'ROLE_MEMBER'], () =>
    detail({
      canManage: true,
      photos: returned ? [photo(6), photo(7), { ...FIRST_CLIPPING, press: null }] : [photo(6), photo(7)],
      pressClippings: returned ? CLIPPINGS.slice(1) : CLIPPINGS,
    }),
  )
  await ringOf(page).scrollIntoViewIfNeeded()

  await card(page, 1).click()
  const viewer = page.getByRole('dialog', { name: 'Coupure 1 / 5' })
  await viewer.getByRole('button', { name: 'Modifier la coupure de presse' }).click()
  const editor = page.getByRole('dialog', { name: 'Modifier la coupure de presse' })
  const outlet = editor.getByLabel('Journal', { exact: true })
  await expect(outlet).toHaveValue('La Gazette de la Bresse')
  await expect(editor.getByLabel('Date de parution', { exact: true })).toHaveValue('2026-11-03')
  // Les flèches restent au champ : la coupure derrière ne change pas
  await outlet.press('ArrowLeft')
  await expect(editor).toBeVisible()
  await expect(viewer).toBeVisible()

  await editor.getByRole('button', { name: 'Rendre à la galerie' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  expect(calls).toEqual([`DELETE /api/event-photos/${FIRST_CLIPPING.id}/press`])
  await expect(statusOf(page)).toHaveText('Coupure 1 sur 4 · La Gazette de la Bresse')
  await expect(page.getByRole('heading', { name: /Photos et vidéos/ })).toContainText('(3)')
})

test('a plain member sees no action to mark or edit a clipping', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  await page.getByRole('group', { name: 'Photos et vidéos', exact: true }).scrollIntoViewIfNeeded()

  await expect(page.getByRole('button', { name: 'Demander le retrait' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Marquer comme coupure de presse' })).toHaveCount(0)
  await card(page, 1).click()
  const viewer = page.getByRole('dialog', { name: 'Coupure 1 / 5' })
  await expect(viewer.getByRole('button', { name: 'Demander le retrait' })).toBeVisible()
  await expect(viewer.getByRole('button', { name: 'Modifier la coupure de presse' })).toHaveCount(0)
})
