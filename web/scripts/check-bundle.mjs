/**
 * Budget de performance : poids compressé (gzip) de ce que charge la
 * première page (point d'entrée, modules et CSS du manifeste de démarrage
 * de index.html), et du plus gros module chargé à la demande. Vérifie
 * aussi que chaque script commence par l'indication de compilation
 * (build/compileHints.ts).  Usage : npm run budget
 */
import { readdir, readFile } from 'node:fs/promises'
import { gzipSync } from 'node:zlib'
import { fileURLToPath } from 'node:url'
import { COMPILE_HINT } from '../build/compileHints.ts'

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

const unhinted = []
for (const file of allJs) {
  if (!(await readFile(`${dist}${file}`, 'utf8')).startsWith(COMPILE_HINT)) unhinted.push(file)
}
failed ||= unhinted.length > 0
console.log(
  unhinted.length === 0
    ? `✓ Indication de compilation en tête des ${allJs.length} scripts`
    : `✗ Indication de compilation absente de ${unhinted.length} scripts sur ${allJs.length} : ${unhinted.slice(0, 5).join(', ')}`,
)

if (failed) {
  console.error('Build refusé : voir les lignes ✗ ci-dessus.')
  process.exit(1)
}
