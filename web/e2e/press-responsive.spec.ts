import { test, expect } from '@playwright/test'
import { overflow, SWEEP_WIDTHS } from './overflow'
import { boxOf, detail, openEvent, pressSection, ringOf, settled, statusOf } from './press-fixtures'

/**
 * Une soirée et ses coupures de presse, en Chromium bureau seulement (son nom finit par
 * « responsive.spec.ts », voir playwright.config.ts) : aucun défilement horizontal de 320 à 2560 px,
 * l'anneau compris, et la molette fait défiler la page par-dessus l'anneau.
 */

test('the event page with clippings never scrolls sideways from 320 to 2560 px', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  await pressSection(page).scrollIntoViewIfNeeded()

  for (const width of SWEEP_WIDTHS) {
    await page.setViewportSize({ width, height: 900 })
    await settled(page)
    const r = await overflow(page)
    expect(r.sw, `${width}px — défile de côté : ${r.culprits.join(', ')}`).toBeLessThanOrEqual(r.vw)
    expect(r.culprits, `${width}px — dépasse du bord`).toEqual([])
  }
})

test('the wheel over the ring scrolls the page and leaves the ring where it is', async ({ page }) => {
  await openEvent(page, ['ROLE_MEMBER'], () => detail())
  const ring = ringOf(page)
  await ring.scrollIntoViewIfNeeded()
  const box = await boxOf(ring)
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
  const before = await page.evaluate(() => window.scrollY)

  await page.mouse.wheel(0, 400)

  await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(before)
  await expect(statusOf(page)).toHaveText('Coupure 1 sur 5 · La Gazette de la Bresse')
})
