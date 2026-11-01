<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { CLUB_TIME_ZONE, formatDate, formatEuro } from '@/i18n/format'
import type { TokenSale } from '@/models/EveningEntrance'
import type { PendingSale } from '@/services/SaleOutbox'

/** Dernières ventes de l'accueil : celles qui attendent le réseau, puis les reçues (annulables) */
defineProps<{ sales: readonly TokenSale[]; pending: readonly PendingSale[]; busy: boolean }>()
const emit = defineEmits<{ cancel: [sale: TokenSale, time: string] }>()
const { t } = useI18n()

const METHOD_LABELS = { especes: 'evening.entrance.cash', carte: 'evening.entrance.card' } as const

const time = (date: Date): string => formatDate(date, { hour: '2-digit', minute: '2-digit', timeZone: CLUB_TIME_ZONE })
const summary = (lines: readonly { name: string; count: number }[]): string =>
  lines.map((line) => t('evening.entrance.line', { count: line.count, name: line.name })).join(' + ')
</script>

<template>
  <p v-if="!sales.length && !pending.length" class="muted">{{ t('evening.entrance.none') }}</p>
  <ul v-else class="sales">
    <li v-for="waiting in pending" :key="waiting.sale.id" class="sale sale--pending">
      <span class="sale__what">
        <span><strong>{{ formatEuro(waiting.total) }}</strong> · {{ summary(waiting.sale.lines) }}</span>
        <span class="muted sale__meta">{{ t(METHOD_LABELS[waiting.sale.method]) }} · {{ t('evening.entrance.pending') }}</span>
      </span>
    </li>
    <li v-for="sale in sales" :key="sale.id" class="sale" :class="{ 'sale--cancelled': sale.cancelled }">
      <span class="sale__what">
        <span><strong>{{ formatEuro(sale.total) }}</strong> · {{ summary(sale.lines) }}</span>
        <span class="muted sale__meta">{{ time(sale.soldAt) }} · {{ t(METHOD_LABELS[sale.method]) }}<template v-if="sale.soldBy"> · {{ sale.soldBy }}</template></span>
      </span>
      <span v-if="sale.cancelled" class="muted sale__state">{{ t('evening.entrance.cancelled') }}</span>
      <button
        v-else
        type="button"
        class="sale__cancel"
        :disabled="busy"
        :aria-label="t('evening.entrance.cancelLabel', { time: time(sale.soldAt) })"
        @click="emit('cancel', sale, time(sale.soldAt))"
      >
        {{ t('evening.entrance.cancel') }}
      </button>
    </li>
  </ul>
</template>

<style scoped>
.sales {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.sale {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.25rem 0.75rem;
  padding: 0.5rem 0.5rem 0.5rem 0.85rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.sale__what {
  display: grid;
  flex: 1 1 10rem;
  min-width: 0;
  font-variant-numeric: tabular-nums;
}

.sale__meta {
  font-size: 0.85rem;
}

.sale--pending {
  border: 1px dashed var(--badge-gold);
}

.sale--cancelled .sale__what strong {
  text-decoration: line-through;
}

.sale--cancelled .sale__what {
  color: var(--color-muted);
}

.sale__state {
  padding-inline-end: 0.35rem;
  font-size: 0.9rem;
  font-weight: 700;
}

.sale__cancel {
  min-height: 2.75rem;
  padding: 0 0.9rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
</style>
