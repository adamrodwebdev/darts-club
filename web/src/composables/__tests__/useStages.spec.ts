import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, type Ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useStages } from '../useStages'

function mountStages(total: number, options: { all: boolean; after?: Promise<unknown> }) {
  let shown: Ref<number> | undefined
  const wrapper = mount(
    defineComponent({
      setup() {
        shown = useStages(total, options)
        return () => null
      },
    }),
  )
  return { wrapper, shown: shown as Ref<number> }
}

describe('useStages', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('adds one stage per task once mounted', () => {
    vi.useFakeTimers()
    const { shown } = mountStages(3, { all: false })
    expect(shown.value).toBe(0)

    vi.advanceTimersToNextTimer()
    expect(shown.value).toBe(1)
    vi.advanceTimersToNextTimer()
    vi.advanceTimersToNextTimer()
    expect(shown.value).toBe(3)
    expect(vi.getTimerCount()).toBe(0)
  })

  it('shows everything at once when asked (link to an anchor)', () => {
    vi.useFakeTimers()
    const { shown } = mountStages(3, { all: true })
    expect(shown.value).toBe(3)
    expect(vi.getTimerCount()).toBe(0)
  })

  it('stops when the page goes away', () => {
    vi.useFakeTimers()
    const { wrapper, shown } = mountStages(3, { all: false })
    vi.advanceTimersToNextTimer()
    wrapper.unmount()
    expect(vi.getTimerCount()).toBe(0)
    expect(shown.value).toBe(1)
  })

  it('waits for the given moment before the first stage (the first page shown)', async () => {
    vi.useFakeTimers()
    let ready = (): void => undefined
    const { shown } = mountStages(1, { all: false, after: new Promise<void>((resolve) => (ready = resolve)) })
    expect(vi.getTimerCount()).toBe(0)

    ready()
    await Promise.resolve()
    vi.advanceTimersToNextTimer()
    expect(shown.value).toBe(1)
  })

  it('never starts once the page has gone away', async () => {
    vi.useFakeTimers()
    let ready = (): void => undefined
    const { wrapper, shown } = mountStages(1, { all: false, after: new Promise<void>((resolve) => (ready = resolve)) })
    wrapper.unmount()

    ready()
    await Promise.resolve()
    expect(vi.getTimerCount()).toBe(0)
    expect(shown.value).toBe(0)
  })
})
