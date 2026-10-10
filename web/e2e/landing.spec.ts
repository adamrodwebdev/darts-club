import { test, expect } from '@playwright/test'

test('landing shows the anthem, influences and legal links', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Darts Club')
  await expect(page.getByRole('link', { name: 'Rejoindre le club' }).first()).toBeVisible()

  await page.getByRole('link', { name: /Écouter l’hymne/ }).click()
  await expect(page.getByRole('heading', { name: 'Triple Vingt' })).toBeInViewport()
  await expect(page.getByRole('button', { name: 'Écouter le riff' })).toBeVisible()

  const influences = page.getByRole('link', { name: /Russian Circles/ })
  await expect(influences).toHaveAttribute('rel', /noopener/)

  await page.getByRole('link', { name: 'Confidentialité' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Politique de confidentialité')
})
