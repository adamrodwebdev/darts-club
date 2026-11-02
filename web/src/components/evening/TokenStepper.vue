<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatEuro } from '@/i18n/format'
import type { TokenKind } from '@/models/EventAccounts'
import TokenSwatch from './TokenSwatch.vue'

/** Une ligne de vente de l'accueil : jeton ou sachet, son prix, moins / quantité / plus */
defineProps<{ token: TokenKind; count: number }>()
const emit = defineEmits<{ change: [delta: number] }>()
const { t } = useI18n()
</script>

<template>
  <li class="stepper" :class="{ 'stepper--on': count > 0 }">
    <TokenSwatch :token="token" />
    <span class="stepper__label">
      <strong>{{ token.name }}</strong>
      <span class="muted">{{ token.bag ? t('evening.entrance.perBag', { price: formatEuro(token.value) }) : t('evening.entrance.perToken', { price: formatEuro(token.value) }) }}</span>
    </span>
    <button type="button" class="step" :disabled="count === 0" :aria-label="t('evening.entrance.remove', { name: token.name })" @click="emit('change', -1)">
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></svg>
    </button>
    <output class="stepper__count" aria-live="polite">{{ count }}</output>
    <button type="button" class="step" :aria-label="t('evening.entrance.add', { name: token.name })" @click="emit('change', 1)">
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></svg>
    </button>
  </li>
</template>

<style scoped>
.stepper {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.5rem 0.5rem 0.85rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.stepper--on {
  border-color: var(--color-accent);
}

.stepper__label {
  display: grid;
  flex: 1 1 auto;
  min-width: 0;
}

.stepper__label .muted {
  font-size: 0.85rem;
}

.step {
  display: grid;
  flex: none;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  font-size: 1.4rem;
  font-weight: 700;
  cursor: pointer;
  touch-action: manipulation;
}

.step:disabled {
  cursor: default;
  opacity: 0.45;
}

.stepper__count {
  min-width: 2ch;
  font-size: 1.3rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  text-align: center;
}
</style>
