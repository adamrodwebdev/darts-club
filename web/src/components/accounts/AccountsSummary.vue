<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatEuro } from '@/i18n/format'
import type { EventAccounts } from '@/models/EventAccounts'

defineProps<{ accounts: EventAccounts }>()
const { t } = useI18n()

/** « +402,40 € » : un bénéfice porte son signe, comme une perte */
function signed(decimal: string): string {
  return Number(decimal) > 0 ? `+${formatEuro(decimal)}` : formatEuro(decimal)
}
</script>

<template>
  <section class="summary" :aria-label="t('events.accounts.summary')">
    <p class="figure">
      <span class="figure__label">{{ t('events.accounts.costs') }}</span>
      <strong class="figure__value">{{ formatEuro(accounts.totals.costs) }}</strong>
      <span class="muted">{{ t('events.accounts.planned', { amount: formatEuro(accounts.totals.plannedCosts) }) }}</span>
    </p>
    <p class="figure">
      <span class="figure__label">{{ t('events.accounts.income') }}</span>
      <strong class="figure__value">{{ formatEuro(accounts.totals.income) }}</strong>
      <span class="muted">{{ t('events.accounts.planned', { amount: formatEuro(accounts.totals.plannedIncome) }) }}</span>
    </p>
    <p class="figure figure--result" :class="{ 'figure--loss': accounts.isLoss }">
      <span class="figure__label">{{ t('events.accounts.result') }}</span>
      <strong class="figure__value">{{ signed(accounts.totals.result) }}</strong>
      <span class="muted">{{ t('events.accounts.planned', { amount: signed(accounts.totals.plannedResult) }) }}</span>
    </p>
  </section>
</template>

<style scoped>
.summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
  gap: 1rem;
}

.figure {
  display: grid;
  gap: 0.2rem;
  margin: 0;
  padding: 1.1rem 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
  font-variant-numeric: tabular-nums;
}

.figure__label {
  color: var(--color-muted);
  font-size: 0.9rem;
  font-weight: 600;
}

.figure__value {
  font-size: clamp(1.5rem, 1.3rem + 1vw, 2rem);
  font-weight: 800;
}

.figure--result {
  border-color: var(--color-success);
}

.figure--result .figure__value {
  color: var(--color-success);
}

.figure--loss {
  border-color: var(--color-danger);
}

.figure--loss .figure__value {
  color: var(--color-danger);
}

.muted {
  font-size: 0.9rem;
}
</style>
