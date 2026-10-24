import { describe, expect, it } from 'vitest'
import { AssociationProfile } from '../AssociationProfile'
import { dayDate, EventProcedures, ProcedureDocument } from '../EventDocuments'
import { InvalidResponseError } from '../guards'

const event = { id: 'e1', title: 'Soirée fléchettes', startsAt: '2026-11-14T19:00:00+00:00' }
const summary = { type: 'buvette', status: 'envoyee', recipient: 'Mairie de Bellevesvre', deadline: '2026-10-30', sentOn: '2026-10-20', receivedOn: null, updatedAt: '2026-10-20T08:00:00+00:00', edited: true }

const detail = {
  event,
  ...summary,
  updatedBy: 'Nina',
  fields: [
    { key: 'commune', kind: 'text', value: 'Bellevesvre', choices: [] },
    { key: 'group3', kind: 'checkbox', value: true, choices: [] },
    { key: 'signer', kind: 'choice', value: 'presidence', choices: ['presidence', 'secretariat'] },
  ],
  blocks: [
    { id: 'sender', kind: 'expediteur', text: 'Skull Darts 71', label: null, rewritten: false },
    { id: 'politeness', kind: 'paragraphe', text: 'Merci.', label: null, rewritten: true },
    { id: 'limits', kind: 'note', text: 'Cinq autorisations par an.', label: null, rewritten: false },
  ],
  links: [{ label: 'Fiche service-public', url: 'https://www.service-public.gouv.fr/associations/vosdroits/F24345' }],
  tickets: null,
  missing: ['insurer'],
  mayEdit: true,
}

describe('event documents', () => {
  it('parses the list of procedures with the association details state', () => {
    const list = EventProcedures.parse({
      event,
      procedures: [summary, { ...summary, type: 'budget', status: 'a-faire', deadline: null, sentOn: null, updatedAt: null, edited: false }],
      profile: { missing: [], certificateValidUntil: '2027-08-31', certificateCoversEvent: true },
      mayEdit: false,
    })
    expect(list.procedures.map((p) => [p.type, p.status, p.deadline])).toEqual([
      ['buvette', 'envoyee', '2026-10-30'],
      ['budget', 'a-faire', null],
    ])
    expect(list.certificateCoversEvent).toBe(true)
    expect(list.mayEdit).toBe(false)
  })

  it('keeps the reminders for the secretary off the printed sheet', () => {
    const document = ProcedureDocument.parse(detail)
    expect(document.summary).toMatchObject({ type: 'buvette', sentOn: '2026-10-20', edited: true })
    expect(document.content.fields.map((f) => f.value)).toEqual(['Bellevesvre', true, 'presidence'])
    expect(document.content.sheet.map((b) => b.id)).toEqual(['sender', 'politeness'])
    expect(document.content.notes.map((b) => b.id)).toEqual(['limits'])
    expect(document.updatedBy).toBe('Nina')
  })

  it('rejects unknown kinds and statuses', () => {
    expect(() => ProcedureDocument.parse({ ...detail, status: 'perdue' })).toThrow(InvalidResponseError)
    expect(() => ProcedureDocument.parse({ ...detail, blocks: [{ id: 'x', kind: 'image', text: '', label: null, rewritten: false }] })).toThrow(InvalidResponseError)
  })

  it('reads API days as the same calendar day in every European time zone', () => {
    expect(dayDate('2026-10-30').toISOString()).toBe('2026-10-30T12:00:00.000Z')
  })
})

describe('association profile', () => {
  it('turns unknown values into empty fields', () => {
    const profile = AssociationProfile.parse({
      officialName: 'Skull Darts 71',
      rnaNumber: 'W712001234',
      siret: null,
      seatAddress: null,
      presidentName: 'Camille Martin',
      secretaryName: null,
      treasurerName: null,
      insurerName: null,
      insurancePolicy: null,
      missing: ['seat', 'insurer'],
      certificate: { validUntil: '2027-08-31', valid: true },
      updatedAt: null,
      updatedBy: null,
      mayEdit: true,
    })
    expect(profile.details).toMatchObject({ rnaNumber: 'W712001234', siret: '', presidentName: 'Camille Martin' })
    expect(profile.complete).toBe(false)
    expect(profile.certificate).toEqual({ validUntil: '2027-08-31', valid: true })
  })
})
