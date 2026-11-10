import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { parseEventDetail, parseEventPhoto } from '../ClubEvent'
import { InvalidResponseError } from '../guards'
import { TEXT_LIMITS } from '../limits'
import { isoDay, PressClipping } from '../PressClipping'

const PHOTO = '01a1b2c3-0000-7000-8000-0000000000c7'

/** Une coupure telle que l'API la décrit : la photo de la galerie et ses détails de presse */
function clipping(press: unknown, overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: PHOTO, thumbUrl: `/api/event-photos/${PHOTO}/thumb`, largeUrl: `/api/event-photos/${PHOTO}/large`, width: 1025, height: 750,
    caption: null, uploader: 'Alice', uploaderId: 'a1', uploadedAt: '2026-11-04T10:00:00+01:00', hidden: false, hiddenBy: null, mine: false,
    press, ...overrides,
  }
}

const details = { outlet: 'La Gazette de la Bresse', publishedOn: '2026-11-03', title: 'Une Halloween Party qui a fait le plein à Bellevesvre', credit: 'Texte et photos : CLP' }

describe('PressClipping', () => {
  it('reads a clipping with its four details and shows its day in the current language', () => {
    const read = PressClipping.from(parseEventPhoto(clipping(details)))

    expect(read.id).toBe(PHOTO)
    expect(read.photo.largeUrl).toBe(`/api/event-photos/${PHOTO}/large`)
    expect(read.details).toMatchObject(details)
    expect(read.details.publishedLabel).toBe('3 novembre 2026')
  })

  it('keeps every detail optional: null or empty means unknown', () => {
    const read = PressClipping.from(parseEventPhoto(clipping({ outlet: '', publishedOn: null, title: null, credit: null })))

    expect(read.details).toMatchObject({ outlet: null, publishedOn: null, title: null, credit: null })
    expect(read.details.publishedLabel).toBeNull()
  })

  it('reads a gallery photo without press details, from older APIs too', () => {
    expect(parseEventPhoto(clipping(null)).press).toBeNull()
    const older = clipping(null)
    delete older.press
    expect(parseEventPhoto(older).press).toBeNull()
  })

  it.each([
    ['a clipping without details', clipping(null)],
    ['details that are not an object', clipping('La Gazette de la Bresse')],
    ['an outlet sent as a number', clipping({ ...details, outlet: 42 })],
    ['a title sent as a list', clipping({ ...details, title: ['Halloween'] })],
    ['a credit sent as an object', clipping({ ...details, credit: { text: 'CLP' } })],
    ['an outlet longer than its column', clipping({ ...details, outlet: 'é'.repeat(TEXT_LIMITS.pressOutlet + 1) })],
    ['a title longer than its column', clipping({ ...details, title: 'é'.repeat(TEXT_LIMITS.pressTitle + 1) })],
    ['a credit longer than its column', clipping({ ...details, credit: 'é'.repeat(TEXT_LIMITS.pressCredit + 1) })],
    ['a 30th of February', clipping({ ...details, publishedOn: '2026-02-30' })],
    ['a French-order date', clipping({ ...details, publishedOn: '03/11/2026' })],
    ['a date with a time', clipping({ ...details, publishedOn: '2026-11-03T00:00:00Z' })],
    ['a date sent as a number', clipping({ ...details, publishedOn: 20261103 })],
    ['a width sent as text', clipping(details, { width: '1025' })],
    ['a photo without an id', clipping(details, { id: undefined })],
  ])('refuses %s at the boundary', (_case, payload) => {
    expect(() => PressClipping.from(parseEventPhoto(payload))).toThrow(InvalidResponseError)
  })

  it('accepts details that fill their whole column', () => {
    const full = { outlet: 'é'.repeat(80), publishedOn: '2028-02-29', title: 'é'.repeat(200), credit: 'é'.repeat(120) }

    expect(PressClipping.from(parseEventPhoto(clipping(full))).details).toMatchObject(full)
  })

  it('lists the clippings of an event apart from its gallery', () => {
    const detail = parseEventDetail({
      id: 'e1', title: 'Halloween Party', location: 'Salle des fêtes', startsAt: '2026-10-31T17:00:00+00:00', endsAt: '2026-10-31T23:00:00+00:00',
      season: '2026-2027', status: 'prevu', cancelReason: null, capacity: null, registered: 0, maybe: 0, isRegistered: false, myAnswer: null,
      full: false, started: true, past: true, hasSummary: false, poster: null, description: '', organizer: 'Alice', summary: null,
      canManage: false, photos: [clipping(null, { id: 'p2' })], pressClippings: [clipping(details)], posts: [], participants: null, guests: null,
    })

    expect(detail.photos.map((photo) => photo.id)).toEqual(['p2'])
    expect(detail.pressClippings.map((read) => read.id)).toEqual([PHOTO])
  })
})

describe('isoDay', () => {
  it.each(['2026-11-03', '2028-02-29', '1900-01-01'])('accepts the calendar day %s', (day) => {
    expect(isoDay(day)).toBe(day)
  })

  it.each(['2026-02-30', '2027-02-29', '2026-13-01', '2026-2-3', '03/11/2026', ' 2026-11-03', '2026-11-03 ', '', 'hier', '٢٠٢٦-١١-٠٣'])(
    'refuses %j',
    (text) => {
      expect(isoDay(text)).toBeNull()
    },
  )
})

describe('press limits', () => {
  // Les mêmes longueurs que l'API (PressDetails.php) : le champ et la colonne ne se contredisent jamais
  const api = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../../../../api/src/ClubEvent/PressDetails.php'), 'utf8')
  const constant = (name: string): number | undefined => {
    const value = new RegExp(`const ${name} = (\\d+);`).exec(api)?.[1]
    return value === undefined ? undefined : Number(value)
  }

  it('matches the columns of the API', () => {
    expect([TEXT_LIMITS.pressOutlet, TEXT_LIMITS.pressTitle, TEXT_LIMITS.pressCredit]).toEqual([constant('OUTLET_MAX'), constant('TITLE_MAX'), constant('CREDIT_MAX')])
  })
})
