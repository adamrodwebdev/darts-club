<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, formatDate, formatNumber } from '@/i18n/format'
import type { EventAudience } from '@/models/EventAudience'
import { services } from '@/services'

/** Audience de l'annonce (administrateur uniquement, contrôlé par l'API) */
const props = defineProps<{ eventId: string }>()
const { t } = useI18n()
const audience = ref<EventAudience | null>(null)
const load = useAsyncAction()

onMounted(async () => {
  const data = await load.run(() => services.events.audience(props.eventId))
  if (data) audience.value = data
})

/** Part des personnes ayant répondu (« je viens » ou « pas sûr ») */
const responseRate = computed(() => {
  const a = audience.value
  if (!a || a.unique === 0) return null
  return Math.min(100, Math.round(((a.going + a.maybe) / a.unique) * 100))
})
const peak = computed(() => Math.max(1, ...(audience.value?.days.map((d) => d.members + d.visitors) ?? [])))

function dayLabel(day: string): string {
  return formatDate(new Date(`${day}T12:00:00Z`), { weekday: 'short', day: 'numeric', month: 'short', timeZone: CLUB_TIME_ZONE })
}
</script>

<template>
  <section class="card stack audience" aria-labelledby="audience-title">
    <h2 id="audience-title">{{ t('events.audience.title') }}</h2>
    <p class="muted small">{{ t('events.audience.adminOnly') }}</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="audience">
      <dl class="tiles">
        <div class="tile">
          <dt>{{ t('events.audience.unique') }}</dt>
          <dd>{{ formatNumber(audience.unique) }}</dd>
        </div>
        <div class="tile">
          <dt>{{ t('events.audience.members') }}</dt>
          <dd>{{ formatNumber(audience.members) }}</dd>
        </div>
        <div class="tile">
          <dt>{{ t('events.audience.visitors') }}</dt>
          <dd>{{ t('events.audience.approx', { n: formatNumber(audience.visitors) }) }}</dd>
        </div>
        <div class="tile">
          <dt>{{ t('events.audience.responseRate') }}</dt>
          <dd>{{ responseRate === null ? t('events.audience.none') : t('events.audience.percent', { n: responseRate }) }}</dd>
        </div>
      </dl>

      <table v-if="audience.days.length" class="days">
        <caption>{{ t('events.audience.byDay') }}</caption>
        <thead>
          <tr>
            <th scope="col">{{ t('events.audience.day') }}</th>
            <th scope="col">{{ t('events.audience.members') }}</th>
            <th scope="col">{{ t('events.audience.visitors') }}</th>
            <th scope="col"><span class="visually-hidden">{{ t('events.audience.chart') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in audience.days" :key="d.day">
            <th scope="row">{{ dayLabel(d.day) }}</th>
            <td>{{ formatNumber(d.members) }}</td>
            <td>{{ formatNumber(d.visitors) }}</td>
            <td class="bar-cell" aria-hidden="true">
              <span class="bar bar--members" :style="{ width: `${(d.members / peak) * 100}%` }" />
              <span class="bar bar--visitors" :style="{ width: `${(d.visitors / peak) * 100}%` }" />
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else-if="!audience.archived" class="muted">{{ t('events.audience.empty') }}</p>

      <p class="muted small">
        {{ audience.archived ? t('events.audience.archived') : audience.closed ? t('events.audience.closed') : t('events.audience.method') }}
      </p>
      <a v-if="audience.days.length" class="link-button" :href="services.events.audienceCsvUrl(eventId)" download>{{ t('events.audience.export') }}</a>
    </template>
  </section>
</template>

<style scoped>
.small {
  margin: 0;
  font-size: 0.85rem;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
  gap: 0.75rem;
  margin: 0;
}

.tile {
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.tile dt {
  color: var(--color-muted);
  font-size: 0.85rem;
}

.tile dd {
  margin: 0.2rem 0 0;
  font-size: 1.5rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* Défilement contenu dans le tableau sur petit écran */
.days {
  display: block;
  overflow-x: auto;
  width: 100%;
  border-collapse: collapse;
  font-variant-numeric: tabular-nums;
}

.days caption {
  margin-bottom: 0.4rem;
  font-weight: 700;
  text-align: left;
}

.days th,
.days td {
  padding: 0.35rem 0.6rem;
  border-bottom: 1px solid var(--color-border);
  text-align: left;
  white-space: nowrap;
}

.bar-cell {
  width: 100%;
  min-width: 6rem;
}

.bar {
  display: block;
  height: 0.4rem;
  margin: 0.1rem 0;
  border-radius: 999px;
}

.bar--members {
  background: var(--color-accent);
}

.bar--visitors {
  background: var(--badge-gold);
}

.link-button {
  display: inline-flex;
  align-items: center;
  justify-self: start;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}

h2 {
  margin: 0;
}
</style>
