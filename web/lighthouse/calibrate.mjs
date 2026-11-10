// Ralentissement du processeur calé sur la machine de la CI : chaque mesure simule le même téléphone,
// quelle que soit la machine tirée (leur vitesse varie d'un quart d'une CI à l'autre, et un même code
// passait de 10 à 140 ms de blocage). Deux horloges, l'indice JavaScript de Lighthouse sur les passages
// du site et le travail du navigateur sur la page sonde ; la plus douce l'emporte (règles et références :
// cpuSlowdown.ts). Lit les passages de préchauffage et écrit LIGHTHOUSE_CPU_SLOWDOWN=… (pour $GITHUB_ENV) ;
// le détail des deux horloges va dans le journal, pour affiner leurs références.
import { closeSync, existsSync, fstatSync, openSync, readdirSync, readSync } from 'node:fs'
import { cpus } from 'node:os'
import { join } from 'node:path'
import { calibrate, MIN_SLOWDOWN, PROBE_REFERENCE_MS, WarmupPass } from './cpuSlowdown.ts'

const DIR = process.argv[2] ?? '.lighthouseci'
// Taille vérifiée avant la lecture : un rapport pèse moins de 2 Mo, un fichier bien plus gros n'en est pas un
const MAX_REPORT_BYTES = 32 * 1024 * 1024

/**
 * Contenu d'un rapport, ou rien s'il est trop gros ou n'est pas du JSON. Taille et lecture passent par
 * le même descripteur, et la lecture s'arrête à la taille vérifiée : le fichier ne peut pas changer
 * entre les deux (CodeQL js/file-system-race, 2026-11-09)
 */
function readReport(path) {
  let descriptor
  try {
    descriptor = openSync(path, 'r')
    const size = fstatSync(descriptor).size
    if (size > MAX_REPORT_BYTES) return undefined
    const buffer = Buffer.alloc(size)
    const read = readSync(descriptor, buffer, 0, size, 0)
    return JSON.parse(buffer.toString('utf8', 0, read))
  } catch {
    return undefined
  } finally {
    if (descriptor !== undefined) closeSync(descriptor)
  }
}

// Noms horodatés : l'ordre alphabétique est celui des passages
const passes = (existsSync(DIR) ? readdirSync(DIR) : [])
  .filter((name) => /^lhr-.*\.json$/.test(name))
  .sort()
  .flatMap((name) => WarmupPass.parse(readReport(join(DIR, name))) ?? [])
const { javascript, browser, slowdown, matchingProbeReference } = calibrate(passes)

const times = (value) => `${value.toFixed(2)}×`
// Le modèle de processeur dit quel type de machine a été tiré : les échecs ne s'y répartissent pas également
const processors = cpus()
const machine = processors.length ? `${processors[0].model.trim()}, ${processors.length} cœurs` : 'processeur inconnu'
const ms = (value) => `${value.toFixed(1)} ms`

console.error(
  javascript
    ? `Indice de la machine : ${javascript.samples.map(Math.round).join(', ')} → horloge JavaScript ${times(javascript.slowdown)}`
    : "Indice de la machine : inconnu → pas d'horloge JavaScript",
)
// La référence qui accorderait les deux horloges, relevée sur plusieurs CI, affine PROBE_REFERENCE_MS
const matching = matchingProbeReference === undefined ? '' : ` ; ${ms(matchingProbeReference)} accorderait les deux horloges ici`
console.error(
  browser
    ? `Sonde du navigateur : ${browser.samples.map((value) => value.toFixed(1)).join(', ')} ms → horloge du navigateur ${times(browser.slowdown)} (référence ${ms(PROBE_REFERENCE_MS)}${matching})`
    : "Sonde du navigateur : aucune mesure → pas d'horloge du navigateur",
)
const known = [javascript, browser].filter((each) => each !== undefined).map((each) => each.slowdown)
const reason =
  known.length === 0
    ? "celui de Lighthouse, faute d'horloge"
    : Math.min(...known) < MIN_SLOWDOWN
      ? `plancher de ${times(MIN_SLOWDOWN)}`
      : known.length === 2
        ? 'le plus doux des deux'
        : 'la seule horloge connue'
console.error(`Ralentissement : ${times(slowdown)} (${reason}) — ${machine}`)
console.log(`LIGHTHOUSE_CPU_SLOWDOWN=${slowdown.toFixed(2)}`)
