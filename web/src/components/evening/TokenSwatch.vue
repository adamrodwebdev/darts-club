<script setup lang="ts">
import type { TokenKind } from '@/models/EventAccounts'

/** Pastille de la couleur d'un jeton (repère visuel, le nom est toujours écrit à côté) ; un sac pour un sachet */
defineProps<{ token: Pick<TokenKind, 'bag' | 'color'> }>()
</script>

<template>
  <svg v-if="token.bag" class="swatch swatch--bag" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
    <path d="M6 8h12l-1 12H7L6 8zM9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
  <span v-else-if="token.color" class="swatch" :style="{ '--fill': `var(--token-${token.color})`, '--ring': `var(--token-${token.color}-ring)` }" aria-hidden="true" />
  <svg v-else class="swatch swatch--bag" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
    <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2" />
    <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.5" />
  </svg>
</template>

<style scoped>
.swatch {
  flex: none;
  display: inline-block;
  box-sizing: border-box;
  width: 1.375rem;
  height: 1.375rem;
  border: 2px solid var(--ring);
  border-radius: 50%;
  background: var(--fill);
}

.swatch--bag {
  border: 0;
  border-radius: 0;
  background: none;
  color: var(--color-muted);
}
</style>
