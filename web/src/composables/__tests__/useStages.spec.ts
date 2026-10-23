import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, type Ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useStages } from '../useStages'

function mountStages(total: number, all: boolean) {
  let shown: Ref<number> | undefined
  const wrapper = mount(
    defineComponent({
      setup() {
        shown = useStages(total, { all })
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
    const { shown } = mountStages(3, false)
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
    const { shown } = mountStages(3, true)
    expect(shown.value).toBe(3)
    expect(vi.getTimerCount()).toBe(0)
  })

  it('stops when the page goes away', () => {
    vi.useFakeTimers()
    const { wrapper, shown } = mountStages(3, false)
    vi.advanceTimersToNextTimer()
    wrapper.unmount()
    expect(vi.getTimerCount()).toBe(0)
    expect(shown.value).toBe(1)
  })
})
