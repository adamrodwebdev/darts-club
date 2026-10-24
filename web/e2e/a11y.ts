import AxeBuilder from '@axe-core/playwright'
import { expect, type Page } from '@playwright/test'

/** Audit WCAG 2.2 niveau AA (axe-core) des pages principales. */
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

export const THEMES = ['light', 'dark'] as const

export async function audit(page: Page): Promise<void> {
  // Laisse les animations d'apparition se terminer (dont le fondu d'arrivée de la page : en
  // plein fondu, un texte moins opaque n'a pas encore son contraste)
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.waitForFunction(() => document.getAnimations().every((animation) => animation.playState !== 'running'))
  const results = await new AxeBuilder({ page }).withTags(WCAG).analyze()
  const summary = results.violations.map((v) => `${v.id} (${v.impact}) : ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)
  expect(summary).toEqual([])
}

/** Audit de l'écran affiché dans les deux thèmes (le thème suit celui du système émulé) */
export async function auditBothThemes(page: Page): Promise<void> {
  for (const theme of THEMES) {
    await page.emulateMedia({ colorScheme: theme })
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
    await audit(page)
  }
}
