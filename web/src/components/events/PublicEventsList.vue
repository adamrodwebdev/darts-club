<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { dateBlock, formatEventDate } from '@/models/ClubEvent'
import type { PublicEvent } from '@/models/PublicEvent'
import { services } from '@/services'

/**
 * Événements ouverts au public à venir : visibles sans compte, chacun menant
 * à sa page publique où l'on répond « Je viens » / « Pas sûr ».
 */
const props = withDefaults(defineProps<{ limit?: number; hideWhenEmpty?: boolean }>(), { limit: 20, hideWhenEmpty: false })
const emit = defineEmits<{ loaded: [count: number] }>()
const { t } = useI18n()
const events = ref<PublicEvent[] | null>(null)
const load = useAsyncAction()

onMounted(async () => {
  const items = await load.run(() => services.publicEvents.list())
  events.value = items ? items.slice(0, props.limit) : []
  emit('loaded', events.value.length)
})
</script>

<template>
  <div v-if="events && (events.length || !hideWhenEmpty)" class="public-events">
    <p v-if="load.error.value && !hideWhenEmpty" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p v-else-if="!events.length" class="muted">{{ t('publicEvents.empty') }}</p>
    <ul v-else class="list">
      <li v-for="event in events" :key="event.slug">
        <RouterLink :to="`/e/${event.slug}`" class="tile" :class="{ 'tile--cancelled': event.cancelled }">
          <img
            v-if="event.poster"
            class="tile__poster"
            :src="event.poster.cardUrl"
            :width="event.poster.width"
            :height="event.poster.height"
            alt=""
            loading="lazy"
            decoding="async"
          >
          <span v-else class="tile__date" aria-hidden="true">
            <span class="tile__day">{{ dateBlock(event.startsAt).day }}</span>
            <span class="tile__month">{{ dateBlock(event.startsAt).month }}</span>
          </span>
          <span class="tile__body">
            <span class="tile__title">
              <span v-if="event.cancelled" class="tag tag--danger">{{ t('enums.eventStatus.annule') }}</span>
              {{ event.title }}
            </span>
            <span class="muted">{{ formatEventDate(event.startsAt) }} · {{ event.city }}</span>
            <span class="tile__meta">
              {{ t('publicEvent.going', event.going) }}
              <template v-if="!event.cancelled && !event.started"> · <strong>{{ event.full ? t('events.detail.full') : t('publicEvents.answer') }}</strong></template>
            </span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.tile {
  container: public-event-tile / inline-size;
  display: flex;
  gap: 1rem;
  align-items: center;
  height: 100%;
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

.tile:hover {
  transform: translateY(-2px);
  border-color: var(--color-accent);
}

.tile--cancelled {
  opacity: 0.75;
}

.tile__poster {
  flex: none;
  width: clamp(4rem, 20cqi, 5.5rem);
  height: auto;
  max-height: 7.5rem;
  border-radius: calc(var(--radius) / 2);
  object-fit: cover;
}

.tile__date {
  display: grid;
  flex: none;
  place-items: center;
  width: 3.75rem;
  height: 3.75rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  line-height: 1;
}

.tile__day {
  font-size: 1.5rem;
  font-weight: 800;
}

.tile__month {
  font-size: 0.8rem;
  text-transform: uppercase;
}

.tile__body {
  display: grid;
  gap: 0.2rem;
  min-width: 0;
}

.tile__title {
  font-weight: 800;
  overflow-wrap: anywhere;
}

.tile__meta {
  font-size: 0.9rem;
}

.tile__meta strong {
  color: var(--color-link);
}

.tag {
  padding: 0.05rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.75rem;
  font-weight: 700;
}

.tag--danger {
  color: var(--color-danger);
}
</style>
