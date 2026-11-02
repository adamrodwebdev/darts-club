import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTask } from '../nextTask'

describe('nextTask', () => {
  afterEach(() => {
    Reflect.deleteProperty(globalThis, 'scheduler')
  })

  it('resolves in a later task, after the microtasks of the current one', async () => {
    const order: string[] = []
    const later = nextTask().then(() => order.push('next task'))
    void Promise.resolve().then(() => order.push('microtask'))
    order.push('current task')

    await later
    expect(order).toEqual(['current task', 'microtask', 'next task'])
  })

  it('yields through the browser scheduler when it has one', async () => {
    const yieldToMain = vi.fn<() => Promise<void>>(() => Promise.resolve())
    Object.defineProperty(globalThis, 'scheduler', { configurable: true, value: { yield: yieldToMain } })

    await nextTask()
    expect(yieldToMain).toHaveBeenCalledOnce()
  })
})
