import { afterEach, describe, expect, it, vi } from 'vitest'
import { dismissTitleCard } from '../titleCard'

function addTitleCard(): HTMLElement {
  const card = document.createElement('div')
  card.id = 'title-card'
  document.body.append(card)
  return card
}

describe('dismissTitleCard', () => {
  afterEach(() => {
    document.getElementById('title-card')?.remove()
    delete document.documentElement.dataset.motion
    vi.useRealTimers()
  })

  it('fades the title card out, then removes it', () => {
    document.documentElement.dataset.motion = 'on'
    const card = addTitleCard()

    dismissTitleCard(document)
    expect(card.classList.contains('title-card--leaving')).toBe(true)
    expect(card.isConnected).toBe(true)

    card.dispatchEvent(new Event('transitionend'))
    expect(card.isConnected).toBe(false)
  })

  it('removes it at once when animations are off', () => {
    document.documentElement.dataset.motion = 'off'
    const card = addTitleCard()

    dismissTitleCard(document)
    expect(card.isConnected).toBe(false)
  })

  it('removes it even when the fade never plays (background tab)', () => {
    vi.useFakeTimers()
    document.documentElement.dataset.motion = 'on'
    const card = addTitleCard()

    dismissTitleCard(document)
    vi.advanceTimersByTime(600)
    expect(card.isConnected).toBe(false)
  })

  it('does nothing on a page without title card (tests, later navigations)', () => {
    expect(() => dismissTitleCard(document)).not.toThrow()
  })
})
