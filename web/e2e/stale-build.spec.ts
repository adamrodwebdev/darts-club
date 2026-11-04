import { expect, test, type APIResponse, type Page } from '@playwright/test'

/**
 * Onglet ouvert avant un déploiement. Seul le code construit passe par le chargeur de Vite qui
 * signale l'échec : ce test ne tourne que sur l'aperçu (CI=1, voir BUILT_ONLY dans
 * playwright.config.ts).
 *
 * L'ancienne version est simulée comme un vrai déploiement : son point d'entrée demande un fichier
 * de page que le serveur n'a plus, sous un autre nom que celui de la version en ligne. Avec le même
 * nom, WebKit ne redemande pas, au document suivant, un module raté par le chargeur de Vite : le
 * test ne prouverait rien de ce qui arrive en vrai.
 */
const OLD_ENTRY = '/assets/index-ancienne.js'
const GONE_PAGE_FILE = 'ForgotPasswordView-ancienne.js'

/** En-têtes de la vraie réponse, sans longueur ni compression : le corps change */
function headersFor(response: APIResponse): Record<string, string> {
  return Object.fromEntries(Object.entries(response.headers()).filter(([name]) => !['content-length', 'content-encoding'].includes(name)))
}

/**
 * Ancienne version de la page `path` : son HTML nomme un autre point d'entrée, copie du vrai où le
 * fichier de la page « mot de passe oublié » porte un nom retiré par le déploiement (404 du serveur,
 * sans cache). Toute autre page vient de la version en ligne.
 */
async function serveOldVersion(page: Page, path: string, onGoneFile: () => void): Promise<void> {
  let entry = ''
  await page.route(
    (url) => url.pathname === path,
    async (route) => {
      const response = await route.fetch()
      const html = await response.text()
      entry = /"entry":"([^"]+)"/.exec(html)?.[1] ?? ''
      await route.fulfill({ status: response.status(), headers: headersFor(response), body: html.replace(`"entry":"${entry}"`, `"entry":"${OLD_ENTRY}"`) })
    },
  )
  await page.route(`**${OLD_ENTRY}`, async (route) => {
    const response = await route.fetch({ url: new URL(entry, route.request().url()).href })
    const code = (await response.text()).replaceAll(/ForgotPasswordView-[\w-]+\.js/g, GONE_PAGE_FILE)
    await route.fulfill({ status: 200, headers: headersFor(response), body: code })
  })
  await page.route(`**/assets/${GONE_PAGE_FILE}`, (route) => {
    onGoneFile()
    return route.fulfill({ status: 404, headers: { 'cache-control': 'no-store' }, body: '' })
  })
}

test('a tab opened before a deploy loads the new version of the page it is asked for', async ({ page }) => {
  let goneFileRequests = 0
  await serveOldVersion(page, '/connexion', () => (goneFileRequests += 1))
  await page.goto('/connexion')
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Connexion')
  await page.evaluate(() => Object.assign(window, { documentBeforeDeploy: true }))

  await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click()

  await expect(page).toHaveURL(/\/mot-de-passe-oublie$/)
  await expect(page.getByRole('button', { name: 'Recevoir le lien' })).toBeVisible()
  expect(goneFileRequests, "l'ancien onglet a demandé le fichier retiré").toBeGreaterThan(0)
  expect(await page.evaluate(() => 'documentBeforeDeploy' in window), 'page chargée comme un nouveau document').toBe(false)
})
