<script setup lang="ts">
import { formatEuro } from '@/i18n/format'

/** Couleur du poste : accueil, bar, cuisine, ou jetons en circulation */
export type IncomeTone = 'entrance' | 'bar' | 'kitchen' | 'float'

defineProps<{ id: string; tone: IncomeTone; title: string; amount: string; caption?: string }>()
</script>

<template>
  <section class="income-card" :class="`income-card--${tone}`" :aria-labelledby="id">
    <h2 :id="id" class="income-card__title">{{ title }}</h2>
    <strong class="income-card__amount">{{ formatEuro(amount) }}</strong>
    <span v-if="caption" class="income-card__caption">{{ caption }}</span>
    <slot />
    <p v-if="$slots.foot" class="income-card__foot"><slot name="foot" /></p>
  </section>
</template>

<style scoped>
.income-card {
  --tone: var(--color-muted);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.1rem 1.15rem;
  border: 1px solid var(--color-border);
  border-block-start: 4px solid var(--tone);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
  font-variant-numeric: tabular-nums;
}

.income-card--entrance {
  --tone: var(--badge-blue);
}

.income-card--bar {
  --tone: var(--badge-gold);
}

.income-card--kitchen {
  --tone: var(--badge-teal);
}

.income-card__title {
  margin: 0;
  color: var(--tone);
  font-size: 0.9rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.income-card__amount {
  font-size: clamp(1.75rem, 1.45rem + 1.2vw, 2.25rem);
  font-weight: 800;
  line-height: 1.1;
}

.income-card__caption {
  color: var(--color-muted);
  font-size: 0.875rem;
}

.income-card__foot {
  margin: 0.25rem 0 0;
  padding-block-start: 0.5rem;
  border-block-start: 1px solid var(--color-border);
  color: var(--color-muted);
  font-size: 0.875rem;
}
</style>
