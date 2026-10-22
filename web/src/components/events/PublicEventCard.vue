<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatEventDate } from '@/models/ClubEvent'
import type { PublicEvent } from '@/models/PublicEvent'

/** Présentation publique d'un événement : ville seulement, compteurs sans noms. */
const props = defineProps<{ event: PublicEvent }>()
const { t } = useI18n()
const placesLeft = computed(() => (props.event.capacity === null ? null : Math.max(0, props.event.capacity - props.event.going)))
</script>

<template>
  <article class="public-event" :class="{ 'public-event--cancelled': event.cancelled }">
    <img
      v-if="event.poster"
      class="poster"
      :src="event.poster.cardUrl"
      :srcset="`${event.poster.cardUrl} 900w, ${event.poster.largeUrl} 1600w`"
      sizes="(min-width: 48rem) 22rem, calc(100vw - 2rem)"
      :width="event.poster.width"
      :height="event.poster.height"
      :alt="t('events.poster.alt', { title: event.title })"
      fetchpriority="high"
    >
    <div class="info stack">
      <p class="kicker">{{ t('publicEvent.kicker') }}</p>
      <h1>{{ event.title }}</h1>
      <p class="when">{{ formatEventDate(event.startsAt) }} — {{ event.location ?? event.city }}</p>
      <p v-if="event.cancelled" class="notice notice--error" role="status">{{ t('events.detail.cancelled', { reason: event.cancelReason ?? '' }) }}</p>
      <p class="muted">
        {{ t('publicEvent.going', event.going) }} · {{ t('events.answer.maybeCount', event.maybe) }}
        <template v-if="placesLeft !== null && !event.started"> · {{ t('events.card.placesLeft', placesLeft) }}</template>
      </p>
      <p v-if="event.description" class="text">{{ event.description }}</p>
      <slot />
    </div>
  </article>
</template>

<style scoped>
.public-event {
  display: grid;
  gap: 1.5rem;
  align-items: start;
}

.poster {
  width: 100%;
  max-width: 22rem;
  height: auto;
  justify-self: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.info {
  min-width: 0;
}

.kicker {
  margin: 0;
  color: var(--color-link);
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-size: 0.85rem;
}

h1 {
  margin: 0;
  overflow-wrap: anywhere;
}

.public-event--cancelled h1 {
  text-decoration: line-through;
}

.when {
  margin: 0;
  font-weight: 700;
}

.text {
  margin: 0;
  white-space: pre-line;
}

@media (width >= 48rem) {
  .public-event {
    grid-template-columns: minmax(0, 22rem) minmax(0, 1fr);
  }
}
</style>
