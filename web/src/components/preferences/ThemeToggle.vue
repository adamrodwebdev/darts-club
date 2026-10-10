<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '@/stores/preferences'

/** Bouton compact : système → clair → sombre ; le choix détaillé est dans « Mon compte ». */
const preferences = usePreferencesStore()
const { t } = useI18n()

const ICONS = {
  system: 'M4 5h16v11H4zM9 20h6M12 16v4M12 7.5a3 3 0 0 0 0 6z',
  light: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
  dark: 'M20 14.5A8 8 0 1 1 9.5 4 6.5 6.5 0 0 0 20 14.5z',
} as const

const label = computed(() => t('prefs.theme.toggle', { current: t(`prefs.theme.${preferences.theme}`) }))
</script>

<template>
  <button type="button" class="pref-btn" :aria-label="label" :title="label" @click="preferences.cycleTheme()">
    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
      <path :d="ICONS[preferences.theme]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </button>
</template>

<style scoped>
.pref-btn {
  display: inline-grid;
  place-items: center;
  min-width: 44px;
  min-height: 44px;
  padding: 0;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.pref-btn:hover {
  background: var(--color-surface-2);
}
</style>
