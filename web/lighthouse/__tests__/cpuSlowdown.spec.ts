import { describe, expect, it } from 'vitest'
import { calibrate, median, PROBE_REFERENCE_MS, REFERENCE_INDEX, REFERENCE_SLOWDOWN, WarmupPass } from '../cpuSlowdown'

const SITE = 'http://localhost:4173/'
const PROBE = 'http://127.0.0.1:8787/sonde'

/** Rapport Lighthouse réduit aux champs que lit le calage */
function report(url: unknown, benchmarkIndex: unknown, totalTaskTime: unknown): Record<string, unknown> {
  return {
    requestedUrl: url,
    environment: { benchmarkIndex },
    audits: { diagnostics: { details: { type: 'debugdata', items: [{ numTasks: 12, totalTaskTime }] } } },
  }
}

function pass(url: string, benchmarkIndex: unknown, totalTaskTime: unknown): WarmupPass {
  const read = WarmupPass.parse(report(url, benchmarkIndex, totalTaskTime))
  if (read === undefined) throw new Error(`Rapport sans adresse lisible : ${url}`)
  return read
}

/** Passage sur l'accueil du site : seul son indice compte */
const site = (benchmarkIndex: unknown) => pass(SITE, benchmarkIndex, 900)
/** Passage sur la page sonde : seul son travail observé compte */
const probe = (totalTaskTime: unknown) => pass(PROBE, 3000, totalTaskTime)

describe('median', () => {
  it('takes the middle value of an odd count, whatever the order of the passes', () => {
    expect(median([3310, 3260, 3280])).toBe(3280)
  })

  it('averages the two middle values of an even count', () => {
    expect(median([40, 10, 30, 20])).toBe(25)
  })

  it('has none without values', () => {
    expect(median([])).toBeUndefined()
  })

  it('leaves the samples in the order of the passes', () => {
    const samples = [33, 31, 32]
    median(samples)
    expect(samples).toEqual([33, 31, 32])
  })
})

describe('WarmupPass.parse', () => {
  it('reads the machine index and the observed task time of a pass', () => {
    expect(WarmupPass.parse(report(PROBE, 3280, 31.5))).toMatchObject({ probe: true, benchmarkIndex: 3280, totalTaskTime: 31.5 })
  })

  it('tells the probe passes from the site passes by the requested path alone', () => {
    expect(WarmupPass.parse(report(SITE, 3280, 900))?.probe).toBe(false)
    expect(WarmupPass.parse(report('http://localhost:4173/sonde', 3280, 31.5))?.probe).toBe(true)
    expect(WarmupPass.parse(report('http://127.0.0.1:8787/sonde/autre', 3280, 31.5))?.probe).toBe(false)
  })

  const host = 'http://127.0.0.1:8787/'
  const longestUrl = `${host}${'a'.repeat(2048 - host.length)}`

  it('accepts a requested URL up to 2048 characters', () => {
    expect(WarmupPass.parse(report(longestUrl, 3280, 31.5))).toBeDefined()
  })

  // Sans adresse lisible, le passage ne se classe ni du côté du site ni de celui de la sonde
  it.each<[string, unknown]>([
    ['missing', undefined],
    ['null', null],
    ['a number', 42],
    ['an array', [PROBE]],
    ['an object', { href: PROBE }],
    ['empty text', ''],
    ['text that is no URL', 'sonde'],
    ['one character over the limit', `${longestUrl}a`],
  ])('ignores a pass whose requested URL is %s', (_, url) => {
    expect(WarmupPass.parse(report(url, 3280, 31.5))).toBeUndefined()
  })

  it.each<[string, unknown]>([
    ['not a report', null],
    ['text', '{"requestedUrl":"http://127.0.0.1:8787/sonde"}'],
    ['a list', [report(PROBE, 3280, 31.5)]],
  ])('ignores %s', (_, value) => {
    expect(WarmupPass.parse(value)).toBeUndefined()
  })

  // Un nombre doit en être un, fini et dans ses bornes : rien n'est converti
  const notNumbers: readonly [string, unknown][] = [
    ['missing', undefined],
    ['null', null],
    ['true', true],
    ['numeric text', '3280'],
    ['text with a unit', '3280 ms'],
    ['an exponent in text', '3.28e3'],
    ['hexadecimal text', '0xCD0'],
    ['empty text', ''],
    ['an array', [3280]],
    ['an object with valueOf', { valueOf: () => 3280 }],
    ['NaN', Number.NaN],
    ['Infinity', Number.POSITIVE_INFINITY],
    ['minus Infinity', Number.NEGATIVE_INFINITY],
    ['zero', 0],
    ['minus zero', -0],
    ['a negative number', -3280],
  ]

  it.each<[string, unknown]>([...notNumbers, ['just under 1', 0.999], ['just over 100000', 100_000.001]])(
    'refuses a machine index that is %s',
    (_, benchmarkIndex) => {
      expect(WarmupPass.parse(report(SITE, benchmarkIndex, 900))?.benchmarkIndex).toBeUndefined()
    },
  )

  it.each<[string, unknown]>([...notNumbers, ['just under 1 ms', 0.999], ['just over 60000 ms', 60_000.001]])(
    'refuses an observed task time that is %s',
    (_, totalTaskTime) => {
      expect(WarmupPass.parse(report(PROBE, 3280, totalTaskTime))?.totalTaskTime).toBeUndefined()
    },
  )

  it('accepts the bounds themselves', () => {
    expect(WarmupPass.parse(report(PROBE, 1, 1))).toMatchObject({ benchmarkIndex: 1, totalTaskTime: 1 })
    expect(WarmupPass.parse(report(PROBE, 100_000, 60_000))).toMatchObject({ benchmarkIndex: 100_000, totalTaskTime: 60_000 })
  })

  it('reads only the fields of the report itself, never inherited ones', () => {
    const inherited: unknown = Object.assign(Object.create({ environment: { benchmarkIndex: 3280 } }), { requestedUrl: SITE })
    expect(WarmupPass.parse(inherited)?.benchmarkIndex).toBeUndefined()
    // « __proto__ » écrit dans le fichier reste un champ ordinaire, jamais le prototype
    const smuggled: unknown = JSON.parse(`{"requestedUrl":"${SITE}","environment":{"__proto__":{"benchmarkIndex":3280}}}`)
    expect(WarmupPass.parse(smuggled)?.benchmarkIndex).toBeUndefined()
  })

  it('reads the task time from the first item of a list of diagnostics only', () => {
    const items = (value: unknown) => ({ requestedUrl: PROBE, audits: { diagnostics: { details: { items: value } } } })
    expect(WarmupPass.parse(items([{ totalTaskTime: 31.5 }, { totalTaskTime: 99 }]))?.totalTaskTime).toBe(31.5)
    expect(WarmupPass.parse(items([]))?.totalTaskTime).toBeUndefined()
    expect(WarmupPass.parse(items({ 0: { totalTaskTime: 31.5 }, length: 1 }))?.totalTaskTime).toBeUndefined()
  })

  it('keeps no task time from a pass that failed, but keeps its machine index', () => {
    const failed = { ...report(PROBE, 3280, 31.5), runtimeError: { code: 'ERRORED_DOCUMENT_REQUEST' } }
    expect(WarmupPass.parse(failed)).toMatchObject({ probe: true, benchmarkIndex: 3280, totalTaskTime: undefined })
  })
})

describe('calibrate', () => {
  it('sets the JavaScript clock from the median machine index, as before the probe', () => {
    const calibration = calibrate([site(3310), site(3260), site(3280)])
    expect(calibration.javascript).toMatchObject({ samples: [3310, 3260, 3280], median: 3280 })
    expect(calibration.javascript?.slowdown).toBeCloseTo((REFERENCE_SLOWDOWN * 3280) / REFERENCE_INDEX)
  })

  it('reads the machine index from the site passes only', () => {
    const calibration = calibrate([site(3000), site(3000), site(3000), pass(PROBE, 9000, 30), pass(PROBE, 9000, 30)])
    expect(calibration.javascript?.median).toBe(3000)
  })

  it('sets the browser clock from the median task time of the probe passes', () => {
    const calibration = calibrate([probe(PROBE_REFERENCE_MS * 2), probe(PROBE_REFERENCE_MS * 2), probe(PROBE_REFERENCE_MS * 9)])
    expect(calibration.browser?.median).toBe(PROBE_REFERENCE_MS * 2)
    expect(calibration.browser?.slowdown).toBeCloseTo(REFERENCE_SLOWDOWN / 2)
  })

  it('reads the task time from the probe passes only', () => {
    const calibration = calibrate([pass(SITE, 3000, 900), pass(SITE, 3000, 900)])
    expect(calibration.browser).toBeUndefined()
  })

  // PR #128 : machine rapide en JavaScript (indice 4050, 6,6×), lente pour le travail du navigateur
  it('takes the browser clock when the browser is the slower of the two', () => {
    const calibration = calibrate([site(4050), site(4050), site(4050), probe(PROBE_REFERENCE_MS * 1.25), probe(PROBE_REFERENCE_MS * 1.25)])
    expect(calibration.slowdown).toBeCloseTo(REFERENCE_SLOWDOWN / 1.25)
  })

  it('takes the JavaScript clock when the JavaScript is the slower of the two', () => {
    const calibration = calibrate([site(REFERENCE_INDEX), probe(PROBE_REFERENCE_MS / 2), probe(PROBE_REFERENCE_MS / 2)])
    expect(calibration.slowdown).toBeCloseTo(REFERENCE_SLOWDOWN)
    expect(calibration.browser?.slowdown).toBeCloseTo(REFERENCE_SLOWDOWN * 2)
  })

  it('keeps the JavaScript clock alone when the probe measured nothing', () => {
    const calibration = calibrate([site(3280), site(3280), site(3280), probe(Number.NaN), probe(undefined), probe('31.5')])
    expect(calibration.browser).toBeUndefined()
    expect(calibration.slowdown).toBeCloseTo((REFERENCE_SLOWDOWN * 3280) / REFERENCE_INDEX)
  })

  it('keeps the browser clock alone when the machine index is unknown', () => {
    const calibration = calibrate([site(Number.NaN), site(undefined), probe(PROBE_REFERENCE_MS * 0.8)])
    expect(calibration.javascript).toBeUndefined()
    expect(calibration.slowdown).toBeCloseTo(REFERENCE_SLOWDOWN / 0.8)
  })

  it("falls back to Lighthouse's own 4× without any clock", () => {
    expect(calibrate([]).slowdown).toBe(REFERENCE_SLOWDOWN)
    expect(calibrate([site(Number.NaN), probe(Number.NaN)]).slowdown).toBe(REFERENCE_SLOWDOWN)
  })

  it('never simulates a phone faster than the machine itself', () => {
    expect(calibrate([site(REFERENCE_INDEX / 8)]).slowdown).toBe(1)
    expect(calibrate([site(REFERENCE_INDEX), probe(PROBE_REFERENCE_MS * 20)]).slowdown).toBe(1)
  })

  it('gives the probe reference that would make both clocks agree on this machine', () => {
    const calibration = calibrate([site(3280), probe(30)])
    expect(calibration.matchingProbeReference).toBeCloseTo((30 * 3280) / REFERENCE_INDEX)
    // Avec cette référence, la sonde demanderait le ralentissement de l'indice
    expect((REFERENCE_SLOWDOWN * (calibration.matchingProbeReference ?? 0)) / 30).toBeCloseTo(calibration.javascript?.slowdown ?? 0)
  })

  it('gives no matching probe reference while a clock is missing', () => {
    expect(calibrate([site(3280)]).matchingProbeReference).toBeUndefined()
    expect(calibrate([probe(30)]).matchingProbeReference).toBeUndefined()
  })
})
