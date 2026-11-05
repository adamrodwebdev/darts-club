/**
 * Budget de performance : poids compressé (gzip) de ce que charge la
 * première page (point d'entrée, modules et CSS du manifeste de démarrage
 * de index.html), et du plus gros module chargé à la demande.  Usage : npm run budget
 */
import { readdir, readFile } from 'node:fs/promises'
import { gzipSync } from 'node:zlib'
import { fileURLToPath } from 'node:url'

const BUDGETS = { initialJs: 150, initialCss: 30, largestLazyChunk: 100 } // Ko gzip

const dist = fileURLToPath(new URL('../dist/', import.meta.url))
const html = await readFile(`${dist}index.html`, 'utf8')
const gz = async (file) => gzipSync(await readFile(`${dist}${file}`)).length / 1024

// Fichiers de la première page : le manifeste que lit l'écran titre (build/deferredBoot.ts). La page
// n'a plus de <script src> ni de <link href> vers eux : les y chercher mesurait 0 Ko.
const manifest = /<script type="application\/json" id="boot-manifest">([^<]+)<\/script>/.exec(html)?.[1]
if (!manifest) {
  console.error('index.html : manifeste de démarrage (boot-manifest) introuvable.')
  process.exit(1)
}
const { entry, modules, styles } = JSON.parse(manifest)
const fromRoot = (path) => path.replace(/^\//, '')
const initialJs = [entry, ...modules].map(fromRoot)
const initialCss = styles.map(fromRoot)

const sum = async (files) => (await Promise.all(files.map(gz))).reduce((a, b) => a + b, 0)
const allJs = (await readdir(`${dist}assets`)).filter((f) => f.endsWith('.js')).map((f) => `assets/${f}`)
const lazy = allJs.filter((f) => !initialJs.includes(f))
const lazySizes = await Promise.all(lazy.map(async (f) => [f, await gz(f)]))
const [largestName, largest] = lazySizes.sort((a, b) => b[1] - a[1])[0] ?? ['—', 0]

const results = [
  ['JS initial', await sum(initialJs), BUDGETS.initialJs],
  ['CSS initial', await sum(initialCss), BUDGETS.initialCss],
  [`Plus gros module à la demande (${largestName})`, largest, BUDGETS.largestLazyChunk],
]

let failed = false
for (const [label, size, budget] of results) {
  const ok = size <= budget
  failed ||= !ok
  console.log(`${ok ? '✓' : '✗'} ${label} : ${size.toFixed(1)} Ko gzip (budget ${budget} Ko)`)
}

if (failed) {
  console.error('Budget de performance dépassé.')
  process.exit(1)
}
