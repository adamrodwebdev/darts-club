import { test, expect } from '@playwright/test'

test('landing shows the anthem, the friend band and legal links', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Skull Darts 71')
  await expect(page.getByRole('link', { name: 'Rejoindre le club' }).first()).toBeVisible()

  await page.getByRole('link', { name: /Écouter l’hymne/ }).click()
  await expect(page.getByRole('heading', { name: 'Triple Vingt' })).toBeInViewport()
  await expect(page.getByRole('button', { name: 'Écouter l’hymne' })).toBeVisible()
  // Rien n'est téléchargé avant la lecture
  await expect(page.locator('#hymne audio')).toHaveAttribute('preload', 'none')

  await expect(page.getByRole('heading', { name: 'Blind to Deaf' })).toBeAttached()
  await expect(page.getByRole('link', { name: /Leur page Facebook/ })).toHaveAttribute('rel', /noopener/)

  await page.getByRole('link', { name: 'Confidentialité' }).click()
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Politique de confidentialité')
})

test('the anthem can be played from any part of the song', async ({ page }) => {
  await page.goto('/')
  const audio = page.locator('#hymne audio')
  const chorus = page.getByRole('button', { name: 'Écouter « Refrain » à partir de 1:40' })

  await chorus.click()
  await expect.poll(() => audio.evaluate((element: HTMLAudioElement) => Math.round(element.currentTime))).toBeGreaterThanOrEqual(100)
  await expect(page.getByRole('listitem').filter({ has: chorus })).toHaveAttribute('aria-current', 'time')
  await expect(page.getByRole('slider', { name: 'Position dans l’hymne' })).toHaveAttribute('aria-valuetext', /^1:4\d sur 5:55$/)

  await page.getByRole('button', { name: 'Mettre en pause' }).click()
  await expect(page.getByRole('button', { name: 'Écouter l’hymne' })).toBeVisible()
})
