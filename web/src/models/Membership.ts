import { formatEuro } from '@/i18n/format'
import { labelled, type LabelledOption } from '@/i18n/labels'
import { bool, list, num, obj, optStr, str } from './guards'

export type MembershipStatusId = 'payee' | 'partielle' | 'impayee'

export type Option = LabelledOption

function paymentMethod(value: unknown): Option {
  const d = obj(value)
  return labelled('paymentMethod', str(d.id), str(d.label))
}

export class PaymentRecord {
  constructor(
    readonly id: string,
    readonly amount: string,
    readonly method: Option,
    readonly paidAt: string,
    readonly cancelled: boolean,
    readonly reference: string | null,
    readonly recordedBy: string | null,
    readonly cancelReason: string | null,
  ) {}

  static parse(value: unknown): PaymentRecord {
    const d = obj(value, 'paiement')
    return new PaymentRecord(
      str(d.id),
      str(d.amount),
      paymentMethod(d.method),
      str(d.paidAt),
      bool(d.cancelled),
      optStr(d.reference),
      optStr(d.recordedBy),
      optStr(d.cancelReason),
    )
  }
}

export class MembershipRecord {
  constructor(
    readonly id: string,
    readonly season: string,
    readonly holder: { id: string | null; displayName: string; email: string | null },
    readonly amountDue: string,
    readonly paid: string,
    readonly remaining: string,
    readonly status: LabelledOption<MembershipStatusId>,
    readonly overdue: boolean,
    readonly dueDate: string,
    readonly payments: PaymentRecord[],
  ) {}

  static parse(value: unknown): MembershipRecord {
    const d = obj(value, 'cotisation')
    const holder = obj(d.holder)
    const status = obj(d.status)
    return new MembershipRecord(
      str(d.id),
      str(d.season),
      { id: optStr(holder.id), displayName: str(holder.displayName), email: optStr(holder.email) },
      str(d.amountDue),
      str(d.paid),
      str(d.remaining),
      labelled('membershipStatus', str(status.id) as MembershipStatusId, str(status.label)),
      bool(d.overdue),
      str(d.dueDate),
      list(d.payments, PaymentRecord.parse),
    )
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

/** Saison sportive courante (septembre → août), comme l'API. */
export function currentSeason(now = new Date()): string {
  const start = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1
  return `${start}-${start + 1}`
}
