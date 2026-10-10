import { bool, list, num, obj, optStr, str } from './guards'

export interface Member {
  id: string | null
  displayName: string
  avatarUrl: string | null
}

export interface Mailbox {
  id: string
  label: string
}

export function parseMember(value: unknown): Member {
  const d = obj(value, 'adhérent')
  return { id: optStr(d.id), displayName: str(d.displayName), avatarUrl: optStr(d.avatarUrl) }
}

function parseMailbox(value: unknown): Mailbox {
  const d = obj(value, 'boîte')
  return { id: str(d.id), label: str(d.label) }
}

export class ConversationSummary {
  constructor(
    readonly id: string,
    readonly mailbox: Mailbox | null,
    readonly subject: string | null,
    readonly counterpart: Member | null,
    readonly lastMessage: { preview: string; at: Date } | null,
    public unread: number,
  ) {}

  static parse(value: unknown): ConversationSummary {
    const d = obj(value, 'conversation')
    const last = d.lastMessage === null ? null : obj(d.lastMessage)
    return new ConversationSummary(
      str(d.id),
      d.mailbox === null ? null : parseMailbox(d.mailbox),
      optStr(d.subject),
      d.with === null ? null : parseMember(d.with),
      last === null ? null : { preview: str(last.preview), at: new Date(str(last.at)) },
      num(d.unread),
    )
  }

  /** Titre affiché : l'autre adhérent, ou la boîte du bureau et le sujet. */
  get title(): string {
    if (this.counterpart !== null) return this.counterpart.displayName
    const box = this.mailbox?.label ?? 'Bureau'
    return this.subject !== null ? `${box} · ${this.subject}` : box
  }
}

export class ChatMessage {
  constructor(
    readonly id: string,
    readonly body: string,
    readonly at: Date,
    readonly mine: boolean,
    readonly author: Member,
  ) {}

  static parse(value: unknown): ChatMessage {
    const d = obj(value, 'message')
    return new ChatMessage(str(d.id), str(d.body), new Date(str(d.at)), bool(d.mine), parseMember(d.author))
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
