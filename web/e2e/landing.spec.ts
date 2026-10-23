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

test('the home page is a guitar neck: strings to strum down, cards to slide along', async ({ page }) => {
  await page.route('**/api/public/events', (route) => route.fulfill({ json: { items: [], past: [] } }))
  await page.goto('/')

  await expect(page.getByText('Gratte ↓ pour passer d’une corde à l’autre, glisse ← → sur chacune.')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'L’esprit du club' })).toBeAttached()
  const pillars = page.getByRole('list', { name: 'Ce que propose le club', exact: true })
  await expect(pillars.getByRole('listitem')).toHaveCount(3)

  // Les onze parties de l'hymne tiennent sur une seule corde, parcourue avec les flèches
  const parts = page.getByRole('list', { name: 'Parties de l’hymne', exact: true })
  await expect(parts.getByRole('listitem')).toHaveCount(11)
  const anthem = page.locator('#hymne')
  await expect(anthem.getByText('1 / 11')).toBeVisible()
  // Toute la bande à l'écran, cartes et flèches, une fois la page posée (Playwright attend qu'elle ne bouge plus)
  await parts.locator('xpath=..').scrollIntoViewIfNeeded()
  await anthem.getByRole('button', { name: 'Carte suivante' }).click()
  await expect(anthem.getByText('2 / 11')).toBeVisible()
  await expect(parts.getByRole('heading', { name: 'Couplet 1' })).toBeInViewport()
})

test('a rock hand greets whoever reaches the bottom of the page', async ({ page }) => {
  await page.goto('/')
  const hand = page.locator('.rock-hand')
  await expect(hand).toHaveClass(/rock-hand--waiting/)
  // Défilement par script : la molette n'existe pas sur WebKit mobile
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
  await expect(hand).toHaveClass(/rock-hand--rocking/)
})

test('the page scrolls freely: no section pulls it back', async ({ page }) => {
  await page.goto('/')
  // Aucun accrochage vertical : seules les bandes horizontales en ont
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollSnapType)).toBe('none')
  const snapping = await page.locator('section, footer').evaluateAll((elements) => elements.filter((element) => getComputedStyle(element).scrollSnapAlign !== 'none').length)
  expect(snapping).toBe(0)

  // Pas à pas vers le bas : chaque pas arrive où il doit, rien ne ramène la page en arrière
  for (let step = 1; step <= 10; step++) {
    await page.evaluate(() => window.scrollBy(0, 120))
    await expect.poll(() => page.evaluate(() => Math.round(window.scrollY))).toBe(step * 120)
  }
})

test('without animations the rock hand simply stays there', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/')
  await expect(page.locator('.rock-hand')).toHaveClass(/rock-hand--still/)
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
