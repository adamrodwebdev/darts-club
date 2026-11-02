// Ralentissement du processeur calé sur la machine de la CI : chaque mesure simule le même téléphone,
// quelle que soit la machine tirée (leur vitesse varie d'un quart d'une CI à l'autre, et un même code
// passait de 10 à 140 ms de blocage). Référence : 4× sur une machine d'indice 2450, la plus lente vue
// (2 novembre 2026). Lit les passages de préchauffage et écrit LIGHTHOUSE_CPU_SLOWDOWN=… (pour $GITHUB_ENV).
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

const DIR = process.argv[2] ?? '.lighthouseci'
const REFERENCE_INDEX = 2450
const REFERENCE_SLOWDOWN = 4

const indexes = existsSync(DIR)
  ? readdirSync(DIR)
      .filter((name) => /^lhr-.*\.json$/.test(name))
      .map((name) => JSON.parse(readFileSync(join(DIR, name), 'utf8')).environment?.benchmarkIndex)
      .filter((value) => Number.isFinite(value))
  : []
// Le passage le plus rapide (machine chaude) : le calage le plus strict, jamais plus indulgent
const index = Math.max(...indexes)
const slowdown = Number.isFinite(index) ? Math.max(1, (REFERENCE_SLOWDOWN * index) / REFERENCE_INDEX) : REFERENCE_SLOWDOWN
console.error(`Indice de la machine : ${indexes.map(Math.round).join(', ') || 'inconnu'} → ralentissement ${slowdown.toFixed(2)}×`)
console.log(`LIGHTHOUSE_CPU_SLOWDOWN=${slowdown.toFixed(2)}`)
