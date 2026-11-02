<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStockQuantity } from '@/composables/useStockQuantity'
import { formatEuro, formatNumber } from '@/i18n/format'
import { LOW_STOCK_SHARE, leftShare, servingsLeft } from '@/models/IncomeRules'
import type { ItemSales } from '@/models/LiveIncome'

const props = defineProps<{ item: ItemSales }>()
const { t } = useI18n()
const quantity = useStockQuantity()

const STATION_LABELS = { bar: 'events.accounts.menu.stations.bar', cuisine: 'events.accounts.menu.stations.cuisine' } as const

/** « 124 × Verre + 9 × Pichet = 44,5 L » */
const sold = computed(() => {
  const lines = props.item.formats
    .filter((format) => format.count > 0)
    .map((format) => t('events.accounts.live.formatLine', { count: formatNumber(format.count), name: format.name }))
    .join(' + ')
  if (lines === '') return t('events.accounts.live.nothingSold')
  const served = props.item.served
  return props.item.unit === 'litre' && served !== null && served > 0 ? t('events.accounts.live.soldTotal', { lines, total: quantity.format('litre', served) }) : lines
})

/** « 15,5 L · ≈ 62 × Verre », « 59 portions » */
const left = computed(() => {
  const { unit, left: rest } = props.item
  if (unit === null) return t('events.accounts.live.removed')
  if (rest === null) return t('events.accounts.live.leftUnknown')
  const servings = unit === 'litre' ? servingsLeft(props.item) : null
  const amount = quantity.withUnit(unit, rest)
  return servings === null ? amount : `${amount} · ${t('events.accounts.live.servings', { count: formatNumber(servings.count), name: servings.name })}`
})

const share = computed(() => leftShare(props.item))
</script>

<template>
  <article class="sales" :aria-labelledby="`sales-${item.id}`">
    <div class="sales__name">
      <h3 :id="`sales-${item.id}`">{{ item.label }}</h3>
      <span class="badge" :class="`badge--${item.station}`">{{ t(STATION_LABELS[item.station]) }}</span>
    </div>
    <dl class="sales__figures">
      <div class="figure figure--sold">
        <dt>{{ t('events.accounts.live.soldLabel') }}</dt>
        <dd>{{ sold }}</dd>
      </div>
      <div class="figure figure--income">
        <dt>{{ t('events.accounts.live.amountLabel') }}</dt>
        <dd>
          <strong>{{ formatEuro(item.amount) }}</strong>
        </dd>
      </div>
      <div class="figure figure--left">
        <dt>{{ t('events.accounts.live.leftLabel') }}</dt>
        <dd>
          {{ left }}
          <span v-if="share !== null" class="meter" :class="{ 'meter--low': share < LOW_STOCK_SHARE }" aria-hidden="true">
            <span class="meter__bar" :style="{ '--share': share }" />
          </span>
        </dd>
      </div>
    </dl>
  </article>
</template>

<style scoped>
.sales {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem 1.25rem;
  padding: 0.9rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.sales__name {
  display: flex;
  flex: 2 1 11rem;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem 0.6rem;
  min-width: 0;
}

.sales__name h3 {
  margin: 0;
  font-size: 1.05rem;
  overflow-wrap: anywhere;
}

.badge {
  display: inline-flex;
  align-items: center;
  min-height: 1.6rem;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}

.badge--bar {
  background: color-mix(in srgb, var(--badge-gold) 10%, transparent);
  color: var(--badge-gold);
}

.badge--cuisine {
  background: color-mix(in srgb, var(--badge-teal) 10%, transparent);
  color: var(--badge-teal);
}

.sales__figures {
  display: flex;
  flex: 5 1 24rem;
  flex-wrap: wrap;
  gap: 0.6rem 1.25rem;
  margin: 0;
}

.figure {
  display: grid;
  align-content: start;
  gap: 0.15rem;
  font-variant-numeric: tabular-nums;
}

.figure--sold {
  flex: 2 1 12rem;
}

.figure--income {
  flex: 1 1 7rem;
}

.figure--left {
  flex: 1 1 9rem;
}

.figure dt {
  color: var(--color-muted);
  font-size: 0.8rem;
}

.figure dd {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  overflow-wrap: anywhere;
}

.meter {
  display: block;
  block-size: 0.375rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  overflow: hidden;
}

.meter__bar {
  display: block;
  block-size: 100%;
  background: var(--color-success);
  transform: scaleX(var(--share));
  transform-origin: left center;
}

.meter--low .meter__bar {
  background: var(--badge-gold);
}

@media (prefers-reduced-motion: no-preference) {
  .meter__bar {
    transition: transform 0.4s ease-out;
  }
}
</style>
