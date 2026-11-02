import { describe, expect, it } from 'vitest'
import { STOCK_MEASURES } from '../StockMeasure'

describe('STOCK_MEASURES', () => {
  const litre = STOCK_MEASURES.litre

  it('reads sizes by the litre in centilitres, a bare number in the field unit', () => {
    expect(litre.parse('30 L', 'container')).toBe(3000)
    expect(litre.parse('30', 'container')).toBe(3000)
    expect(litre.parse('1,5 l', 'format')).toBe(150)
    expect(litre.parse('0.33 litre', 'format')).toBe(33)
    expect(litre.parse('25', 'format')).toBe(25)
    expect(litre.parse(' 25 cl ', 'format')).toBe(25)
    expect(litre.parse('500 cl', 'container')).toBe(500)
  })

  it('refuses what it cannot read or what the API would refuse', () => {
    expect(litre.parse('', 'container')).toBeNull()
    expect(litre.parse('25 ml', 'format')).toBeNull()
    expect(litre.parse('33,5 cl', 'format')).toBeNull()
    expect(litre.parse('2 000 L', 'container')).toBeNull()
    expect(litre.parse('1001 L', 'container')).toBeNull()
    expect(litre.parse('0 cl', 'format')).toBeNull()
  })

  it('shows litres from one litre up, centilitres below', () => {
    expect(litre.quantity(4450)).toEqual({ value: 44.5, unit: 'l' })
    expect(litre.quantity(25)).toEqual({ value: 25, unit: 'cl' })
    expect(STOCK_MEASURES.portion.quantity(120)).toEqual({ value: 120, unit: null })
  })

  it('counts units and portions in whole numbers', () => {
    expect(STOCK_MEASURES.unite.parse('24', 'container')).toBe(24)
    expect(STOCK_MEASURES.unite.parse('2,5', 'container')).toBeNull()
    expect(STOCK_MEASURES.portion.parse('0', 'container')).toBeNull()
  })
})
