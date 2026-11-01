import { describe, expect, it } from 'vitest'
import { EventAccounts } from '../EventAccounts'
import { InvalidResponseError } from '../guards'

const json = {
  event: { id: 'e1', title: 'Soirée fléchettes', startsAt: '2026-11-14T19:00:00+00:00' },
  closed: false,
  closedAt: null,
  closedBy: null,
  flags: { temporaryBar: true, taxExempt: false },
  items: [
    {
      id: 'i1',
      position: 1,
      label: 'Bière pression',
      station: 'bar',
      unit: 'litre',
      garnishes: [],
      alcoholic: true,
      container: { name: 'Fût', size: 3000 },
      planned: 2,
      bought: 2,
      returned: 0,
      purchasePrice: '95.00',
      formats: [
        { name: 'Verre', volume: 25, price: '2.00', sold: 124 },
        { name: 'Pichet', volume: 150, price: '10.00', sold: 9 },
      ],
      stock: 6000,
      served: 4450,
      soldCount: 133,
      left: 1550,
      cost: '190.00',
      income: '338.00',
    },
  ],
  cash: { tokensEnabled: true, tokens: [{ name: 'Jeton vert', value: '1.00', bag: false }, { name: 'Sachet', value: '10.00', bag: true }], cashFloat: '100.00' },
  teams: {
    accueil: [],
    bar: [{ assignmentId: 't1', assignedAt: '2026-11-10T10:00:00+00:00', id: 'u1', displayName: 'Julie', avatarUrl: null, badges: [] }],
    cuisine: [],
  },
  entries: [
    { id: 'n1', position: 1, kind: 'depense', label: 'Salle', planned: '80.00', actual: null },
    { id: 'n2', position: 2, kind: 'recette', label: 'Dons', planned: '0.00', actual: '20.00' },
  ],
  receipts: [{ id: 'r1', label: 'Avoir', amount: '1.90', credit: true, type: 'pdf', uploadedBy: null, uploadedAt: '2026-11-15T10:00:00+00:00' }],
  totals: { barCosts: '9.50', barIncome: '27.00', costs: '9.50', income: '47.00', result: '37.50', plannedCosts: '89.50', plannedIncome: '30.00', plannedResult: '-59.50' },
  counters: { year: 2026, temporaryBars: { used: 1, limit: 5 }, taxExempt: { used: 0, limit: 6 } },
  permissions: { edit: true, editMenu: true, reopen: false },
}

describe('EventAccounts', () => {
  it('parses the accounts the API computed', () => {
    const accounts = EventAccounts.parse(json)
    expect(accounts.items[0]).toMatchObject({ station: 'bar', unit: 'litre', container: { name: 'Fût', size: 3000 }, served: 4450, left: 1550 })
    expect(accounts.items[0]?.formats.map((f) => [f.name, f.volume])).toEqual([
      ['Verre', 25],
      ['Pichet', 150],
    ])
    expect(accounts.cash.tokens.filter((token) => token.bag).map((token) => token.name)).toEqual(['Sachet'])
    expect(accounts.teams.bar.map((member) => [member.displayName, member.assignmentId])).toEqual([['Julie', 't1']])
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

  it('lets the whole board prepare the menu while the accounts stay with the secretary and the treasurer', () => {
    const presidency = EventAccounts.parse({ ...json, permissions: { edit: false, editMenu: true, reopen: false } })
    expect([presidency.editable, presidency.menuEditable]).toEqual([false, true])
    expect(EventAccounts.parse({ ...json, closed: true, closedAt: '2026-11-16T10:00:00+00:00' }).menuEditable).toBe(false)
  })

  it('reads an older answer without tokens, teams or menu rights', () => {
    const { cash: _cash, teams: _teams, ...older } = json
    const accounts = EventAccounts.parse({ ...older, permissions: { edit: true, reopen: false } })
    expect(accounts.cash).toEqual({ tokensEnabled: false, tokens: [], cashFloat: '0.00' })
    expect(accounts.teams).toEqual({ accueil: [], bar: [], cuisine: [] })
    expect(accounts.mayEditMenu).toBe(false)
  })

  it('flags a loss and rejects malformed answers', () => {
    expect(EventAccounts.parse({ ...json, totals: { ...json.totals, result: '-12.40' } }).isLoss).toBe(true)
    expect(() => EventAccounts.parse({ ...json, totals: { ...json.totals, costs: 9.5 } })).toThrow(InvalidResponseError)
  })
})
