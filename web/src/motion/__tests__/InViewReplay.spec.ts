import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { InViewReplay, WELL_IN_VIEW } from '../InViewReplay'

/** IntersectionObserver de test : ses rappels sont déclenchés à la main */
class FakeObserver {
  static created: FakeObserver[] = []
  readonly targets: Element[] = []

  constructor(
    private readonly callback: IntersectionObserverCallback,
    readonly options?: IntersectionObserverInit,
  ) {
    FakeObserver.created.push(this)
  }

  observe(target: Element): void {
    this.targets.push(target)
  }

  disconnect(): void {
    this.targets.length = 0
  }

  report(entry: { isIntersecting: boolean; intersectionRatio?: number }): void {
    const full = { intersectionRatio: entry.isIntersecting ? 1 : 0, ...entry } as IntersectionObserverEntry
    this.callback([full], this as unknown as IntersectionObserver)
  }
}

type FakeAnimation = { playState: string; currentTime: number | null; timeline: unknown; pause: () => void; play: () => void; animationName?: string }

function animation(kind: 'css' | 'transition' | 'scroll'): FakeAnimation {
  const fake: FakeAnimation = {
    playState: 'running',
    currentTime: 1200,
    timeline: kind === 'scroll' ? { axis: 'block' } : document.timeline,
    pause: vi.fn<() => void>(() => {
      fake.playState = 'paused'
    }),
    play: vi.fn<() => void>(() => {
      fake.playState = 'running'
    }),
  }
  if (kind !== 'transition') fake.animationName = 'rise'
  return fake
}

function animatedElement(animations: FakeAnimation[]): Element {
  const element = document.createElement('div')
  Object.assign(element, { getAnimations: () => animations })
  return element
}

/** Les deux observateurs créés : entrée bien à l'écran, puis sortie complète */
function observers(): { entering: FakeObserver; leaving: FakeObserver } {
  const [entering, leaving] = FakeObserver.created.slice(-2)
  return { entering: entering!, leaving: leaving! }
}

describe('InViewReplay', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    FakeObserver.created = []
    vi.stubGlobal('IntersectionObserver', FakeObserver)
    document.documentElement.dataset.motion = 'on'
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    delete document.documentElement.dataset.motion
  })

  it('holds the timed CSS animations at their start once the element has left the screen', () => {
    const css = animation('css')
    const transition = animation('transition')
    const scrolling = animation('scroll')
    new InViewReplay(animatedElement([css, transition, scrolling]))

    observers().leaving.report({ isIntersecting: false })
    vi.advanceTimersToNextFrame()

    expect(css.playState).toBe('paused')
    expect(css.currentTime).toBe(0)
    expect(transition.pause).not.toHaveBeenCalled()
    expect(scrolling.pause).not.toHaveBeenCalled()
  })

  it('plays them again from the start once the element is well in view', () => {
    const css = animation('css')
    new InViewReplay(animatedElement([css]))
    observers().leaving.report({ isIntersecting: false })
    vi.advanceTimersToNextFrame()

    observers().entering.report({ isIntersecting: true })

    expect(css.play).toHaveBeenCalledOnce()
    expect(css.playState).toBe('running')
  })

  it('counts as back in view only past the given share of the element', () => {
    const css = animation('css')
    new InViewReplay(animatedElement([css]), { threshold: 0.9 })
    observers().leaving.report({ isIntersecting: false })
    vi.advanceTimersToNextFrame()

    observers().entering.report({ isIntersecting: true, intersectionRatio: 0.5 })
    expect(css.play).not.toHaveBeenCalled()

    observers().entering.report({ isIntersecting: true, intersectionRatio: 0.95 })
    expect(css.play).toHaveBeenCalledOnce()
  })

  it('holds nothing if the element is back before the next frame', () => {
    const css = animation('css')
    new InViewReplay(animatedElement([css]))

    observers().leaving.report({ isIntersecting: false })
    observers().entering.report({ isIntersecting: true })
    vi.advanceTimersToNextFrame()

    expect(css.pause).not.toHaveBeenCalled()
    expect(css.playState).toBe('running')
  })

  it('waits well inside the screen by default, whatever the height of the element', () => {
    new InViewReplay(animatedElement([]))
    expect(observers().entering.options).toBe(WELL_IN_VIEW)
  })

  it('holds nothing when animations are off', () => {
    document.documentElement.dataset.motion = 'off'
    const css = animation('css')
    new InViewReplay(animatedElement([css]))

    observers().leaving.report({ isIntersecting: false })
    vi.advanceTimersToNextFrame()

    expect(css.pause).not.toHaveBeenCalled()
  })

  it('releases what it held when it stops watching', () => {
    const css = animation('css')
    const replay = new InViewReplay(animatedElement([css]))
    observers().leaving.report({ isIntersecting: false })
    vi.advanceTimersToNextFrame()

    replay.disconnect()

    expect(css.play).toHaveBeenCalledOnce()
    expect(observers().entering.targets).toEqual([])
    expect(observers().leaving.targets).toEqual([])
  })
})
