<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { HOME_PATHS } from '@/i18n/locales'
import { usePreferencesStore } from '@/stores/preferences'

const { t } = useI18n()
const preferences = usePreferencesStore()
</script>

<template>
  <section class="not-found">
    <h1>{{ t('notFound.title') }}</h1>
    <p>{{ t('notFound.text') }}</p>
    <RouterLink :to="HOME_PATHS[preferences.locale]">{{ t('notFound.back') }}</RouterLink>
  </section>
</template>

<style scoped>
.not-found {
  display: grid;
  place-content: center;
  gap: 0.5rem;
  min-height: 70vh;
  padding: 1rem;
  text-align: center;
}

p {
  color: var(--color-muted);
}
</style>
