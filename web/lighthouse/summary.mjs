// Résumé de chaque passage Lighthouse (lhr-*.json) dans le journal de la CI : tous les passages,
// pas seulement le médian publié, pour voir d'un coup d'œil lequel a échoué et sur quelle mesure.
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

const DIR = process.argv[2] ?? '.lighthouseci'
if (!existsSync(DIR)) process.exit(0)
const METRICS = ['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index']

// Noms horodatés : l'ordre alphabétique est celui des passages
for (const file of readdirSync(DIR).filter((name) => /^lhr-.*\.json$/.test(name)).sort()) {
  const report = JSON.parse(readFileSync(join(DIR, file), 'utf8'))
  const scores = Object.values(report.categories).map((category) => `${category.id} ${Math.round(category.score * 100)}`)
  const metrics = METRICS.map((id) => `${id} ${report.audits[id].displayValue}`)
  console.log(`${report.finalDisplayedUrl} | ${scores.join(', ')} | ${metrics.join(', ')}`)
}
