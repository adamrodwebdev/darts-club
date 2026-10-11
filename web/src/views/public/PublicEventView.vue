<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import PublicEventCard from '@/components/events/PublicEventCard.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { usePageHead } from '@/composables/useDocumentHead'
import { useViewBeacon } from '@/composables/useViewBeacon'
import { formatEventDate, type Attendance } from '@/models/ClubEvent'
import type { PublicEvent } from '@/models/PublicEvent'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

/** Page publique d'un événement : réponse sans compte, confirmée par email. */
const props = defineProps<{ slug: string }>()
const { t } = useI18n()
const auth = useAuthStore()
const setHead = usePageHead()
useViewBeacon(() => services.publicEvents.recordView(props.slug))

const event = ref<PublicEvent | null>(null)
const load = useAsyncAction()
const send = useAsyncAction()
const name = ref('')
const email = ref('')
const choice = ref<Attendance>('je-viens')
const consent = ref(false)
const website = ref('')
const sent = ref(false)

const open = computed(() => !!event.value && !event.value.cancelled && !event.value.started)

onMounted(async () => {
  const data = await load.run(() => services.publicEvents.get(props.slug))
  if (!data) return
  event.value = data
  if (data.full) choice.value = 'pas-sur'
  setHead({
    title: data.title,
    description: `${formatEventDate(data.startsAt)} · ${data.city}. ${data.description}`.slice(0, 160),
    structuredData: {
      '@context': 'https://schema.org',
      '@type': 'Event',
      name: data.title,
      description: data.description || undefined,
      startDate: data.startsAt.toISOString(),
      endDate: data.endsAt.toISOString(),
      eventStatus: data.cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
      eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
      location: { '@type': 'Place', name: data.city, address: { '@type': 'PostalAddress', addressLocality: data.city, addressCountry: 'FR' } },
      image: data.poster ? [window.location.origin + data.poster.largeUrl] : undefined,
      organizer: { '@type': 'SportsOrganization', name: 'Skull Darts 71', url: window.location.origin },
      isAccessibleForFree: true,
      url: window.location.origin + window.location.pathname,
    },
  })
})

async function submit(): Promise<void> {
  const ok = await send.run(async () => {
    await services.publicEvents.answer(props.slug, { name: name.value, email: email.value, answer: choice.value, consent: consent.value, website: website.value })
    return true
  })
  if (ok) sent.value = true
}
</script>

<template>
  <section class="page stack">
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <PublicEventCard v-if="event" :event="event">
      <p v-if="auth.isAuthenticated" class="notice">
        <i18n-t keypath="publicEvent.member" scope="global">
          <template #link>
            <RouterLink to="/evenements">{{ t('publicEvent.memberLink') }}</RouterLink>
          </template>
        </i18n-t>
      </p>

      <div v-if="sent" class="notice notice--success stack" role="status">
        <strong>{{ t('publicEvent.sentTitle') }}</strong>
        <span>{{ t('publicEvent.sent', { email }) }}</span>
      </div>

      <form v-else-if="open" class="stack form" novalidate @submit.prevent="submit">
        <h2>{{ t('publicEvent.formTitle') }}</h2>
        <fieldset class="choices">
          <legend>{{ t('events.answer.label') }}</legend>
          <label class="choice">
            <input v-model="choice" type="radio" name="answer" value="je-viens" :disabled="event.full">
            <span>{{ event.full ? `${t('events.answer.going')} (${t('events.detail.full')})` : t('events.answer.going') }}</span>
          </label>
          <label class="choice">
            <input v-model="choice" type="radio" name="answer" value="pas-sur">
            <span>{{ t('events.answer.maybe') }}</span>
          </label>
        </fieldset>
        <BaseField v-model="name" :label="t('publicEvent.name')" autocomplete="given-name" required :maxlength="40" />
        <BaseField v-model="email" :label="t('common.email')" type="email" autocomplete="email" :hint="t('publicEvent.emailHint')" required :maxlength="180" />
        <!-- Piège à robots : invisible et ignoré des lecteurs d'écran -->
        <div class="trap" aria-hidden="true">
          <label>{{ t('publicEvent.trap') }} <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <label class="consent">
          <input v-model="consent" type="checkbox" required>
          <span>
            <i18n-t keypath="publicEvent.consent" scope="global">
              <template #link>
                <RouterLink to="/confidentialite">{{ t('publicEvent.privacyLink') }}</RouterLink>
              </template>
            </i18n-t>
          </span>
        </label>
        <p v-if="send.error.value" class="notice notice--error" role="alert">{{ send.error.value }}</p>
        <BaseButton type="submit" :pending="send.pending.value" :disabled="!consent">{{ t('publicEvent.submit') }}</BaseButton>
      </form>

      <p v-else-if="!event.cancelled" class="muted">{{ t('publicEvent.closed') }}</p>

      <p class="muted small">
        <i18n-t keypath="publicEvent.join" scope="global">
          <template #link>
            <RouterLink to="/">{{ t('publicEvent.joinLink') }}</RouterLink>
          </template>
        </i18n-t>
      </p>
    </PublicEventCard>
  </section>
</template>

<style scoped>
.form {
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.form h2 {
  margin: 0;
  font-size: 1.15rem;
}

.choices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.5rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.choices legend {
  width: 100%;
  margin-bottom: 0.25rem;
  padding: 0;
  font-weight: 600;
}

.choice,
.consent {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.75rem;
  cursor: pointer;
}

.consent {
  align-items: flex-start;
}

.choice input,
.consent input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.15rem;
  accent-color: var(--color-accent);
}

.trap {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.small {
  margin: 0;
  font-size: 0.85rem;
}
</style>
