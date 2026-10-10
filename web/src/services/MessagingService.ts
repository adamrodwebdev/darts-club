import type { ApiClient } from '@/api/ApiClient'
import {
  ChatMessage,
  ConversationSummary,
  parseConversationList,
  parseMember,
  parseMessagePage,
  type Member,
} from '@/models/Conversation'
import { list, obj } from '@/models/guards'

export type NewConversation =
  | { recipientId: string; body: string }
  | { mailbox: string; subject?: string; body: string }

export class MessagingService {
  constructor(private readonly api: ApiClient) {}

  async conversations(): Promise<ReturnType<typeof parseConversationList>> {
    return parseConversationList(await this.api.get('/api/conversations'))
  }

  async start(input: NewConversation): Promise<ConversationSummary> {
    return ConversationSummary.parse(await this.api.post('/api/conversations', { ...input }))
  }

  async messages(conversationId: string, before?: string): Promise<ReturnType<typeof parseMessagePage>> {
    const query = before ? `?before=${encodeURIComponent(before)}` : ''
    return parseMessagePage(await this.api.get(`/api/conversations/${encodeURIComponent(conversationId)}/messages${query}`))
  }

  async reply(conversationId: string, body: string): Promise<ChatMessage> {
    return ChatMessage.parse(await this.api.post(`/api/conversations/${encodeURIComponent(conversationId)}/messages`, { body }))
  }

  async markRead(conversationId: string): Promise<void> {
    await this.api.post(`/api/conversations/${encodeURIComponent(conversationId)}/read`)
  }

  async members(query = ''): Promise<Member[]> {
    const data = obj(await this.api.get(`/api/members?q=${encodeURIComponent(query)}`))
    return list(data.items, parseMember)
  }
}
