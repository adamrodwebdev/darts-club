import { describe, expect, it } from 'vitest'
import { changeCents, saleCents, toCents } from '../CashRules'
import { EntranceScreen } from '../EveningEntrance'
import type { TokenKind } from '../EventAccounts'
import { SaleOutbox, type KeyValueStore, type PendingSale } from '../../services/SaleOutbox'

const TOKENS: TokenKind[] = [
  { name: 'Jeton vert', value: '1.00', bag: false, color: 'vert' },
  { name: 'Jeton jaune', value: '0.50', bag: false, color: 'jaune' },
  { name: 'Sachet', value: '10.00', bag: true, color: null },
]

describe('cash rules', () => {
  it('adds a sale up in whole cents', () => {
    expect(toCents('0.10') + toCents('0.20')).toBe(30)
    expect(saleCents(TOKENS, { Sachet: 2, 'Jeton jaune': 3 })).toBe(2150)
    expect(saleCents(TOKENS, {})).toBe(0)
  })

  it('gives the change, or nothing when the note is too small', () => {
    expect(changeCents(5000, 2150)).toBe(2850)
    expect(changeCents(2000, 2150)).toBeNull()
  })
})

describe('EntranceScreen', () => {
  it('parses the entrance the API computed', () => {
    const screen = EntranceScreen.parse({
      event: { id: 'e1', title: 'Soirée fléchettes', startsAt: '2026-11-21T19:00:00+00:00' },
      tokensEnabled: true,
      tokens: [{ name: 'Jeton vert', value: '1.00', bag: false, color: 'vert' }],
      cashFloat: '100.00',
      sales: [{ id: 's1', lines: [{ name: 'Sachet', value: '10.00', bag: true, color: null, count: 2 }], total: '20.00', method: 'carte', soldAt: '2026-11-21T20:05:00+00:00', soldBy: 'Nadia', cancelled: false, cancelledAt: null, cancelledBy: null }],
      taken: { cash: '0.00', card: '20.00' },
    })
    expect(screen.tokens[0]?.color).toBe('vert')
    expect(screen.sales[0]).toMatchObject({ method: 'carte', total: '20.00', cancelled: false })
    expect(screen.taken.card).toBe('20.00')
  })
})

/** Stockage en mémoire, comme localStorage */
function memoryStore(initial: string | null = null): KeyValueStore & { value: string | null } {
  return {
    value: initial,
    getItem(): string | null {
      return this.value
    },
    setItem(_key: string, value: string): void {
      this.value = value
    },
  }
}

const pending = (id: string, eventId = 'e1'): PendingSale => ({ eventId, total: '10.00', sale: { id, method: 'especes', lines: [{ name: 'Sachet', count: 1 }] } })

describe('SaleOutbox', () => {
  it('keeps sales per evening, once each, until they are sent', () => {
    const outbox = new SaleOutbox(memoryStore())
    outbox.add(pending('s1'))
    outbox.add(pending('s1'))
    outbox.add(pending('s2', 'e2'))
    expect(outbox.list('e1').map((p) => p.sale.id)).toEqual(['s1'])
    outbox.remove('s1')
    expect(outbox.list('e1')).toEqual([])
    expect(outbox.list('e2')).toHaveLength(1)
  })

  it('survives a damaged or missing store', () => {
    expect(new SaleOutbox(memoryStore('{not json')).list('e1')).toEqual([])
    expect(new SaleOutbox(memoryStore('[{"eventId":"e1"}]')).list('e1')).toEqual([])
    const blind = new SaleOutbox(null)
    blind.add(pending('s1'))
    expect(blind.list('e1')).toEqual([])
  })
})
