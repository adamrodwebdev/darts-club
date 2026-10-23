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

describe('Badges & cotisations', () => {
  it('keeps known badges only', async () => {
    const { parseBadges } = await import('../Badge')
    expect(parseBadges([{ id: 'cotisant', label: 'Cotisant' }, { id: 'inconnu', label: '?' }])).toEqual([
      { id: 'cotisant', label: 'Cotisant' },
    ])
    expect(parseBadges(undefined)).toEqual([])
  })

  it('computes the club season like the API', async () => {
    const { currentSeason, formatEuro } = await import('../Membership')
    expect(currentSeason(new Date('2026-09-01T10:00:00'))).toBe('2026-2027')
    expect(currentSeason(new Date('2026-08-31T10:00:00'))).toBe('2025-2026')
    expect(formatEuro('30.50').replace(/\s/g, ' ')).toBe('30,50 €')
  })
})

describe('ClubEvent', () => {
  it('parses an event and derives registration state', async () => {
    const { EventSummary, dateBlock } = await import('../ClubEvent')
    const event = EventSummary.parse({
      id: 'e1', title: 'Tournoi', location: 'Lyon', startsAt: '2026-10-24T12:00:00+00:00', endsAt: '2026-10-24T18:00:00+00:00',
      season: '2026-2027', status: 'prevu', cancelReason: null, capacity: 16, registered: 15, isRegistered: false,
      full: false, started: false, past: false, hasSummary: false,
    })
    expect(event.placesLeft).toBe(1)
    expect(event.canRegister).toBe(true)
    expect(dateBlock(event.startsAt)).toEqual({ day: '24', month: 'oct' })
  })
})

describe('PublicEventTimeline', () => {
  const raw = (slug: string, overrides: Record<string, unknown> = {}) => ({
    slug, title: slug, description: '', city: 'Bellevesvre', startsAt: '2026-10-31T16:00:00+00:00', endsAt: '2026-10-31T22:00:00+00:00',
    status: 'prevu', cancelReason: null, capacity: null, going: 3, maybe: 1, full: false, started: false, past: false, poster: null,
    ...overrides,
  })

  it('shows the next event first, then the later ones, then the past ones newest first', async () => {
    const { parsePublicEventTimeline, timelineSlides } = await import('../PublicEvent')
    const timeline = parsePublicEventTimeline({
      items: [raw('live', { started: true }), raw('cancelled', { status: 'annule' }), raw('halloween'), raw('noel')],
      past: [raw('rentree', { past: true }), raw('printemps', { past: true })],
    })

    expect(timelineSlides(timeline).map((s) => `${s.event.slug}:${s.badge}`)).toEqual([
      'live:live', 'cancelled:cancelled', 'halloween:next', 'noel:upcoming', 'rentree:past', 'printemps:past',
    ])
  })

  it('rejects a response without the past events', async () => {
    const { parsePublicEventTimeline } = await import('../PublicEvent')
    expect(() => parsePublicEventTimeline({ items: [] })).toThrow(InvalidResponseError)
  })
})
