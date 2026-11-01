<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'

/** En-tête commun aux écrans de la soirée (accueil, bar, cuisine) : poste, soirée, état du réseau */
defineProps<{ title: string; eventTitle: string; startsAt: Date; online: boolean }>()
const { t } = useI18n()
</script>

<template>
  <header class="screen-head">
    <div class="screen-head__title">
      <h1>{{ title }}</h1>
      <p class="muted">{{ eventTitle }} · {{ formatDate(startsAt, { weekday: 'short', day: 'numeric', month: 'short', timeZone: CLUB_TIME_ZONE }) }}</p>
    </div>
    <p class="status" :class="{ 'status--off': !online }" role="status">
      <span class="status__dot" aria-hidden="true" />
      {{ online ? t('evening.online') : t('evening.offline') }}
    </p>
  </header>
</template>

<style scoped>
.screen-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}

.screen-head__title {
  display: grid;
  gap: 0.15rem;
}

h1 {
  margin: 0;
  font-size: clamp(1.6rem, 1.3rem + 1.2vw, 2.2rem);
}

.screen-head__title p {
  margin: 0;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  padding: 0.35rem 0.8rem;
  border: 1px solid var(--color-success);
  border-radius: 999px;
  color: var(--color-success);
  font-size: 0.875rem;
  font-weight: 700;
}

.status__dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: currentColor;
}

.status--off {
  border-color: var(--badge-gold);
  color: var(--badge-gold);
}
</style>
