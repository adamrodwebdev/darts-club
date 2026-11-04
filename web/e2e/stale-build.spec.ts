import { expect, test } from '@playwright/test'

/**
 * Onglet ouvert avant un déploiement : les fichiers de code de l'ancienne version ont disparu du
 * serveur. Seul le code construit passe par le chargeur de Vite qui le signale : ce test ne tourne
 * que sur l'aperçu (CI=1, voir BUILT_ONLY dans playwright.config.ts).
 */
test('a tab opened before a deploy loads the new version of the page it is asked for', async ({ page }) => {
  await page.goto('/connexion')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Connexion')
  await page.evaluate(() => Object.assign(window, { documentBeforeDeploy: true }))

  // Le fichier de la page manque à l'ancien onglet (404, comme le serveur) ; le document chargé
  // ensuite vient de la nouvelle version, qui le trouve
  let newDocument = false
  page.on('request', (request) => {
    if (request.isNavigationRequest() && request.frame() === page.mainFrame()) newDocument = true
  })
  await page.route('**/assets/ForgotPasswordView-*.js', (route) =>
    newDocument ? route.continue() : route.fulfill({ status: 404, body: '' }),
  )

  await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click()

  await expect(page).toHaveURL(/\/mot-de-passe-oublie$/)
  await expect(page.getByRole('button', { name: 'Recevoir le lien' })).toBeVisible()
  expect(await page.evaluate(() => 'documentBeforeDeploy' in window)).toBe(false)
})
