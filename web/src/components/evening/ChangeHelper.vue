<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatEuro } from '@/i18n/format'
import { BILLS } from '@/models/CashRules'

/** Aide au rendu de monnaie : le billet reçu, ce qu'il faut rendre */
defineProps<{ change: number | null; total: number }>()
const given = defineModel<number | null>({ required: true })
const { t } = useI18n()
</script>

<template>
  <fieldset class="change">
    <legend>{{ t('evening.entrance.bill') }}</legend>
    <div class="bills">
      <button
        v-for="bill in BILLS"
        :key="bill"
        type="button"
        class="bill"
        :aria-pressed="given === bill"
        :aria-label="t('evening.entrance.billLabel', { amount: formatEuro(bill / 100) })"
        @click="given = given === bill ? null : bill"
      >
        {{ formatEuro(bill / 100) }}
      </button>
    </div>
    <p class="change__line" aria-live="polite">
      <span>{{ t('evening.entrance.change') }}</span>
      <strong v-if="given === null || total === 0">—</strong>
      <strong v-else-if="change === null" class="change__short">{{ t('evening.entrance.tooSmall') }}</strong>
      <strong v-else>{{ formatEuro(change / 100) }}</strong>
    </p>
  </fieldset>
</template>

<style scoped>
.change {
  display: grid;
  gap: 0.5rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

legend {
  margin-bottom: 0.4rem;
  padding: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.bills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.bill {
  min-width: 4.5rem;
  min-height: 2.75rem;
  padding: 0 0.9rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}

.bill[aria-pressed='true'] {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 14%, transparent);
}

.change__line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin: 0;
  font-variant-numeric: tabular-nums;
}

.change__line strong {
  font-size: 1.3rem;
}

.change__short {
  color: var(--badge-gold);
}
</style>
