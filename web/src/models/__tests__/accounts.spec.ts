import { describe, expect, it } from 'vitest'
import { EventAccounts } from '../EventAccounts'
import { InvalidResponseError } from '../guards'

const json = {
  event: { id: 'e1', title: 'Soirée fléchettes', startsAt: '2026-11-14T19:00:00+00:00' },
  closed: false,
  closedAt: null,
  closedBy: null,
  flags: { temporaryBar: true, taxExempt: false },
  items: [{ id: 'i1', position: 1, label: 'Bière', planned: 10, bought: 12, returned: 2, sold: 9, left: 1, purchasePrice: '0.95', salePrice: '3.00', cost: '9.50', income: '27.00' }],
  entries: [
    { id: 'n1', position: 1, kind: 'depense', label: 'Salle', planned: '80.00', actual: null },
    { id: 'n2', position: 2, kind: 'recette', label: 'Dons', planned: '0.00', actual: '20.00' },
  ],
  receipts: [{ id: 'r1', label: 'Avoir', amount: '1.90', credit: true, type: 'pdf', uploadedBy: null, uploadedAt: '2026-11-15T10:00:00+00:00' }],
  totals: { barCosts: '9.50', barIncome: '27.00', costs: '9.50', income: '47.00', result: '37.50', plannedCosts: '89.50', plannedIncome: '30.00', plannedResult: '-59.50' },
  counters: { year: 2026, temporaryBars: { used: 1, limit: 5 }, taxExempt: { used: 0, limit: 6 } },
  permissions: { edit: true, reopen: false },
}

describe('EventAccounts', () => {
  it('parses the accounts the API computed', () => {
    const accounts = EventAccounts.parse(json)
    expect(accounts.items[0]?.left).toBe(1)
    expect(accounts.expenses.map((e) => e.label)).toEqual(['Salle'])
    expect(accounts.incomes.map((e) => e.actual)).toEqual(['20.00'])
    expect(accounts.receipts[0]).toMatchObject({ credit: true, type: 'pdf' })
    expect(accounts.temporaryBars).toEqual({ used: 1, limit: 5 })
    expect(accounts.isLoss).toBe(false)
  })

  it('is editable only while open and allowed', () => {
    expect(EventAccounts.parse(json).editable).toBe(true)
    expect(EventAccounts.parse({ ...json, closed: true, closedAt: '2026-11-16T10:00:00+00:00' }).editable).toBe(false)
    expect(EventAccounts.parse({ ...json, permissions: { edit: false, reopen: false } }).editable).toBe(false)
  })

  it('flags a loss and rejects malformed answers', () => {
    expect(EventAccounts.parse({ ...json, totals: { ...json.totals, result: '-12.40' } }).isLoss).toBe(true)
    expect(() => EventAccounts.parse({ ...json, totals: { ...json.totals, costs: 9.5 } })).toThrow(InvalidResponseError)
  })
})
