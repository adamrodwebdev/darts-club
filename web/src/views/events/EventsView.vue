<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseField from '@/components/BaseField.vue'
import EventCard from '@/components/events/EventCard.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { EventSummary } from '@/models/ClubEvent'
import { currentSeason } from '@/models/Membership'
import { services } from '@/services'

const route = useRoute()
const router = useRouter()

const thisSeason = currentSeason()
const seasons = [0, -1, -2, -3].map((offset) => {
  const start = Number(thisSeason.slice(0, 4)) + offset
  return `${start}-${start + 1}`
})

const period = ref<'upcoming' | 'past'>(route.query.vue === 'archives' ? 'past' : 'upcoming')
const season = ref('')
const search = ref('')
const events = ref<EventSummary[]>([])
const canManage = ref(false)
const load = useAsyncAction()

async function refresh(): Promise<void> {
  const data = await load.run(() =>
    services.events.list({ period: period.value, season: season.value || undefined, q: search.value || undefined }),
  )
  if (data) {
    events.value = data.items
    canManage.value = data.canManage
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(refresh, 300)
})
watch([period, season], () => {
  router.replace({ query: period.value === 'past' ? { vue: 'archives' } : {} })
  refresh()
})
onMounted(refresh)
</script>

<template>
  <section class="page stack">
    <div class="head">
      <h1>Événements</h1>
      <RouterLink v-if="canManage" to="/evenements/nouveau" class="cta">Nouvel événement</RouterLink>
    </div>

    <div class="tabs" role="tablist" aria-label="Période">
      <button type="button" role="tab" :aria-selected="period === 'upcoming'" class="tab" @click="period = 'upcoming'">À venir</button>
      <button type="button" role="tab" :aria-selected="period === 'past'" class="tab" @click="period = 'past'">Archives</button>
    </div>

    <div v-if="period === 'past'" class="filters">
      <label class="field">
        <span>Saison</span>
        <select v-model="season">
          <option value="">Toutes</option>
          <option v-for="s in seasons" :key="s" :value="s">{{ s }}</option>
        </select>
      </label>
      <BaseField v-model="search" label="Rechercher (titre, lieu)" />
    </div>

    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p v-else-if="!load.pending.value && !events.length" class="muted">
      {{ period === 'upcoming' ? 'Aucun événement prévu pour le moment.' : 'Aucune archive pour ces critères.' }}
    </p>

    <ul class="list" role="tabpanel">
      <li v-for="e in events" :key="e.id"><EventCard :event="e" /></li>
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

.cta {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1.2rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  text-decoration: none;
}

.tabs {
  display: flex;
  gap: 0.25rem;
  border-bottom: 1px solid var(--color-border);
}

.tab {
  min-height: 44px;
  padding: 0 1rem;
  border: 0;
  border-bottom: 3px solid transparent;
  background: none;
  color: var(--color-muted);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.tab[aria-selected='true'] {
  border-bottom-color: var(--color-accent);
  color: var(--color-text);
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: flex-end;
}

.filters > * {
  flex: 1 1 12rem;
}

.field {
  display: grid;
  gap: 0.35rem;
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

.list {
  display: grid;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

@media (min-width: 900px) {
  .list {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
