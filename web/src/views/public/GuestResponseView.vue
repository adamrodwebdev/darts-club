<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import PublicEventCard from '@/components/events/PublicEventCard.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { ATTENDANCE_LABELS, type Attendance } from '@/models/ClubEvent'
import type { GuestResponse } from '@/models/PublicEvent'
import { useServices } from '@/services'

const services = useServices()

/**
 * Lien personnel reçu par email (« /e/…/reponse#jeton ») : confirmer, changer
 * d'avis ou se désinscrire. Le jeton est dans le fragment, jamais envoyé au
 * serveur dans l'URL ; il est retiré de la barre d'adresse après lecture.
 */
defineProps<{ slug: string }>()
const { t } = useI18n()

const token = ref('')
const response = ref<GuestResponse | null>(null)
const removed = ref(false)
const load = useAsyncAction()
const action = useAsyncAction()

onMounted(async () => {
  token.value = window.location.hash.slice(1)
  if (token.value) window.history.replaceState(window.history.state, '', window.location.pathname)
  const data = await load.run(() => services.publicEvents.lookup(token.value))
  if (data) response.value = data
})

async function confirm(): Promise<void> {
  const data = await action.run(() => services.publicEvents.confirm(token.value))
  if (data) response.value = data
}

async function change(answer: Attendance): Promise<void> {
  const data = await action.run(() => services.publicEvents.change(token.value, answer))
  if (data) response.value = data
}

async function cancel(): Promise<void> {
  if (await action.run(async () => (await services.publicEvents.cancel(token.value), true))) removed.value = true
}
</script>

<template>
  <section class="page stack">
    <h1 class="visually-hidden">{{ t('routes.guestResponse') }}</h1>
    <p v-if="load.error.value" class="notice notice--error" role="alert">
      {{ t('guestResponse.invalid') }}
      <RouterLink :to="`/e/${slug}`">{{ t('guestResponse.backToEvent') }}</RouterLink>
    </p>

    <p v-if="removed" class="notice notice--success" role="status">
      {{ t('guestResponse.removed') }}
      <RouterLink :to="`/e/${slug}`">{{ t('guestResponse.backToEvent') }}</RouterLink>
    </p>

    <PublicEventCard v-else-if="response" :event="response.event">
      <div class="panel stack" aria-live="polite">
        <template v-if="response.pendingAnswer">
          <p>
            {{ t('guestResponse.pending', { name: response.name, answer: t(ATTENDANCE_LABELS[response.pendingAnswer]) }) }}
          </p>
          <p v-if="response.expired" class="notice notice--error">{{ t('guestResponse.expired') }}</p>
          <BaseButton v-else :pending="action.pending.value" @click="confirm">{{ t('guestResponse.confirm') }}</BaseButton>
        </template>

        <template v-else-if="response.confirmed && response.answer">
          <p class="notice notice--success">
            {{ t('guestResponse.confirmed', { name: response.name, answer: t(ATTENDANCE_LABELS[response.answer]) }) }}
          </p>
          <p v-if="response.event.location">{{ t('guestResponse.location', { location: response.event.location }) }}</p>
          <div v-if="!response.event.cancelled && !response.event.started" class="actions" role="group" :aria-label="t('events.answer.label')">
            <BaseButton
              v-if="response.answer !== 'je-viens'"
              variant="ghost"
              :disabled="response.event.full"
              :pending="action.pending.value"
              @click="change('je-viens')"
            >
              {{ t('guestResponse.switchTo', { answer: t('events.answer.going') }) }}
            </BaseButton>
            <BaseButton v-else variant="ghost" :pending="action.pending.value" @click="change('pas-sur')">
              {{ t('guestResponse.switchTo', { answer: t('events.answer.maybe') }) }}
            </BaseButton>
            <BaseButton variant="danger" :pending="action.pending.value" @click="cancel">{{ t('guestResponse.cancel') }}</BaseButton>
          </div>
          <p class="muted small">{{ t('guestResponse.keepEmail') }}</p>
        </template>

        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
      </div>
    </PublicEventCard>
  </section>
</template>

<style scoped>
.panel {
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.panel p {
  margin: 0;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.small {
  font-size: 0.85rem;
}
</style>
