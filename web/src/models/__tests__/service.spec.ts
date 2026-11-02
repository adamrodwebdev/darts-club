import { describe, expect, it } from 'vitest'
import { BarScreen, type ServiceItem } from '../EveningService'
import { servingsLeft, tokensFor, waitMinutes } from '../OrderRules'

const green = { name: 'Jeton vert', value: '1.00', color: 'vert' as const }
const yellow = { name: 'Jeton jaune', value: '0.50', color: 'jaune' as const }

describe('order rules', () => {
  it('asks for the biggest tokens first', () => {
    expect(tokensFor(2150, [yellow, green])).toEqual({ counts: [{ token: green, count: 21 }, { token: yellow, count: 1 }], rest: 0 })
    expect(tokensFor(320, [green, yellow])).toEqual({ counts: [{ token: green, count: 3 }], rest: 20 })
    expect(tokensFor(0, [green])).toEqual({ counts: [], rest: 0 })
  })

  it('counts whole minutes of waiting, never below zero', () => {
    const takenAt = new Date('2026-11-21T20:00:00Z')
    expect(waitMinutes(takenAt, new Date('2026-11-21T20:08:59Z'))).toBe(8)
    expect(waitMinutes(takenAt, new Date('2026-11-21T19:59:00Z'))).toBe(0)
  })

  it('tells how many glasses are left once the size is known', () => {
    const beer: ServiceItem = { id: 'i1', label: 'Bière', station: 'bar', unit: 'litre', garnishes: [], soldOut: false, markedSoldOut: false, left: 1550, formats: [{ name: 'Verre', volume: 25, price: '2.00' }] }
    expect(servingsLeft(beer)).toBe(62)
    expect(servingsLeft({ ...beer, left: null })).toBeNull()
  })
})

describe('BarScreen', () => {
  it('splits the menu between the bar and the kitchen', () => {
    const item = (id: string, station: string) => ({ id, label: id, station, unit: 'portion', garnishes: [{ name: 'Ketchup', out: true }], soldOut: false, markedSoldOut: false, left: 3, formats: [{ name: 'Portion', volume: 1, price: '3.00' }] })
    const screen = BarScreen.parse({
      event: { id: 'e1', title: 'Soirée', startsAt: '2026-11-21T19:00:00+00:00' },
      nextNumber: 4,
      tokensEnabled: true,
      tokens: [{ name: 'Jeton vert', value: '1.00', color: 'vert' }],
      menu: [item('beer', 'bar'), item('hotdog', 'cuisine')],
      orders: { waiting: [{ id: 'o1', number: 3, note: null, lines: [], total: '3.00', kitchen: 'en_attente', takenAt: '2026-11-21T20:00:00+00:00', takenBy: 'Marc', readyAt: null, deliveredAt: null, deliveredBy: null }], ready: [], delivered: [] },
    })
    expect(screen.drinks.map((i) => i.id)).toEqual(['beer'])
    expect(screen.food[0]?.garnishes[0]).toEqual({ name: 'Ketchup', out: true })
    expect(screen.orders.waiting[0]?.kitchen).toBe('en_attente')
  })
})
