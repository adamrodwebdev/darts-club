<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import MemberBadges from '@/components/MemberBadges.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import PhotoGallery from '@/components/events/PhotoGallery.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatEventDate, type EventDetail } from '@/models/ClubEvent'
import { services } from '@/services'

const props = defineProps<{ id: string }>()
const { t } = useI18n()

const detail = ref<EventDetail | null>(null)
const event = computed(() => detail.value?.summary ?? null)
const load = useAsyncAction()
const registration = useAsyncAction()
const post = useAsyncAction()
const manage = useAsyncAction()
const draftPost = ref('')
const cancelling = ref(false)
const cancelReason = ref('')
const editingSummary = ref(false)
const summaryDraft = ref('')

async function refresh(): Promise<void> {
  const data = await load.run(() => services.events.get(props.id))
  if (data) detail.value = data
}
onMounted(refresh)

async function toggleRegistration(): Promise<void> {
  const e = event.value
  if (!e) return
  const ok = await registration.run(async () => {
    await (e.isRegistered ? services.events.unregister(e.id) : services.events.register(e.id))
    return true
  })
  if (ok) await refresh()
}

async function sendPost(): Promise<void> {
  const ok = await post.run(async () => {
    await services.events.addPost(props.id, draftPost.value)
    return true
  })
  if (ok) {
    draftPost.value = ''
    await refresh()
  }
}

async function deletePost(postId: string): Promise<void> {
  if (await manage.run(async () => (await services.events.deletePost(postId), true))) await refresh()
}

async function confirmCancel(): Promise<void> {
  if (await manage.run(async () => (await services.events.cancel(props.id, cancelReason.value), true))) {
    cancelling.value = false
    await refresh()
  }
}

function startSummary(): void {
  summaryDraft.value = detail.value?.report ?? ''
  editingSummary.value = true
}

async function saveSummary(): Promise<void> {
  if (await manage.run(async () => (await services.events.writeSummary(props.id, summaryDraft.value), true))) {
    editingSummary.value = false
    await refresh()
  }
}
</script>

<template>
  <section class="page stack">
    <RouterLink :to="event?.past ? '/evenements?vue=archives' : '/evenements'" class="back">{{ t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="detail && event">
      <header class="hero" :class="{ 'hero--cancelled': event.cancelled }">
        <p class="muted">{{ t('events.detail.meta', { season: event.season, organizer: detail.organizer }) }}</p>
        <h1>{{ event.title }}</h1>
        <p class="when">{{ formatEventDate(event.startsAt) }} — {{ event.location }}</p>
        <p v-if="event.cancelled" class="notice notice--error" role="status">{{ t('events.detail.cancelled', { reason: event.cancelReason ?? '' }) }}</p>
      </header>

      <div class="actions">
        <BaseButton
          v-if="!event.cancelled && !event.started"
          :variant="event.isRegistered ? 'ghost' : 'primary'"
          :disabled="!event.isRegistered && event.full"
          :pending="registration.pending.value"
          @click="toggleRegistration"
        >
          {{ event.isRegistered ? t('events.detail.unregister') : event.full ? t('events.detail.full') : t('events.detail.register') }}
        </BaseButton>
        <a class="link-button" :href="services.events.icsUrl(event.id)" download>{{ t('events.detail.addToCalendar') }}</a>
        <span class="muted">
          {{
            event.capacity
              ? t('events.detail.registeredOf', { n: event.registered, capacity: event.capacity }, event.registered)
              : t('events.detail.registered', event.registered)
          }}
        </span>
      </div>
      <p v-if="registration.error.value" class="notice notice--error" role="alert">{{ registration.error.value }}</p>

      <section v-if="detail.description" class="card">
        <h2>{{ t('events.detail.description') }}</h2>
        <p class="text">{{ detail.description }}</p>
      </section>

      <section v-if="detail.report || (detail.canManage && event.started)" class="card stack">
        <h2>{{ t('events.detail.summary') }}</h2>
        <p v-if="detail.report" class="text">{{ detail.report }}</p>
        <BaseButton v-if="detail.canManage" variant="ghost" @click="startSummary">{{ detail.report ? t('events.detail.editSummary') : t('events.detail.writeSummary') }}</BaseButton>
      </section>

      <PhotoGallery
        v-if="event.started && !event.cancelled"
        :event-id="event.id"
        :photos="detail.photos"
        :can-upload="true"
        :can-manage="detail.canManage"
        @changed="refresh"
      />

      <section v-if="event.started && !event.cancelled" class="stack" aria-labelledby="posts-title">
        <h2 id="posts-title">{{ t('events.detail.posts') }}</h2>
        <ul class="posts">
          <li v-for="p in detail.posts" :key="p.id" class="post">
            <UserAvatar :name="p.author.displayName" :src="p.author.avatarUrl" :size="36" />
            <div>
              <p class="post__meta">
                <strong>{{ p.author.displayName }}</strong> <MemberBadges :badges="p.author.badges" compact />
                <RelativeTime class="muted" :date="p.at" />
              </p>
              <p class="text">{{ p.body }}</p>
              <BaseButton v-if="p.mine || detail.canManage" variant="ghost" @click="deletePost(p.id)">{{ t('common.delete') }}</BaseButton>
            </div>
          </li>
        </ul>
        <form class="stack" novalidate @submit.prevent="sendPost">
          <BaseField v-model="draftPost" :label="t('events.detail.sharePost')" multiline :maxlength="5000" />
          <p v-if="post.error.value" class="notice notice--error" role="alert">{{ post.error.value }}</p>
          <BaseButton type="submit" :pending="post.pending.value" :disabled="!draftPost.trim()">{{ t('events.detail.publish') }}</BaseButton>
        </form>
      </section>

      <section v-if="detail.canManage" class="card stack" aria-labelledby="manage-title">
        <h2 id="manage-title">{{ t('events.detail.manage') }}</h2>
        <div class="actions">
          <RouterLink v-if="!event.cancelled && !event.started" :to="`/evenements/${event.id}/modifier`" class="link-button">{{ t('common.edit') }}</RouterLink>
          <BaseButton v-if="!event.cancelled && !event.past" variant="danger" @click="cancelling = true">{{ t('events.detail.cancelEvent') }}</BaseButton>
        </div>
        <p v-if="manage.error.value" class="notice notice--error" role="alert">{{ manage.error.value }}</p>
        <details v-if="detail.participants">
          <summary>{{ t('events.detail.participants', { count: detail.participants.length }) }}</summary>
          <ul class="participants">
            <li v-for="m in detail.participants" :key="m.id ?? m.displayName">
              <UserAvatar :name="m.displayName" :src="m.avatarUrl" :size="28" /> {{ m.displayName }}
            </li>
          </ul>
        </details>
      </section>

      <BaseDialog v-if="cancelling" :title="t('events.detail.cancelEvent')" @close="cancelling = false">
        <form class="stack" @submit.prevent="confirmCancel">
          <p class="muted">{{ t('events.detail.cancelNotice') }}</p>
          <BaseField v-model="cancelReason" :label="t('events.detail.cancelReason')" required :maxlength="200" />
          <div class="actions">
            <BaseButton variant="ghost" @click="cancelling = false">{{ t('events.detail.dismiss') }}</BaseButton>
            <BaseButton type="submit" variant="danger" :pending="manage.pending.value" :disabled="!cancelReason.trim()">{{ t('events.detail.cancelEvent') }}</BaseButton>
          </div>
        </form>
      </BaseDialog>

      <BaseDialog v-if="editingSummary" :title="t('events.detail.summaryDialog')" @close="editingSummary = false">
        <form class="stack" @submit.prevent="saveSummary">
          <BaseField v-model="summaryDraft" :label="t('events.detail.summaryLabel')" multiline :maxlength="10000" />
          <div class="actions">
            <BaseButton variant="ghost" @click="editingSummary = false">{{ t('common.cancel') }}</BaseButton>
            <BaseButton type="submit" :pending="manage.pending.value">{{ t('common.save') }}</BaseButton>
          </div>
        </form>
      </BaseDialog>
    </template>
  </section>
</template>

<style scoped>
.back {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  color: var(--color-muted);
  text-decoration: none;
}

.hero {
  padding: clamp(1rem, 4vw, 2rem);
  border-radius: calc(var(--radius) * 1.5);
  background:
    radial-gradient(circle at 85% 20%, color-mix(in srgb, var(--color-accent) 35%, transparent), transparent 55%),
    var(--color-surface);
  border: 1px solid var(--color-border);
}

.hero h1 {
  margin: 0.25rem 0;
}

.hero--cancelled h1 {
  text-decoration: line-through;
}

.when {
  margin: 0;
  font-weight: 700;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.link-button {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}

h2 {
  margin: 0 0 0.5rem;
}

.text {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.posts {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.post {
  display: flex;
  gap: 0.75rem;
}

.post__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0.25rem;
}

.participants {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: 0.5rem;
  margin: 0.5rem 0 0;
  padding: 0;
  list-style: none;
}

.participants li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

summary {
  display: flex;
  align-items: center;
  min-height: 44px;
  font-weight: 700;
  cursor: pointer;
}
</style>
