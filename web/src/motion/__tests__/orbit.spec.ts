import { describe, expect, it } from 'vitest'
import { frontCard, orbitSlots, orbitStep, settleCard, turnTowards, wrapIndex } from '../orbit'

describe('orbit rules', () => {
  it('spreads the cards evenly around the ring, at alternating heights', () => {
    const slots = orbitSlots(5)

    expect(slots.map((slot) => slot.angle)).toEqual([0, 72, 144, 216, 288])
    expect(new Set(slots.map((slot) => slot.lift)).size).toBe(5)
    expect(orbitSlots(0)).toEqual([])
    expect(orbitStep(4)).toBe(90)
  })

  it('names the card nearest the front for any turn, however many times the ring went round', () => {
    expect(frontCard(0, 5)).toBe(0)
    expect(frontCard(-72, 5)).toBe(1)
    expect(frontCard(-100, 5)).toBe(1)
    expect(frontCard(-110, 5)).toBe(2)
    expect(frontCard(72, 5)).toBe(4)
    expect(frontCard(-720 - 144, 5)).toBe(2)
    expect(frontCard(30, 0)).toBe(0)
  })

  it('turns the shortest way to a card, past the last one onto the first', () => {
    // De la dernière (−288°) à la première : un pas de plus dans le même sens, pas quatre en arrière
    expect(turnTowards(0, 5, -288)).toBe(-360)
    expect(turnTowards(4, 5, 0)).toBe(72)
    expect(turnTowards(2, 5, -720)).toBe(-864)
    expect(frontCard(turnTowards(3, 6, 1234), 6)).toBe(3)
  })

  it('wraps an index around the series', () => {
    expect(wrapIndex(5, 5)).toBe(0)
    expect(wrapIndex(-1, 5)).toBe(4)
    expect(wrapIndex(3, 0)).toBe(0)
  })

  it('settles a drag on the card it brought to the front, or the next one for a long enough swipe', () => {
    expect(settleCard({ start: 0, landed: 1, dx: -170, swiped: true, count: 5 })).toBe(1)
    // Un geste court revient à la carte de départ, un geste assez long passe à la voisine dans son sens
    expect(settleCard({ start: 0, landed: 0, dx: -20, swiped: false, count: 5 })).toBe(0)
    expect(settleCard({ start: 0, landed: 0, dx: -40, swiped: true, count: 5 })).toBe(1)
    expect(settleCard({ start: 0, landed: 0, dx: 40, swiped: true, count: 5 })).toBe(4)
  })
})
