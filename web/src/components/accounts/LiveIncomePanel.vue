<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLiveIncome } from '@/composables/useLiveIncome'
import { CLUB_TIME_ZONE, formatDate, formatEuro, formatNumber } from '@/i18n/format'
import { salesSummary, type SoldCount } from '@/models/IncomeRules'
import { useServices } from '@/services'
import IncomeCard from './IncomeCard.vue'
import ItemSalesRow from './ItemSalesRow.vue'

const props = defineProps<{ eventId: string }>()
const { t } = useI18n()
const services = useServices()
const { income, updatedAt, stale, load } = useLiveIncome(props.eventId)

const time = computed(() => (updatedAt.value ? formatDate(updatedAt.value, { hour: '2-digit', minute: '2-digit', timeZone: CLUB_TIME_ZONE }) : ''))

const counted = (counts: SoldCount[]): string[] => counts.map((c) => t('events.accounts.live.formatLine', { count: formatNumber(c.count), name: c.name }))

const tokensSold = computed(() =>
  (income.value?.entrance.tokens ?? []).map((tally) => t('events.accounts.live.tokenLine', { count: formatNumber(tally.count), name: tally.name, value: formatEuro(tally.value) })).join(' · '),
)

const barLine = computed(() => (income.value ? counted(salesSummary(income.value.itemsAt('bar'))).join(' · ') : ''))

/** Plats servis ou en route, et commandes que la cuisine prépare encore */
const kitchenLine = computed(() => {
  if (!income.value) return ''
  const parts = counted(salesSummary(income.value.itemsAt('cuisine')))
  const waiting = income.value.orders.waiting
  if (waiting > 0) parts.push(t('events.accounts.live.preparing', { n: formatNumber(waiting) }, waiting))
  return parts.join(' · ')
})

/** « 186 commandes · 4 en cuisine · 2 prêtes · 3 annulées » */
const ordersLine = computed(() => {
  const orders = income.value?.orders
  if (!orders) return ''
  const parts = [t('events.accounts.live.orders', { n: formatNumber(orders.count) }, orders.count)]
  if (orders.waiting > 0) parts.push(t('events.accounts.live.inKitchen', { n: formatNumber(orders.waiting) }, orders.waiting))
  if (orders.ready > 0) parts.push(t('events.accounts.live.ready', { n: formatNumber(orders.ready) }, orders.ready))
  if (orders.cancelled > 0) parts.push(t('events.accounts.live.cancelled', { n: formatNumber(orders.cancelled) }, orders.cancelled))
  return parts.join(' · ')
})

const caption = computed(() => (income.value?.tokensEnabled ? t('events.accounts.live.paidInTokens') : t('events.accounts.live.sold')))
</script>

<template>
  <div class="live stack">
    <div class="live__bar">
      <p v-if="updatedAt" class="live__status" :class="{ 'live__status--stale': stale }">
        <span class="live__dot" aria-hidden="true" />
        {{ stale ? t('events.accounts.live.stale', { time }) : t('events.accounts.live.status', { time }) }}
      </p>
      <a class="link-button" :href="services.eventAccounts.incomeExportUrl(eventId)" download>{{ t('events.accounts.live.export') }}</a>
    </div>

    <p v-if="load.error.value && !income" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p v-else-if="!income" class="muted">{{ t('events.accounts.live.loading') }}</p>

    <template v-if="income">
      <div class="cards">
        <IncomeCard v-if="income.tokensEnabled" id="live-entrance" tone="entrance" :title="t('events.accounts.live.entrance')" :amount="income.entrance.total">
          <p class="split">
            <span>{{ t('events.accounts.live.cash') }}</span> <strong>{{ formatEuro(income.entrance.cash) }}</strong>
          </p>
          <p class="split">
            <span>{{ t('events.accounts.live.card') }}</span> <strong>{{ formatEuro(income.entrance.card) }}</strong>
          </p>
          <p class="detail">{{ tokensSold || t('events.accounts.live.noSales') }}</p>
          <template #foot>
            {{ t('events.accounts.live.expectedCash', { amount: formatEuro(income.entrance.expectedCash), float: formatEuro(income.entrance.cashFloat) }) }}
          </template>
        </IncomeCard>
        <IncomeCard id="live-bar" tone="bar" :title="t('events.accounts.live.bar')" :amount="income.bar.amount" :caption="caption">
          <template #foot>{{ barLine || t('events.accounts.live.nothingServed') }}</template>
        </IncomeCard>
        <IncomeCard id="live-kitchen" tone="kitchen" :title="t('events.accounts.live.kitchen')" :amount="income.kitchen.amount" :caption="caption">
          <template #foot>{{ kitchenLine || t('events.accounts.live.nothingServed') }}</template>
        </IncomeCard>
        <IncomeCard
          v-if="income.tokensEnabled"
          id="live-float"
          tone="float"
          :title="t('events.accounts.live.circulation')"
          :amount="income.circulation"
          :caption="t('events.accounts.live.circulationCaption')"
        >
          <template #foot>{{ t('events.accounts.live.circulationDetail', { sold: formatEuro(income.entrance.total), spent: formatEuro(income.spent) }) }}</template>
        </IncomeCard>
      </div>

      <p class="explain">{{ income.tokensEnabled ? t('events.accounts.live.explainTokens') : t('events.accounts.live.explainNoTokens') }}</p>

      <section class="stack per-item" aria-labelledby="live-items">
        <div class="section-head">
          <h2 id="live-items">{{ t('events.accounts.live.perItem') }}</h2>
          <span class="muted">{{ ordersLine }}</span>
        </div>
        <p v-if="!income.items.length" class="muted">{{ t('events.accounts.live.emptyMenu') }}</p>
        <ul v-else class="items">
          <li v-for="item in income.items" :key="item.id">
            <ItemSalesRow :item="item" />
          </li>
        </ul>
        <p class="totals">
          <span>{{ t('events.accounts.live.totalBar') }} <strong>{{ formatEuro(income.bar.amount) }}</strong></span>
          <span>{{ t('events.accounts.live.totalKitchen') }} <strong>{{ formatEuro(income.kitchen.amount) }}</strong></span>
          <span>{{ income.tokensEnabled ? t('events.accounts.live.totalSpent') : t('events.accounts.live.totalSold') }} <strong>{{ formatEuro(income.spent) }}</strong></span>
        </p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.live {
  gap: 1.25rem;
}

.live__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.live__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  padding: 0.35rem 0.8rem;
  border: 1px solid currentcolor;
  border-radius: 999px;
  /* Fond de carte : le vert du texte garde son contraste dans les deux thèmes */
  background: var(--color-surface);
  color: var(--color-success);
  font-size: 0.875rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.live__dot {
  inline-size: 0.55rem;
  block-size: 0.55rem;
  border-radius: 50%;
  background: currentcolor;
}

.live__status--stale {
  color: var(--badge-gold);
}

@media (prefers-reduced-motion: no-preference) {
  .live__status:not(.live__status--stale) .live__dot {
    animation: pulse 2s ease-in-out infinite;
  }
}

@keyframes pulse {
  50% {
    opacity: 0.35;
  }
}

.link-button {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 600;
  text-decoration: none;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
  gap: 1rem;
}

.split {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  margin: 0;
}

.detail {
  margin: 0;
  color: var(--color-muted);
  font-size: 0.875rem;
}

.explain {
  margin: 0;
  padding: 0.85rem 1rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.section-head h2 {
  margin: 0;
}

.items {
  display: grid;
  gap: 0.6rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.totals {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem 1.5rem;
  margin: 0;
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  font-variant-numeric: tabular-nums;
}
</style>
