import { test, expect, type Page } from '@playwright/test'

/**
 * Aucun défilement horizontal de 320 à 2560 px (règle du projet), points de rupture
 * vérifiés de part et d'autre (639/640, 767/768…). Les éléments qui dépassent sont
 * nommés ; un défilement contenu (carrousel, tableau) est permis.
 * Chromium bureau seulement (voir playwright.config.ts) : l'émulation mobile peut
 * rétrécir un contenu trop large.
 */

const WIDTHS = [320, 360, 375, 390, 414, 440, 639, 640, 767, 768, 1023, 1024, 1279, 1280, 1440, 1920, 2560]
const PAGES = ['/', '/en', '/connexion', '/inscription', '/evenements', '/e/soiree-cricket-2026-11-14-a1b2c3', '/mentions-legales', '/confidentialite']

const event = {
  slug: 'soiree-cricket-2026-11-14-a1b2c3', title: 'Soirée cricket et grand tournoi d’automne des Skull Darts', description: 'Initiation et tournoi amical.',
  city: 'Saint-Germain-du-Plain', startsAt: '2026-11-14T19:00:00+00:00', endsAt: '2026-11-14T23:00:00+00:00', status: 'prevu', cancelReason: null,
  capacity: 20, going: 7, maybe: 3, full: false, started: false, past: false, poster: null,
}

async function mockPublicApi(page: Page): Promise<void> {
  // Plusieurs rendez-vous : le carrousel 3D déborde et doit rester contenu
  const later = { ...event, slug: 'halloween-party-2026-10-31-d4e5f6', title: 'Halloween Party' }
  const past = { ...event, slug: 'soiree-de-rentree-2026-10-03-a7b8c9', title: 'Soirée de rentrée', past: true, started: true }
  await page.route('**/api/public/events', (route) => route.fulfill({ json: { items: [event, later], past: [past] } }))
  await page.route(`**/api/public/events/${event.slug}`, (route) => route.fulfill({ json: event }))
  await page.route(`**/api/public/events/${event.slug}/view`, (route) => route.fulfill({ status: 204, body: '' }))
}

/** Largeur qui dépasse et éléments en cause (hors zones à défilement contenu) */
async function overflow(page: Page): Promise<{ sw: number; vw: number; culprits: string[] }> {
  await page.evaluate(() => document.fonts.ready.then(() => undefined))
  // Après un redimensionnement, les requêtes média ne s'appliquent qu'à l'image suivante
  await page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))))
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth
    const inScroller = (el: Element) => {
      for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        if (getComputedStyle(p).overflowX !== 'visible') return true
      }
      return false
    }
    const culprits = [...document.body.querySelectorAll('*')]
      .filter((el) => {
        const b = el.getBoundingClientRect()
        return b.width > 1 && (b.right > vw + 1 || b.left < -1) && el.checkVisibility() && !inScroller(el)
      })
      .slice(0, 5)
      .map((el) => `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ''} ${el.getAttribute('class') ?? ''}`)
    return { sw: document.documentElement.scrollWidth, vw, culprits }
  })
}

for (const path of PAGES) {
  test(`${path}: no horizontal overflow from 320 to 2560 px`, async ({ page }) => {
    await mockPublicApi(page)
    // Animations coupées : on mesure la mise en page finale, pas une étape d'animation
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto(path)
    await expect(page.locator('main h1, main [role="alert"]').first()).toBeVisible()

    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 800 })
      const r = await overflow(page)
      expect(r.sw, `${width}px — défile de côté : ${r.culprits.join(', ')}`).toBeLessThanOrEqual(r.vw)
      expect(r.culprits, `${width}px — dépasse du bord`).toEqual([])
    }
  })
}

// WCAG 1.4.10 : à 320 × 256 (zoom 400 %), pas de défilement dans les deux sens
for (const path of ['/', '/connexion', '/e/soiree-cricket-2026-11-14-a1b2c3']) {
  test(`${path}: reflows at 320 × 256 (400 % zoom)`, async ({ page }) => {
    await mockPublicApi(page)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width: 320, height: 256 })
    await page.goto(path)
    // Le site démarre après l'écran titre : on mesure la page, pas lui
    await page.locator('main').first().waitFor()
    const r = await overflow(page)
    expect(r.sw, `défile de côté : ${r.culprits.join(', ')}`).toBeLessThanOrEqual(r.vw)
    // En-tête non collant à cette hauteur : il ne masque ni le contenu ni le focus
    await expect(page.locator('header.header')).toHaveCSS('position', 'relative')
  })
}
