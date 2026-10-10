import { parseBadges, type Badge } from './Badge'
import { bool, list, num, obj, optStr, str, strList, type Json } from './guards'

export const BOARD_ROLE_OPTIONS = [
  { id: 'president', label: 'Président' },
  { id: 'tresorier', label: 'Trésorier' },
  { id: 'secretaire', label: 'Secrétaire' },
] as const

export interface AdminMember {
  id: string
  displayName: string
  email: string
  phone: string | null
  avatarUrl: string | null
  roles: string[]
  admin: boolean
  badges: Badge[]
  verified: boolean
  twoFactorEnabled: boolean
  suspended: boolean
  suspensionReason: string | null
  membership: string | null
}

export function parseAdminMember(value: unknown): AdminMember {
  const d = obj(value, 'membre')
  return {
    id: str(d.id),
    displayName: str(d.displayName),
    email: str(d.email),
    phone: optStr(d.phone),
    avatarUrl: optStr(d.avatarUrl),
    roles: strList(d.roles),
    admin: bool(d.admin),
    badges: parseBadges(d.badges),
    verified: bool(d.verified),
    twoFactorEnabled: bool(d.twoFactorEnabled),
    suspended: bool(d.suspended),
    suspensionReason: optStr(d.suspensionReason),
    membership: optStr(d.membership),
  }
}

export interface BroadcastRecord {
  id: string
  label: string
  labelText: string
  title: string
  body: string
  author: string
  toAllMembers: boolean
  recipientCount: number
  sentAt: Date
}

export function parseBroadcast(value: unknown): BroadcastRecord {
  const d = obj(value, 'diffusion')
  return {
    id: str(d.id),
    label: str(d.label),
    labelText: str(d.labelText),
    title: str(d.title),
    body: str(d.body),
    author: str(d.author),
    toAllMembers: bool(d.toAllMembers),
    recipientCount: num(d.recipientCount),
    sentAt: new Date(str(d.sentAt)),
  }
}

export interface Announcement {
  id: string
  title: string
  body: string
  pinned: boolean
  author: string
  publishedAt: Date
  editedAt: Date | null
}

export function parseAnnouncement(value: unknown): Announcement {
  const d = obj(value, 'annonce')
  const edited = optStr(d.editedAt)
  return {
    id: str(d.id),
    title: str(d.title),
    body: str(d.body),
    pinned: bool(d.pinned),
    author: str(d.author),
    publishedAt: new Date(str(d.publishedAt)),
    editedAt: edited ? new Date(edited) : null,
  }
}

export interface AuditEntry {
  id: string
  at: Date
  actor: string
  action: string
  target: string | null
  details: Json
}

export function parseAuditEntry(value: unknown): AuditEntry {
  const d = obj(value, 'journal')
  return {
    id: str(d.id),
    at: new Date(str(d.at)),
    actor: str(d.actor),
    action: str(d.action),
    target: optStr(d.target),
    details: Array.isArray(d.details) ? {} : obj(d.details),
  }
}

export const AUDIT_LABELS: Record<string, string> = {
  'member.roles': 'Rôles modifiés',
  'member.suspend': 'Compte suspendu',
  'member.reactivate': 'Compte réactivé',
  'broadcast.send': 'Message diffusé',
  'announcement.publish': 'Annonce publiée',
  'announcement.edit': 'Annonce modifiée',
  'announcement.delete': 'Annonce supprimée',
}

export function parseList<T>(value: unknown, parse: (item: unknown) => T): T[] {
  return list(obj(value).items, parse)
}
