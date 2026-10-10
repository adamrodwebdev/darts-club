<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '@/stores/preferences'

/** Interrupteur du pied de page : couper ou relancer animations et effets (accessible à tous). */
const preferences = usePreferencesStore()
const { t } = useI18n()

const on = computed(() => preferences.appliedMotion === 'on')
</script>

<template>
  <button type="button" class="motion-toggle" :title="t('prefs.motion.toggleHint')" @click="preferences.toggleMotion()">
    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
      <path v-if="on" d="M4 12c3-6 5-6 8 0s5 6 8 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      <path v-else d="M4 12h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>
    {{ t('prefs.motion.toggle', { state: on ? t('prefs.motion.on') : t('prefs.motion.off') }) }}
  </button>
</template>

<style scoped>
.motion-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  cursor: pointer;
}

.motion-toggle:hover {
  background: var(--color-surface-2);
}
</style>
