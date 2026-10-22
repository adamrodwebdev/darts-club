import { formatEuro } from '@/i18n/format'
import { labelled, type LabelledOption } from '@/i18n/labels'
import { bool, list, num, obj, optStr, str } from './guards'

export type MembershipStatusId = 'payee' | 'partielle' | 'impayee'

export type Option = LabelledOption

function paymentMethod(value: unknown): Option {
  const d = obj(value)
  return labelled('paymentMethod', str(d.id), str(d.label))
}

export interface PaymentRecordData {
  id: string
  amount: string
  method: Option
  paidAt: string
  cancelled: boolean
  reference: string | null
  recordedBy: string | null
  cancelReason: string | null
}

export class PaymentRecord {
  declare readonly id: string
  declare readonly amount: string
  declare readonly method: Option
  declare readonly paidAt: string
  declare readonly cancelled: boolean
  declare readonly reference: string | null
  declare readonly recordedBy: string | null
  declare readonly cancelReason: string | null

  constructor(data: PaymentRecordData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): PaymentRecord {
    const d = obj(value, 'paiement')
    return new PaymentRecord({
      id: str(d.id),
      amount: str(d.amount),
      method: paymentMethod(d.method),
      paidAt: str(d.paidAt),
      cancelled: bool(d.cancelled),
      reference: optStr(d.reference),
      recordedBy: optStr(d.recordedBy),
      cancelReason: optStr(d.cancelReason),
    })
  }
}

export interface MembershipRecordData {
  id: string
  season: string
  holder: { id: string | null; displayName: string; email: string | null }
  amountDue: string
  paid: string
  remaining: string
  status: LabelledOption<MembershipStatusId>
  overdue: boolean
  dueDate: string
  payments: PaymentRecord[]
}

export class MembershipRecord {
  declare readonly id: string
  declare readonly season: string
  declare readonly holder: { id: string | null; displayName: string; email: string | null }
  declare readonly amountDue: string
  declare readonly paid: string
  declare readonly remaining: string
  declare readonly status: LabelledOption<MembershipStatusId>
  declare readonly overdue: boolean
  declare readonly dueDate: string
  declare readonly payments: PaymentRecord[]

  constructor(data: MembershipRecordData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): MembershipRecord {
    const d = obj(value, 'cotisation')
    const holder = obj(d.holder)
    const status = obj(d.status)
    return new MembershipRecord({
      id: str(d.id),
      season: str(d.season),
      holder: { id: optStr(holder.id), displayName: str(holder.displayName), email: optStr(holder.email) },
      amountDue: str(d.amountDue),
      paid: str(d.paid),
      remaining: str(d.remaining),
      status: labelled('membershipStatus', str(status.id) as MembershipStatusId, str(status.label)),
      overdue: bool(d.overdue),
      dueDate: str(d.dueDate),
      payments: list(d.payments, PaymentRecord.parse),
    })
  }
}

export interface TreasuryDashboard {
  season: string
  dueDate: string
  members: number
  byStatus: Record<MembershipStatusId, number>
  overdue: number
  expected: string
  collected: string
  remaining: string
}

export function parseDashboard(value: unknown): TreasuryDashboard {
  const d = obj(value, 'tableau de bord')
  const s = obj(d.byStatus)
  return {
    season: str(d.season),
    dueDate: str(d.dueDate),
    members: num(d.members),
    byStatus: { payee: num(s.payee), partielle: num(s.partielle), impayee: num(s.impayee) },
    overdue: num(d.overdue),
    expected: str(d.expected),
    collected: str(d.collected),
    remaining: str(d.remaining),
  }
}

export function parseMembershipList(value: unknown): { season: string; items: MembershipRecord[]; methods: Option[] } {
  const d = obj(value)
  return { season: str(d.season), items: list(d.items, MembershipRecord.parse), methods: list(d.methods, paymentMethod) }
}

export function parseMyMembership(value: unknown): { season: string; current: MembershipRecord | null; history: MembershipRecord[] } {
  const d = obj(value)
  return {
    season: str(d.season),
    current: d.current === null ? null : MembershipRecord.parse(d.current),
    history: list(d.history, MembershipRecord.parse),
  }
}

/** Montants formatés dans la langue courante (voir i18n/format) */
export { formatEuro }

/** Saison sportive à une date (septembre → août), comme l'API ; la date vient de l'horloge injectée. */
export function currentSeason(now: Date): string {
  const start = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
  return `${start}-${start + 1}`
}
