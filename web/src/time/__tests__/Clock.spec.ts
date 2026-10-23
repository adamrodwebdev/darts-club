import { describe, expect, it } from 'vitest'
import { formatRelative } from '@/i18n/format'
import { AppNotification } from '@/models/Notification'
import { currentSeason } from '@/models/Membership'
import { fixedClock, toDateInput } from '../Clock'

describe('injected clock', () => {
  const clock = fixedClock(new Date(2026, 9, 22, 14, 30))

  it('gives the same instant every time, as a fresh copy', () => {
    const first = clock.now()
    first.setFullYear(1999)
    expect(clock.now().getFullYear()).toBe(2026)
  })

  it('drives dates that used to read the system time', () => {
    expect(toDateInput(clock.now())).toBe('2026-10-22')
    expect(currentSeason(clock.now())).toBe('2026-2027')
    expect(formatRelative(new Date(2026, 9, 22, 11, 30), clock.now())).toBe('il y a 3 heures')
    expect(formatRelative(new Date(2026, 9, 22, 14, 29, 40), clock.now())).toBeNull()
  })
})

describe('AppNotification', () => {
  it('changes its read state only through markRead()', () => {
    const notification = AppNotification.parse({
      id: 'n1', category: 'evenement', label: 'Événement', title: 'Halloween', body: '', link: null, createdAt: '2026-10-22T10:00:00Z', read: false,
    })
    expect(notification.read).toBe(false)
    notification.markRead()
    expect(notification.read).toBe(true)
  })
})
