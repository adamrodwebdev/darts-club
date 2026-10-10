import { test, expect } from '@playwright/test'

const WIDTHS = [320, 390, 768, 1024, 1440, 1920]
const PAGES = ['/', '/en', '/connexion', '/inscription']

for (const path of PAGES) {
  for (const width of WIDTHS) {
    test(`no horizontal scroll on ${path} at ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 })
      await page.goto(path)

      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      )
      expect(overflow).toBeLessThanOrEqual(0)
    })
  }
}
