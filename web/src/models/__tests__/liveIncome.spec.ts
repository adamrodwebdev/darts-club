import { describe, expect, it } from 'vitest'
import { InvalidResponseError } from '../guards'
import { leftShare, salesSummary, servingsLeft } from '../IncomeRules'
import { LiveIncome, type ItemSales } from '../LiveIncome'

const beer: ItemSales = {
  id: 'i1', label: 'Bière pression', station: 'bar', unit: 'litre',
  formats: [{ name: 'Verre', volume: 25, count: 124, amount: '248.00' }, { name: 'Pichet', volume: 150, count: 9, amount: '90.00' }],
  amount: '338.00', served: 4450, stock: 6000, left: 1550,
}
const water: ItemSales = {
  id: 'i2', label: 'Eau', station: 'bar', unit: 'unite',
  formats: [{ name: 'Bouteille', volume: 1, count: 30, amount: '30.00' }],
  amount: '30.00', served: 30, stock: 48, left: 18,
}

const json = {
  event: { id: 'e1', title: 'Soirée fléchettes', startsAt: '2026-11-21T19:00:00+00:00' },
  tokensEnabled: true,
  entrance: {
    cash: '20.00', card: '6.00', total: '26.00', cashFloat: '50.00', expectedCash: '70.00',
    tokens: [{ name: 'Jeton vert', value: '1.00', bag: false, color: 'vert', count: 5, amount: '5.00' }],
  },
  bar: { amount: '14.00', served: 3 },
  kitchen: { amount: '3.00', served: 1 },
  spent: '17.00',
  circulation: '9.00',
  orders: { count: 3, waiting: 1, ready: 0, cancelled: 1 },
  items: [
    { ...beer },
    { id: 'i3', label: 'Hot dog', station: 'cuisine', unit: 'portion', formats: [{ name: 'Portion', volume: 1, count: 1, amount: '3.00' }], amount: '3.00', served: 1, stock: 2, left: 1 },
    { id: 'i9', label: 'Crêpe', station: 'cuisine', unit: null, formats: [{ name: 'Sucre', volume: null, count: 2, amount: '4.00' }], amount: '4.00', served: null, stock: null, left: null },
    { ...water },
  ],
}

describe('live income', () => {
  it('reads the figures the API worked out, items in the menu order of each station', () => {
    const income = LiveIncome.parse(json)
    expect(income.entrance.tokens).toEqual([{ name: 'Jeton vert', value: '1.00', bag: false, color: 'vert', count: 5, amount: '5.00' }])
    expect([income.spent, income.circulation, income.orders.cancelled]).toEqual(['17.00', '9.00', 1])
    expect(income.itemsAt('bar').map((item) => item.label)).toEqual(['Bière pression', 'Eau'])
    expect(income.itemsAt('cuisine').map((item) => item.label)).toEqual(['Hot dog', 'Crêpe'])
    // Article retiré de la carte après ses ventes : ni unité ni stock
    expect(income.items[2]).toMatchObject({ unit: null, stock: null, left: null, served: null })
  })

  it('refuses an answer it cannot read', () => {
    expect(() => LiveIncome.parse({ ...json, entrance: null })).toThrow(InvalidResponseError)
    expect(() => LiveIncome.parse({ ...json, orders: { count: 'beaucoup' } })).toThrow(InvalidResponseError)
  })

  it('sums a station up in a few words: an item with one format goes by its own name', () => {
    expect(salesSummary([beer, water])).toEqual([
      { count: 124, name: 'Verre' },
      { count: 9, name: 'Pichet' },
      { count: 30, name: 'Eau' },
    ])
    expect(salesSummary([{ ...water, formats: [{ name: 'Bouteille', volume: 1, count: 0, amount: '0.00' }] }])).toEqual([])
  })

  it('tells what is left as a share of the stock and in glasses', () => {
    expect(leftShare(beer)).toBeCloseTo(1550 / 6000)
    expect(servingsLeft(beer)).toEqual({ count: 62, name: 'Verre' })
    expect(leftShare({ ...beer, left: -50 })).toBe(0)
    expect(servingsLeft({ ...beer, left: -50 })).toEqual({ count: 0, name: 'Verre' })
    expect(leftShare({ ...beer, left: null })).toBeNull()
    expect(leftShare({ ...beer, stock: 0 })).toBeNull()
    expect(servingsLeft({ ...beer, formats: [{ name: 'Verre', volume: null, count: 1, amount: '2.00' }] })).toBeNull()
  })
})
