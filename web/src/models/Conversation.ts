import { i18n } from '@/i18n'
import { labelled, type LabelledOption } from '@/i18n/labels'
import { parseBadges, type Badge } from './Badge'
import { bool, list, num, obj, optStr, str } from './guards'

export interface Member {
  id: string | null
  displayName: string
  avatarUrl: string | null
  badges: Badge[]
}

export type Mailbox = LabelledOption

export function parseMember(value: unknown): Member {
  const d = obj(value, 'adhérent')
  return { id: optStr(d.id), displayName: str(d.displayName), avatarUrl: optStr(d.avatarUrl), badges: parseBadges(d.badges) }
}

function parseMailbox(value: unknown): Mailbox {
  const d = obj(value, 'boîte')
  return labelled('mailbox', str(d.id), str(d.label))
}

export interface ConversationSummaryData {
  id: string
  mailbox: Mailbox | null
  subject: string | null
  counterpart: Member | null
  lastMessage: { preview: string; at: Date } | null
  unread: number
}

export class ConversationSummary {
  declare readonly id: string
  declare readonly mailbox: Mailbox | null
  declare readonly subject: string | null
  declare readonly counterpart: Member | null
  declare readonly lastMessage: { preview: string; at: Date } | null
  declare readonly unread: number

  constructor(data: ConversationSummaryData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): ConversationSummary {
    const d = obj(value, 'conversation')
    const last = d.lastMessage === null ? null : obj(d.lastMessage)
    return new ConversationSummary({
      id: str(d.id),
      mailbox: d.mailbox === null ? null : parseMailbox(d.mailbox),
      subject: optStr(d.subject),
      counterpart: d.with === null ? null : parseMember(d.with),
      lastMessage: last === null ? null : { preview: str(last.preview), at: new Date(str(last.at)) },
      unread: num(d.unread),
    })
  }

  /** Titre affiché : l'autre adhérent, ou la boîte du bureau et le sujet. */
  get title(): string {
    if (this.counterpart !== null) return this.counterpart.displayName
    const box = this.mailbox?.label ?? i18n.global.t('common.board')
    return this.subject !== null ? `${box} · ${this.subject}` : box
  }
}

export interface ChatMessageData {
  id: string
  body: string
  at: Date
  mine: boolean
  author: Member
}

export class ChatMessage {
  declare readonly id: string
  declare readonly body: string
  declare readonly at: Date
  declare readonly mine: boolean
  declare readonly author: Member

  constructor(data: ChatMessageData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): ChatMessage {
    const d = obj(value, 'message')
    return new ChatMessage({ id: str(d.id), body: str(d.body), at: new Date(str(d.at)), mine: bool(d.mine), author: parseMember(d.author) })
  }
}

export function parseConversationList(value: unknown): { items: ConversationSummary[]; mailboxes: Mailbox[] } {
  const d = obj(value)
  return { items: list(d.items, ConversationSummary.parse), mailboxes: list(d.mailboxes, parseMailbox) }
}

export function parseMessagePage(value: unknown): {
  conversation: ConversationSummary
  items: ChatMessage[]
  nextCursor: string | null
} {
  const d = obj(value)
  return {
    conversation: ConversationSummary.parse(d.conversation),
    items: list(d.items, ChatMessage.parse),
    nextCursor: optStr(d.nextCursor),
  }
}
