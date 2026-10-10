<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { HOME_PATHS, LOCALES, SWITCH_LABELS, type Locale } from '@/i18n/locales'
import { usePreferencesStore } from '@/stores/preferences'

/** Bascule vers l'autre langue ; sur l'accueil, vers l'URL de cette langue. */
const preferences = usePreferencesStore()
const route = useRoute()
const router = useRouter()

const targets = computed(() => LOCALES.filter((code) => code !== preferences.locale))

async function switchTo(locale: Locale): Promise<void> {
  await preferences.setLocale(locale)
  if (route.meta.homeLocale) await router.push({ path: HOME_PATHS[locale], hash: route.hash })
}
</script>

<template>
  <button
    v-for="code in targets"
    :key="code"
    type="button"
    class="lang-btn"
    :lang="code"
    :aria-label="SWITCH_LABELS[code]"
    :title="SWITCH_LABELS[code]"
    @click="switchTo(code)"
  >
    {{ code.toUpperCase() }}
  </button>
</template>

<style scoped>
.lang-btn {
  min-width: 44px;
  min-height: 44px;
  padding: 0 0.5rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 800;
  letter-spacing: 0.05em;
  cursor: pointer;
}

.lang-btn:hover {
  background: var(--color-surface-2);
}
</style>
