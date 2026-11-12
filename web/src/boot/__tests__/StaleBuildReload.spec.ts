import { describe, expect, it } from 'vitest'
import type { Clock } from '@/time/Clock'
import {
  RELOAD_MEMORY_KEY,
  RELOAD_SPACING_MS,
  SessionReloadMemory,
  StaleBuildReload,
  type PageLoader,
  type ReloadMemory,
} from '../StaleBuildReload'

/** Fenêtre factice : la page affichée, et les adresses chargées comme nouveaux documents */
class FakePages implements PageLoader {
  readonly loaded: string[] = []

  constructor(private readonly shown: string) {}

  current(): string {
    return this.shown
  }

  load(path: string): void {
    this.loaded.push(path)
  }
}

/** Mémoire de l'onglet, partagée par les documents successifs comme sessionStorage */
class FakeMemory implements ReloadMemory {
  private at: number | null = null

  constructor(private readonly writable = true) {}

  lastReload(): number | null {
    return this.at
  }

  rememberReload(at: number): void {
    if (this.writable) this.at = at
  }
}

/** Horloge qu'un test avance à la main */
class SteppedClock implements Clock {
  private ms = Date.UTC(2026, 10, 4, 10)

  now(): Date {
    return new Date(this.ms)
  }

  advance(ms: number): void {
    this.ms += ms
  }
}

describe('StaleBuildReload', () => {
  it('loads the page a navigation was heading to when its code is gone', () => {
    const pages = new FakePages('/evenements')
    const reload = new StaleBuildReload(pages, new FakeMemory(), new SteppedClock())

    reload.navigating('/evenements/0b9e8f1c-6a4d-4f2e-9c1a-2d3e4f5a6b7c')
    reload.codeMissing()

    expect(pages.loaded).toEqual(['/evenements/0b9e8f1c-6a4d-4f2e-9c1a-2d3e4f5a6b7c'])
  })

  it('loads the page on screen again when code goes missing outside a navigation', () => {
    // Arrivée sur /compte, puis l'adresse a changé dans la page (ancre) : c'est elle qui compte
    const pages = new FakePages('/compte#securite')
    const reload = new StaleBuildReload(pages, new FakeMemory(), new SteppedClock())

    reload.navigating('/compte')
    reload.arrived()
    reload.codeMissing()

    expect(pages.loaded).toEqual(['/compte#securite'])
  })

  it('loads a new document at most once a minute, so a file that is really gone never loops', () => {
    const clock = new SteppedClock()
    const memory = new FakeMemory()
    const oldTab = new FakePages('/infos')
    new StaleBuildReload(oldTab, memory, clock).codeMissing()

    // Le document chargé à neuf ne trouve pas le fichier non plus : il ne recharge pas
    clock.advance(RELOAD_SPACING_MS - 1)
    const newDocument = new FakePages('/infos')
    new StaleBuildReload(newDocument, memory, clock).codeMissing()
    // Un prochain déploiement, plus tard, recharge de nouveau
    clock.advance(1)
    const later = new FakePages('/infos')
    new StaleBuildReload(later, memory, clock).codeMissing()

    expect([oldTab.loaded, newDocument.loaded, later.loaded]).toEqual([['/infos'], [], ['/infos']])
  })

  it('never reloads a tab whose storage cannot remember the reload', () => {
    const pages = new FakePages('/infos')
    const reload = new StaleBuildReload(pages, new FakeMemory(false), new SteppedClock())

    reload.codeMissing()

    expect(pages.loaded).toEqual([])
  })
})

describe('SessionReloadMemory', () => {
  it('reads back the instant it stored', () => {
    const values = new Map<string, string>()
    const memory = new SessionReloadMemory(() => ({
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    }))

    memory.rememberReload(1_793_786_400_000)

    expect([memory.lastReload(), values.get(RELOAD_MEMORY_KEY)]).toEqual([1_793_786_400_000, '1793786400000'])
  })

  it('reads a corrupted value as no reload at all', () => {
    const memory = new SessionReloadMemory(() => ({ getItem: () => 'hier', setItem: () => undefined }))

    expect(memory.lastReload()).toBeNull()
  })

  // Number() aurait lu « » comme 0 et « 0x10 » comme 16 : seul un entier écrit en chiffres compte
  it.each(['', ' ', '0x10', '1e3', '-5', '12.5', 'Infinity', '1793786400000abc'])(
    'reads the stored text %j as no reload at all',
    (stored) => {
      const memory = new SessionReloadMemory(() => ({ getItem: () => stored, setItem: () => undefined }))

      expect(memory.lastReload()).toBeNull()
    },
  )

  it('has no memory when the browser blocks storage', () => {
    const memory = new SessionReloadMemory(() => {
      throw new DOMException('Stockage refusé', 'SecurityError')
    })

    memory.rememberReload(1_793_786_400_000)

    expect(memory.lastReload()).toBeNull()
  })
})
