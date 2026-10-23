import { afterEach, describe, expect, it, vi } from 'vitest'
import { afterNextFrame } from '../afterNextFrame'

describe('afterNextFrame', () => {
  afterEach(() => {
    vi.useRealTimers()
    Reflect.deleteProperty(document, 'visibilityState')
  })

  it('runs once the next frame is drawn, in a task of its own', () => {
    vi.useFakeTimers()
    const callback = vi.fn<() => void>()
    afterNextFrame(callback)

    vi.advanceTimersToNextFrame()
    expect(callback).not.toHaveBeenCalled()
    vi.advanceTimersToNextTimer()
    expect(callback).toHaveBeenCalledOnce()
  })

  it('does not wait for frames in a background tab, where they are paused', () => {
    vi.useFakeTimers()
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' })
    const callback = vi.fn<() => void>()
    afterNextFrame(callback)

    vi.advanceTimersToNextTimer()
    expect(callback).toHaveBeenCalledOnce()
  })

  it('can be called off, before or after the frame', () => {
    vi.useFakeTimers()
    const early = vi.fn<() => void>()
    const late = vi.fn<() => void>()
    afterNextFrame(early)()
    const cancelLate = afterNextFrame(late)
    vi.advanceTimersToNextFrame()
    cancelLate()

    vi.runAllTimers()
    expect(early).not.toHaveBeenCalled()
    expect(late).not.toHaveBeenCalled()
  })
})
