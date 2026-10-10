<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import MemberBadges from '@/components/MemberBadges.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { ConversationSummary, Mailbox, Member } from '@/models/Conversation'
import { services } from '@/services'
import { useNotificationStore } from '@/stores/notifications'

const { t } = useI18n()
const router = useRouter()
const conversations = ref<ConversationSummary[]>([])
const mailboxes = ref<Mailbox[]>([])
const load = useAsyncAction()

// Nouveau message
const composing = ref(false)
const target = ref<'member' | 'mailbox'>('member')
const search = ref('')
const results = ref<Member[]>([])
const recipient = ref<Member | null>(null)
const mailbox = ref('')
const subject = ref('')
const body = ref('')
const send = useAsyncAction()

async function refresh(): Promise<void> {
  const data = await load.run(() => services.messaging.conversations())
  if (data) {
    conversations.value = data.items
    mailboxes.value = data.mailboxes
    mailbox.value ||= data.mailboxes[0]?.id ?? ''
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, (q) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    results.value = q.trim().length >= 2 ? await services.messaging.members(q).catch(() => []) : []
  }, 250)
})

async function start(): Promise<void> {
  const conversation = await send.run(() =>
    target.value === 'member'
      ? services.messaging.start({ recipientId: recipient.value?.id ?? '', body: body.value })
      : services.messaging.start({ mailbox: mailbox.value, subject: subject.value || undefined, body: body.value }),
  )
  if (conversation) await router.push(`/messages/${conversation.id}`)
}

const stop = useNotificationStore().onNotification((n) => {
  if (n.category === 'message') refresh()
})
onMounted(refresh)
onUnmounted(stop)
</script>

<template>
  <section class="page">
    <div class="head">
      <h1>{{ t('messaging.inbox.title') }}</h1>
      <BaseButton :variant="composing ? 'ghost' : 'primary'" :aria-expanded="composing" @click="composing = !composing">
        {{ composing ? t('common.cancel') : t('messaging.inbox.newMessage') }}
      </BaseButton>
    </div>

    <form v-if="composing" class="card stack compose" novalidate @submit.prevent="start">
      <fieldset class="target">
        <legend>{{ t('messaging.compose.recipient') }}</legend>
        <label><input v-model="target" type="radio" value="member" /> {{ t('messaging.compose.toMember') }}</label>
        <label><input v-model="target" type="radio" value="mailbox" /> {{ t('messaging.compose.toBoard') }}</label>
      </fieldset>

      <template v-if="target === 'member'">
        <div v-if="recipient" class="chosen">
          <UserAvatar :name="recipient.displayName" :src="recipient.avatarUrl" :size="32" />
          <strong>{{ recipient.displayName }}</strong>
          <MemberBadges :badges="recipient.badges" />
          <BaseButton variant="ghost" @click="recipient = null">{{ t('messaging.compose.change') }}</BaseButton>
        </div>
        <template v-else>
          <BaseField v-model="search" :label="t('messaging.compose.search')" :hint="t('messaging.compose.searchHint')" :error="send.fieldErrors.value.recipientId" />
          <ul v-if="results.length" class="results" role="listbox" :aria-label="t('messaging.compose.results')">
            <li v-for="m in results" :key="m.id ?? m.displayName">
              <button type="button" class="result" role="option" :aria-selected="false" @click="recipient = m">
                <UserAvatar :name="m.displayName" :src="m.avatarUrl" :size="32" />
                {{ m.displayName }}
                <MemberBadges :badges="m.badges" compact />
              </button>
            </li>
          </ul>
        </template>
      </template>

      <template v-else>
        <div class="field">
          <label for="mailbox">{{ t('messaging.compose.mailbox') }}</label>
          <select id="mailbox" v-model="mailbox">
            <option v-for="m in mailboxes" :key="m.id" :value="m.id">{{ m.label }}</option>
          </select>
        </div>
        <BaseField v-model="subject" :label="t('messaging.compose.subject')" :maxlength="120" />
      </template>

      <BaseField v-model="body" :label="t('messaging.compose.body')" multiline required :maxlength="5000" :error="send.fieldErrors.value.body" />
      <p v-if="send.error.value" class="notice notice--error" role="alert">{{ send.error.value }}</p>
      <BaseButton type="submit" :pending="send.pending.value" :disabled="!body.trim() || (target === 'member' && !recipient)">{{ t('messaging.compose.send') }}</BaseButton>
    </form>

    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p v-else-if="!load.pending.value && conversations.length === 0" class="muted">{{ t('messaging.inbox.empty') }}</p>

    <ul class="conversations">
      <li v-for="c in conversations" :key="c.id">
        <RouterLink :to="`/messages/${c.id}`" class="conversation" :class="{ 'conversation--unread': c.unread > 0 }">
          <UserAvatar :name="c.counterpart?.displayName ?? c.mailbox?.label ?? t('common.board')" :src="c.counterpart?.avatarUrl" :size="44" />
          <span class="conversation__main">
            <span class="conversation__title">{{ c.title }} <MemberBadges v-if="c.counterpart" :badges="c.counterpart.badges" compact /></span>
            <span v-if="c.lastMessage" class="conversation__preview">{{ c.lastMessage.preview }}</span>
          </span>
          <span class="conversation__meta">
            <RelativeTime v-if="c.lastMessage" :date="c.lastMessage.at" />
            <span v-if="c.unread > 0" class="pill">{{ c.unread }}<span class="visually-hidden"> {{ t('messaging.inbox.unread') }}</span></span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.compose {
  margin: 1rem 0;
}

.target {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.target legend {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.target label {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
}

.chosen,
.result {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.results {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.result {
  width: 100%;
  min-height: 44px;
  padding: 0.3rem 0.5rem;
  border: 0;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  color: var(--color-text);
  font: inherit;
  cursor: pointer;
}

.field {
  display: grid;
  gap: 0.35rem;
}

.field label {
  font-weight: 600;
}

select {
  min-height: 2.75rem;
  padding: 0 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
}

.conversations {
  display: grid;
  gap: 0.5rem;
  margin: 1rem 0 0;
  padding: 0;
  list-style: none;
}

.conversation {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  text-decoration: none;
}

.conversation--unread .conversation__title {
  font-weight: 800;
}

.conversation__main {
  display: grid;
  flex: 1;
  min-width: 0;
}

.conversation__title,
.conversation__preview {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conversation__preview,
.conversation__meta {
  color: var(--color-muted);
  font-size: 0.85rem;
}

.conversation__meta {
  display: grid;
  justify-items: end;
  gap: 0.25rem;
}

.pill {
  min-width: 1.4rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 800;
  text-align: center;
}
</style>
