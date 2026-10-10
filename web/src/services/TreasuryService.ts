import type { ApiClient } from '@/api/ApiClient'
import { num, obj } from '@/models/guards'
import {
  MembershipRecord,
  parseDashboard,
  parseMembershipList,
  parseMyMembership,
  type TreasuryDashboard,
} from '@/models/Membership'

export interface NewPayment {
  amount: string
  method: string
  paidAt: string
  reference?: string
}

export class TreasuryService {
  constructor(private readonly api: ApiClient) {}

  async dashboard(season: string): Promise<TreasuryDashboard> {
    return parseDashboard(await this.api.get(`/api/treasury/dashboard?season=${encodeURIComponent(season)}`))
  }

  async memberships(season: string, status?: string): Promise<ReturnType<typeof parseMembershipList>> {
    const query = new URLSearchParams({ season })
    if (status) query.set('status', status)
    return parseMembershipList(await this.api.get(`/api/treasury/memberships?${query}`))
  }

  /** @returns nombre de cotisations créées */
  async openSeason(season: string, amountDue: string): Promise<number> {
    return num(obj(await this.api.post(`/api/treasury/seasons/${encodeURIComponent(season)}/open`, { amountDue })).created)
  }

  async recordPayment(membershipId: string, payment: NewPayment): Promise<MembershipRecord> {
    return MembershipRecord.parse(await this.api.post(`/api/treasury/memberships/${encodeURIComponent(membershipId)}/payments`, { ...payment }))
  }

  async cancelPayment(paymentId: string, reason: string): Promise<MembershipRecord> {
    return MembershipRecord.parse(await this.api.post(`/api/treasury/payments/${encodeURIComponent(paymentId)}/cancel`, { reason }))
  }

  async changeAmountDue(membershipId: string, amountDue: string): Promise<MembershipRecord> {
    return MembershipRecord.parse(await this.api.patch(`/api/treasury/memberships/${encodeURIComponent(membershipId)}`, { amountDue }))
  }

  /** @returns nombre d'adhérents relancés */
  async sendReminders(): Promise<number> {
    return num(obj(await this.api.post('/api/treasury/reminders')).sent)
  }

  exportUrl(season: string): string {
    return `/api/treasury/export?season=${encodeURIComponent(season)}`
  }

  async mine(): Promise<ReturnType<typeof parseMyMembership>> {
    return parseMyMembership(await this.api.get('/api/me/membership'))
  }
}
