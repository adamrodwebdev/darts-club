import { enumLabel } from '@/i18n'
import { labelled } from '@/i18n/labels'
import { parseBadges, type Badge } from './Badge'
import { bool, list, num, obj, optStr, str, strList, type Json } from './guards'

/** Fonctions du bureau attribuables (mêmes identifiants que les badges) */
export const BOARD_ROLE_OPTIONS = (['president', 'tresorier', 'secretaire'] as const).map((id) => labelled('badge', id))

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
  /** Validé par le bureau (espace membres ouvert) */
  approved: boolean
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
    approved: d.approved === undefined ? true : bool(d.approved),
    twoFactorEnabled: bool(d.twoFactorEnabled),
    suspended: bool(d.suspended),
    suspensionReason: optStr(d.suspensionReason),
    membership: optStr(d.membership),
  }
}

export interface BroadcastRecord {
  readonly id: string
  readonly label: string
  /** Libellé du label dans la langue courante */
  readonly labelText: string
  title: string
  body: string
  author: string
  toAllMembers: boolean
  recipientCount: number
  sentAt: Date
}

export function parseBroadcast(value: unknown): BroadcastRecord {
  const d = obj(value, 'diffusion')
  const label = str(d.label)
  const labelText = str(d.labelText)
  return {
    id: str(d.id),
    label,
    get labelText() {
      return enumLabel('category', label, labelText)
    },
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

/** Libellé d'une action du journal (« member.suspend »…) dans la langue courante */
export function auditLabel(action: string): string {
  return enumLabel('audit', action)
}

export function parseList<T>(value: unknown, parse: (item: unknown) => T): T[] {
  return list(obj(value).items, parse)
}
