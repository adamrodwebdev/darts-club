import { describe, expect, it } from 'vitest'
import { ConversationSummary } from '../Conversation'
import { InvalidResponseError } from '../guards'
import { Profile, initialsOf } from '../Profile'

describe('Profile', () => {
  const raw = {
    id: '1',
    email: 'a@example.com',
    displayName: 'Alice la Flèche',
    phone: null,
    avatarUrl: null,
    roles: ['ROLE_TREASURER', 'ROLE_MEMBER'],
    hasPassword: true,
    twoFactorEnabled: false,
    twoFactorSetupRequired: true,
  }

  it('parses the API payload', () => {
    const profile = Profile.parse(raw)
    expect(profile.initials).toBe('AL')
    expect(profile.isBoardMember).toBe(true)
  })

  it('rejects malformed payloads at the boundary', () => {
    expect(() => Profile.parse({ ...raw, roles: 'ROLE_MEMBER' })).toThrow(InvalidResponseError)
  })

  it('computes initials', () => {
    expect(initialsOf('jean.dupont')).toBe('JD')
    expect(initialsOf('Bob')).toBe('BO')
  })
})

describe('ConversationSummary.title', () => {
  it('shows the other member, or the board mailbox and subject', () => {
    const direct = ConversationSummary.parse({
      id: 'c1', mailbox: null, subject: null, unread: 0, lastMessage: null,
      with: { id: 'u2', displayName: 'Bob', avatarUrl: null },
    })
    const board = ConversationSummary.parse({
      id: 'c2', mailbox: { id: 'president', label: 'Présidence' }, subject: 'Cotisation', unread: 1,
      with: null, lastMessage: { preview: 'Bonjour', at: '2026-10-10T10:00:00+00:00' },
    })

    expect(direct.title).toBe('Bob')
    expect(board.title).toBe('Présidence · Cotisation')
  })
})
