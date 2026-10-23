import type { ApiClient } from '@/api/ApiClient'
import {
  parseAdminMember,
  parseAnnouncement,
  parseAuditEntry,
  parseBroadcast,
  parseList,
  type AdminMember,
  type Announcement,
  type AuditEntry,
  type BroadcastRecord,
} from '@/models/Admin'

export interface BroadcastDraft {
  label: string
  title: string
  body: string
  /** Absent : tous les adhérents */
  userIds?: string[]
}

export interface AnnouncementDraft {
  title: string
  body: string
  pinned: boolean
}

/** Interface du président : membres, diffusions, annonces, journal. */
export class AdminService {
  constructor(private readonly api: ApiClient) {}

  async members(query = ''): Promise<AdminMember[]> {
    return parseList(await this.api.get(`/api/admin/members?q=${encodeURIComponent(query)}`), parseAdminMember)
  }

  async changeRoles(memberId: string, roles: string[]): Promise<void> {
    await this.api.put(`/api/admin/members/${encodeURIComponent(memberId)}/roles`, { roles })
  }

  /** Ouvre l'espace membres à un compte confirmé */
  async approve(memberId: string): Promise<void> {
    await this.api.post(`/api/admin/members/${encodeURIComponent(memberId)}/approve`)
  }

  async suspend(memberId: string, reason: string): Promise<void> {
    await this.api.post(`/api/admin/members/${encodeURIComponent(memberId)}/suspend`, { reason })
  }

  async reactivate(memberId: string): Promise<void> {
    await this.api.post(`/api/admin/members/${encodeURIComponent(memberId)}/reactivate`)
  }

  async broadcasts(): Promise<BroadcastRecord[]> {
    return parseList(await this.api.get('/api/admin/broadcasts'), parseBroadcast)
  }

  async broadcast(draft: BroadcastDraft): Promise<BroadcastRecord> {
    return parseBroadcast(await this.api.post('/api/admin/broadcasts', { ...draft }))
  }

  async publish(draft: AnnouncementDraft): Promise<Announcement> {
    return parseAnnouncement(await this.api.post('/api/admin/announcements', { ...draft }))
  }

  async updateAnnouncement(id: string, changes: Partial<AnnouncementDraft>): Promise<Announcement> {
    return parseAnnouncement(await this.api.patch(`/api/admin/announcements/${encodeURIComponent(id)}`, { ...changes }))
  }

  async deleteAnnouncement(id: string): Promise<void> {
    await this.api.delete(`/api/admin/announcements/${encodeURIComponent(id)}`)
  }

  async audit(): Promise<AuditEntry[]> {
    return parseList(await this.api.get('/api/admin/audit'), parseAuditEntry)
  }

  /** Canal d'informations (lecture, tous les adhérents) */
  async announcements(): Promise<Announcement[]> {
    return parseList(await this.api.get('/api/announcements'), parseAnnouncement)
  }
}
