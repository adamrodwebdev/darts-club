<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import MemberBadges from '@/components/MemberBadges.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { ChatMessage, ConversationSummary } from '@/models/Conversation'
import { useServices } from '@/services'
import { useNotificationStore } from '@/stores/notifications'

const services = useServices()

const props = defineProps<{ id: string }>()
const { t } = useI18n()

const conversation = ref<ConversationSummary | null>(null)
const messages = ref<ChatMessage[]>([])
const cursor = ref<string | null>(null)
const draft = ref('')
const load = useAsyncAction()
const send = useAsyncAction()
const older = useAsyncAction()
const thread = useTemplateRef<HTMLElement>('thread')

async function scrollToEnd(): Promise<void> {
  await nextTick()
  thread.value?.scrollTo({ top: thread.value.scrollHeight })
}

async function refresh(): Promise<void> {
  const page = await load.run(() => services.messaging.messages(props.id))
  if (!page) return
  conversation.value = page.conversation
  messages.value = page.items
  cursor.value = page.items.length >= 30 ? page.nextCursor : null
  await services.messaging.markRead(props.id).catch(() => undefined)
  await scrollToEnd()
}

async function loadOlder(): Promise<void> {
  if (!cursor.value) return
  const page = await older.run(() => services.messaging.messages(props.id, cursor.value ?? undefined))
  if (!page) return
  messages.value = [...page.items, ...messages.value]
  cursor.value = page.items.length >= 30 ? page.nextCursor : null
}

async function submit(): Promise<void> {
  const message = await send.run(() => services.messaging.reply(props.id, draft.value))
  if (message) {
    messages.value.push(message)
    draft.value = ''
    await scrollToEnd()
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) submit()
}

const stop = useNotificationStore().onNotification((n) => {
  if (n.link === `/messages/${props.id}`) refresh()
})
onMounted(refresh)
onUnmounted(stop)
</script>

<template>
  <section class="page chat">
    <header class="chat__head">
      <RouterLink to="/messages" class="back" :aria-label="t('messaging.conversation.back')">←</RouterLink>
      <h1>{{ conversation?.title ?? t('messaging.conversation.fallbackTitle') }}</h1>
    </header>

    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <div ref="thread" class="thread" role="log" aria-live="polite" :aria-label="t('messaging.conversation.thread')" tabindex="0">
      <BaseButton v-if="cursor" variant="ghost" class="older" :pending="older.pending.value" @click="loadOlder">{{ t('messaging.conversation.older') }}</BaseButton>
      <article v-for="m in messages" :key="m.id" class="bubble" :class="{ 'bubble--mine': m.mine }">
        <UserAvatar v-if="!m.mine" :name="m.author.displayName" :src="m.author.avatarUrl" :size="32" />
        <div class="bubble__content">
          <p class="bubble__meta">
            <strong v-if="!m.mine">{{ m.author.displayName }}</strong>
            <MemberBadges v-if="!m.mine" :badges="m.author.badges" compact />
            <RelativeTime :date="m.at" />
          </p>
          <!-- Texte brut : interpolation Vue échappée, jamais de v-html -->
          <p class="bubble__body">{{ m.body }}</p>
        </div>
      </article>
    </div>

    <form class="composer" novalidate @submit.prevent="submit">
      <label for="draft" class="visually-hidden">{{ t('messaging.conversation.draftLabel') }}</label>
      <textarea id="draft" v-model="draft" rows="2" maxlength="5000" :placeholder="t('messaging.conversation.draftPlaceholder')" @keydown="onKeydown" />
      <BaseButton type="submit" :pending="send.pending.value" :disabled="!draft.trim()">{{ t('messaging.conversation.send') }}</BaseButton>
    </form>
    <p v-if="send.error.value" class="notice notice--error" role="alert">{{ send.error.value }}</p>
  </section>
</template>

<style scoped>
.chat {
  display: grid;
  grid-template-rows: auto auto 1fr auto auto;
  height: calc(100dvh - 4rem);
  padding-bottom: 0.75rem;
}

.chat__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chat__head h1 {
  margin: 0;
  font-size: clamp(1.2rem, 4vw, 1.6rem);
  overflow-wrap: anywhere;
}

.back {
  display: inline-grid;
  place-items: center;
  min-width: 44px;
  min-height: 44px;
  border-radius: var(--radius);
  color: var(--color-text);
  font-size: 1.4rem;
  text-decoration: none;
}

.thread {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
  margin: 0.75rem 0;
  padding: 0.5rem;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.older {
  align-self: center;
}

.bubble {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  max-width: min(85%, 40rem);
}

.bubble--mine {
  align-self: flex-end;
}

.bubble__content {
  padding: 0.6rem 0.85rem;
  border-radius: 1rem 1rem 1rem 0.25rem;
  background: var(--color-surface-2);
}

.bubble--mine .bubble__content {
  border-radius: 1rem 1rem 0.25rem 1rem;
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.bubble__meta {
  display: flex;
  gap: 0.5rem;
  margin: 0 0 0.2rem;
  font-size: 0.75rem;
  opacity: 0.85;
}

.bubble__body {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.composer {
  display: flex;
  gap: 0.5rem;
  align-items: flex-end;
}

.composer textarea {
  flex: 1;
  min-height: 2.75rem;
  max-height: 10rem;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
  resize: vertical;
}
</style>
