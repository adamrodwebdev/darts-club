// Résumé de chaque passage Lighthouse (lhr-*.json) dans le journal de la CI : tous les passages,
// pas seulement le médian publié, pour voir d'un coup d'œil lequel a échoué et sur quelle mesure.
// Un passage qui bloque plus de 50 ms détaille ses tâches longues, ses scripts et le travail du fil
// principal ; l'indice de vitesse de la machine suit chaque passage. La cause se lit dans le journal.
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

const DIR = process.argv[2] ?? '.lighthouseci'
if (!existsSync(DIR)) process.exit(0)
const METRICS = ['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index']
const DETAIL_ABOVE_MS = 50

const ms = (value) => `${Math.round(value)} ms`
// L'origine est toujours celle de l'aperçu : le chemin suffit
const withoutOrigin = (url) => url.replace(/^https?:\/\/[^/]+/, '') || url
const items = (report, id) => report.audits[id]?.details?.items ?? []

// Noms horodatés : l'ordre alphabétique est celui des passages
for (const file of readdirSync(DIR).filter((name) => /^lhr-.*\.json$/.test(name)).sort()) {
  const report = JSON.parse(readFileSync(join(DIR, file), 'utf8'))
  const scores = Object.values(report.categories).map((category) => `${category.id} ${Math.round(category.score * 100)}`)
  const metrics = METRICS.map((id) => `${id} ${report.audits[id].displayValue}`)
  console.log(`${report.finalDisplayedUrl} | ${scores.join(', ')} | ${metrics.join(', ')} | machine ${Math.round(report.environment.benchmarkIndex)}`)
  if (report.audits['total-blocking-time'].numericValue <= DETAIL_ABOVE_MS) continue
  const tasks = items(report, 'long-tasks').map((task) => `${ms(task.duration)} à ${ms(task.startTime)} (${withoutOrigin(task.url)})`)
  const scripts = items(report, 'bootup-time').slice(0, 4).map((script) => `${withoutOrigin(script.url)} ${ms(script.total)} (exécution ${ms(script.scripting)})`)
  const work = items(report, 'mainthread-work-breakdown').map((group) => `${group.group} ${ms(group.duration)}`)
  console.log(`    tâches longues : ${tasks.join(', ') || 'aucune'}`)
  console.log(`    scripts : ${scripts.join(', ')}`)
  console.log(`    fil principal : ${work.join(', ')}`)
}
