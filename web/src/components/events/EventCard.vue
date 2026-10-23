<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { dateBlock, formatEventDate, type EventSummary } from '@/models/ClubEvent'

const props = defineProps<{ event: EventSummary }>()
const { t } = useI18n()
const block = computed(() => dateBlock(props.event.startsAt))
</script>

<template>
  <RouterLink :to="`/evenements/${event.id}`" class="card-link" :class="{ 'card-link--cancelled': event.cancelled }">
    <span class="date" aria-hidden="true">
      <span class="date__day">{{ block.day }}</span>
      <span class="date__month">{{ block.month }}</span>
    </span>
    <span class="info">
      <span class="info__title">
        <span v-if="event.cancelled" class="tag tag--danger">{{ t('enums.eventStatus.annule') }}</span>
        {{ event.title }}
      </span>
      <span class="muted">{{ formatEventDate(event.startsAt) }} · {{ event.location }}</span>
      <span class="tags">
        <span v-if="event.isRegistered" class="tag tag--ok">{{ t('events.card.registered') }}</span>
        <span v-else-if="event.myAnswer === 'pas-sur' && !event.past" class="tag">{{ t('events.answer.maybe') }}</span>
        <span v-else-if="event.full && !event.past" class="tag">{{ t('events.card.full') }}</span>
        <span v-else-if="event.placesLeft !== null && !event.past" class="tag">{{ t('events.card.placesLeft', event.placesLeft) }}</span>
        <span v-if="event.past && event.hasSummary" class="tag">{{ t('events.card.summary') }}</span>
        <span v-if="event.isPublic && !event.past" class="tag">{{ t('events.card.public') }}</span>
      </span>
    </span>
    <!-- Décorative : le titre de l'événement est déjà dans le lien -->
    <img
      v-if="event.poster"
      class="poster"
      :src="event.poster.cardUrl"
      :width="event.poster.width"
      :height="event.poster.height"
      alt=""
      loading="lazy"
      decoding="async"
    >
  </RouterLink>
</template>

<style scoped>
.card-link {
  container: event-card / inline-size;
  display: flex;
  gap: 1rem;
  align-items: center;
  padding: 0.9rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  text-decoration: none;
  transition:
    transform 0.15s ease,
    border-color 0.15s ease;
}

.card-link:hover {
  transform: translateY(-2px);
  border-color: var(--color-accent);
}

.card-link--cancelled {
  opacity: 0.75;
}

.date {
  display: grid;
  place-items: center;
  flex: none;
  width: 3.75rem;
  height: 3.75rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  line-height: 1;
}

.date__day {
  font-size: 1.5rem;
  font-weight: 800;
}

.date__month {
  font-size: 0.8rem;
  text-transform: uppercase;
}

.info {
  display: grid;
  flex: 1;
  gap: 0.2rem;
  min-width: 0;
}

.poster {
  flex: none;
  width: clamp(3.5rem, 18cqi, 5.5rem);
  height: auto;
  max-height: 7.5rem;
  border-radius: calc(var(--radius) / 2);
  object-fit: cover;
}

.info__title {
  font-weight: 800;
  overflow-wrap: anywhere;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.tag {
  padding: 0.05rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.75rem;
  font-weight: 700;
}

.tag--ok {
  color: var(--color-success);
}

.tag--danger {
  color: var(--color-danger);
}
</style>
