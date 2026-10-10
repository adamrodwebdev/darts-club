<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Badge } from '@/models/Badge'

defineProps<{ badges: readonly Badge[]; compact?: boolean }>()
const { t } = useI18n()

const ICONS: Record<Badge['id'], string> = {
  president: 'M12 2l2.6 6.3 6.4.6-4.9 4.3 1.5 6.5L12 16.4 6.4 19.7l1.5-6.5L3 8.9l6.4-.6z',
  tresorier: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm1 4v1.1c1.3.3 2.2 1.1 2.4 2.4h-1.8c-.2-.6-.8-1-1.6-1-1 0-1.6.4-1.6 1 0 .7.7.9 2 1.2 1.9.4 3.2 1.1 3.2 2.8 0 1.4-1 2.3-2.6 2.6V18h-1.9v-1c-1.6-.3-2.6-1.3-2.7-2.7h1.8c.2.8.8 1.2 1.8 1.2 1.1 0 1.7-.4 1.7-1.1 0-.7-.6-.9-2-1.2-1.8-.4-3.1-1-3.1-2.7 0-1.3 1-2.2 2.5-2.5V7z',
  secretaire: 'M4 4h16v16H4zm3 4v2h10V8zm0 4v2h10v-2zm0 4v2h6v-2z',
  cotisant: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.2l-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4z',
}
</script>

<template>
  <ul v-if="badges.length" class="badges" :class="{ 'badges--compact': compact }" :aria-label="t('common.badges')">
    <li v-for="badge in badges" :key="badge.id" class="badge" :class="`badge--${badge.id}`" :title="badge.label">
      <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path :d="ICONS[badge.id]" fill="currentColor" /></svg>
      <span :class="{ 'visually-hidden': compact }">{{ badge.label }}</span>
    </li>
  </ul>
</template>

<style scoped>
.badges {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
  vertical-align: middle;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.1rem 0.5rem;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.4;
  white-space: nowrap;
}

.badges--compact .badge {
  padding: 0.15rem;
}

/* Couleurs choisies pour un contraste AA en thème sombre et clair */
.badge--president {
  color: var(--badge-gold);
}

.badge--tresorier {
  color: var(--badge-teal);
}

.badge--secretaire {
  color: var(--badge-blue);
}

.badge--cotisant {
  color: var(--color-success);
}
</style>
