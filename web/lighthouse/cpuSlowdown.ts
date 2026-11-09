// Ralentissement du processeur de la mesure Lighthouse en CI, calé sur deux horloges de la machine tirée :
// l'indice de Lighthouse, un calcul JavaScript, et le travail propre du navigateur sur la page sonde
// (probe.html : arrivée du document, création du contexte JavaScript, lecture du HTML, style, rendu).
// Une machine peut être rapide en JavaScript et lente pour le reste : calé sur le seul indice, le travail
// du navigateur y était ralenti au-delà du téléphone visé. Le plus doux des deux ralentissements
// l'emporte, si bien que le téléphone simulé n'est plus lent que la cible pour aucun des deux travaux ;
// en contrepartie, sur une telle machine, le JavaScript est moins ralenti que sur la cible, de l'écart
// entre les deux horloges.
// Règles pures, sans lecture de fichier : calibrate.mjs lit les rapports et écrit le résultat.
import { PROBE_PATH } from './probe.ts'

/** Ralentissement de Lighthouse sur mobile, celui de la machine de référence */
export const REFERENCE_SLOWDOWN = 4
/** Horloge JavaScript : 4× sur une machine d'indice 2450, la plus lente vue (2 novembre 2026) */
export const REFERENCE_INDEX = 2450
/**
 * Horloge du navigateur : travail observé de la page sonde sur cette même machine de référence, en ms
 * (diagnostics.totalTaskTime, avant tout ralentissement). Première estimation, à affiner avec les
 * journaux de la CI : mesurée le 9 novembre 2026 (Lighthouse 12.6.1) sur un portable supposé équilibré,
 * aussi rapide en JavaScript que pour le reste, soit sonde × indice ÷ 2450 avec les médianes de trois
 * passages au calme : 24,5 ms × 3364 ÷ 2450. Ce portable partagé est bruyant (28 ms sur une autre
 * série) : la valeur juste est la sonde d'une machine d'indice 2450 en CI, la référence de l'indice.
 */
export const PROBE_REFERENCE_MS = 33.6
/** Jamais plus rapide que la machine elle-même */
export const MIN_SLOWDOWN = 1

interface Range {
  readonly min: number
  readonly max: number
}

/** Bornes des valeurs lues dans un rapport : au-delà, la valeur est refusée */
const BENCHMARK_INDEX: Range = { min: 1, max: 100_000 }
const TASK_TIME_MS: Range = { min: 1, max: 60_000 }
const URL_MAX_LENGTH = 2048

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** Champ propre d'un objet venu d'un fichier, à travers ses objets imbriqués : jamais un champ hérité */
function fieldAt(value: unknown, ...keys: readonly string[]): unknown {
  let current = value
  for (const key of keys) {
    if (!isRecord(current) || !Object.hasOwn(current, key)) return undefined
    current = current[key]
  }
  return current
}

function firstItem(value: unknown): unknown {
  if (!Array.isArray(value)) return undefined
  const items: readonly unknown[] = value
  return items[0]
}

/** Un nombre fini dans ses bornes, tel quel : un texte, un booléen ou un objet ne sont jamais convertis */
function finiteIn(value: unknown, range: Range): number | undefined {
  return typeof value === 'number' && Number.isFinite(value) && value >= range.min && value <= range.max ? value : undefined
}

/** Chemin d'une adresse lue dans un rapport : un texte de longueur bornée qui se lit comme une adresse */
function pathOf(value: unknown): string | undefined {
  if (typeof value !== 'string' || value.length > URL_MAX_LENGTH) return undefined
  try {
    return new URL(value).pathname
  } catch {
    return undefined
  }
}

/** Ce qu'un passage de préchauffage apporte au calage ; une valeur absente ou refusée reste vide */
export class WarmupPass {
  /** Passage sur la page sonde plutôt que sur le site */
  readonly probe: boolean
  /** environment.benchmarkIndex : vitesse de la machine en JavaScript */
  readonly benchmarkIndex: number | undefined
  /** diagnostics.totalTaskTime : travail observé du fil principal, avant ralentissement, en ms */
  readonly totalTaskTime: number | undefined

  private constructor(probe: boolean, benchmarkIndex: number | undefined, totalTaskTime: number | undefined) {
    this.probe = probe
    this.benchmarkIndex = benchmarkIndex
    this.totalTaskTime = totalTaskTime
  }

  /**
   * Un rapport Lighthouse (lhr-*.json) venu d'un fichier : seuls ses champs propres sont lus, et chaque
   * nombre doit en être un, fini et dans ses bornes. Sans adresse lisible, le passage ne se classe pas.
   */
  static parse(report: unknown): WarmupPass | undefined {
    const path = pathOf(fieldAt(report, 'requestedUrl'))
    if (path === undefined) return undefined
    // Un passage en erreur (page absente, rien de peint) ne dit rien du travail du navigateur
    const failed = fieldAt(report, 'runtimeError') !== undefined
    const diagnostics = firstItem(fieldAt(report, 'audits', 'diagnostics', 'details', 'items'))
    return new WarmupPass(
      path === PROBE_PATH,
      finiteIn(fieldAt(report, 'environment', 'benchmarkIndex'), BENCHMARK_INDEX),
      failed ? undefined : finiteIn(fieldAt(diagnostics, 'totalTaskTime'), TASK_TIME_MS),
    )
  }
}

/** Valeur du milieu, ou moyenne des deux du milieu pour un nombre pair ; l'ordre reçu ne change pas */
export function median(values: readonly number[]): number | undefined {
  const sorted = values.toSorted((a, b) => a - b)
  const middle = sorted.length / 2
  const upper = sorted.at(Math.floor(middle))
  if (upper === undefined || !Number.isInteger(middle)) return upper
  return ((sorted.at(middle - 1) ?? upper) + upper) / 2
}

export interface Clock {
  /** Valeurs lues, une par passage, dans l'ordre des passages */
  readonly samples: readonly number[]
  /** Leur médiane, sur laquelle l'horloge se cale */
  readonly median: number
  /** Ralentissement que demande cette horloge */
  readonly slowdown: number
}

function clock(samples: readonly number[], slowdownFor: (middle: number) => number): Clock | undefined {
  const middle = median(samples)
  return middle === undefined ? undefined : { samples, median: middle, slowdown: slowdownFor(middle) }
}

export interface Calibration {
  readonly javascript: Clock | undefined
  readonly browser: Clock | undefined
  /** Le plus doux des ralentissements connus, jamais sous 1× ; sans aucune horloge, celui de Lighthouse (4×) */
  readonly slowdown: number
  /**
   * Référence de la sonde qui accorderait les deux horloges sur cette machine (sonde × indice ÷ 2450) :
   * relevée sur plusieurs CI, elle affine PROBE_REFERENCE_MS
   */
  readonly matchingProbeReference: number | undefined
}

export function calibrate(passes: readonly WarmupPass[]): Calibration {
  // Chaque horloge se cale sur la médiane de ses passages, comme sa référence (valeur habituelle de la
  // machine, pas son pic) : le plus rapide des passages calait 6 % plus sévère que le même téléphone
  // (6,87× au lieu de 6,5×). L'indice ne vient que des passages du site, comme la référence 2450.
  const javascript = clock(
    passes.flatMap((pass) => (pass.probe || pass.benchmarkIndex === undefined ? [] : [pass.benchmarkIndex])),
    (index) => (REFERENCE_SLOWDOWN * index) / REFERENCE_INDEX,
  )
  const browser = clock(
    passes.flatMap((pass) => (pass.probe && pass.totalTaskTime !== undefined ? [pass.totalTaskTime] : [])),
    (taskTime) => (REFERENCE_SLOWDOWN * PROBE_REFERENCE_MS) / taskTime,
  )
  const known = [javascript, browser].flatMap((each) => (each === undefined ? [] : [each.slowdown]))
  return {
    javascript,
    browser,
    slowdown: known.length === 0 ? REFERENCE_SLOWDOWN : Math.max(MIN_SLOWDOWN, Math.min(...known)),
    matchingProbeReference:
      javascript === undefined || browser === undefined ? undefined : (browser.median * javascript.median) / REFERENCE_INDEX,
  }
}
