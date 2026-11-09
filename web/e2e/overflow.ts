import type { Page } from '@playwright/test'

/**
 * Aucun défilement horizontal de 320 à 2560 px (règle du projet) : les largeurs du balayage, points
 * de rupture vérifiés de part et d'autre (639/640, 767/768…).
 */
export const SWEEP_WIDTHS = [320, 360, 375, 390, 414, 440, 639, 640, 767, 768, 1023, 1024, 1279, 1280, 1440, 1920, 2560]

/** Largeur qui dépasse et éléments en cause (hors zones à défilement contenu : carrousel, tableau, anneau) */
export async function overflow(page: Page): Promise<{ sw: number; vw: number; culprits: string[] }> {
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
