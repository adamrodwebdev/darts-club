<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useStockQuantity } from '@/composables/useStockQuantity'
import { formatEuro, formatNumber } from '@/i18n/format'
import type { BarItem, EventAccounts, SaleFormat } from '@/models/EventAccounts'

defineProps<{ accounts: EventAccounts }>()
const emit = defineEmits<{ add: []; edit: [item: BarItem] }>()
const { t } = useI18n()
const quantity = useStockQuantity()

/** Sous ce reste (part du stock), la jauge passe en alerte */
const LOW_STOCK = 0.3

const STATION_LABELS = { bar: 'events.accounts.menu.stations.bar', cuisine: 'events.accounts.menu.stations.cuisine' } as const

function format(item: BarItem, sale: SaleFormat): string {
  const price = formatEuro(sale.price)
  return item.unit === 'litre' && sale.volume !== null
    ? t('events.accounts.menu.formatVolume', { name: sale.name, volume: quantity.format('litre', sale.volume), price })
    : t('events.accounts.menu.format', { name: sale.name, price })
}

/** « 2 × Fût de 30 L » : ce qui a été gardé, en contenants */
function containers(item: BarItem): string | null {
  if (item.container.name === null) return null
  return t('events.accounts.menu.containers', { count: formatNumber(item.bought - item.returned), name: item.container.name, size: quantity.format(item.unit, item.container.size) })
}

function sold(item: BarItem): string {
  return item.served === null ? t('events.accounts.menu.sales', { n: formatNumber(item.soldCount) }, item.soldCount) : quantity.format(item.unit, item.served)
}

function share(item: BarItem): number {
  return item.left === null || item.stock <= 0 ? 0 : Math.max(0, Math.min(1, item.left / item.stock))
}

/** Au litre : combien de premiers formats (verres) restent, « ≈ 62 × Verre » */
function servings(item: BarItem): string | null {
  const first = item.formats[0]
  if (item.unit !== 'litre' || item.left === null || !first?.volume) return null
  return t('events.accounts.menu.leftServings', { n: formatNumber(Math.floor(item.left / first.volume)), format: first.name })
}
</script>

<template>
  <section class="menu stack" aria-labelledby="menu-title">
    <div class="heading">
      <h2 id="menu-title">{{ t('events.accounts.menu.title') }}</h2>
      <button v-if="accounts.menuEditable" type="button" class="add-button no-print" @click="emit('add')">{{ t('events.accounts.menu.add') }}</button>
    </div>
    <p class="muted legend">{{ t('events.accounts.menu.legend') }}</p>

    <p v-if="!accounts.items.length" class="muted">{{ t('events.accounts.menu.empty') }}</p>

    <ul v-else class="items">
      <li v-for="item in accounts.items" :key="item.id" class="item">
        <div class="item__name">
          <h3>{{ item.label }}</h3>
          <p class="badges">
            <span class="badge" :class="`badge--${item.station}`">{{ t(STATION_LABELS[item.station]) }}</span>
            <span v-if="item.alcoholic" class="badge badge--alcohol">{{ t('events.accounts.menu.alcohol') }}</span>
          </p>
        </div>

        <dl class="figures">
          <div class="figure figure--stock">
            <dt>{{ t('events.accounts.menu.stock') }}</dt>
            <dd>
              <strong>{{ quantity.withUnit(item.unit, item.stock) }}</strong>
              <span v-if="containers(item)" class="muted detail">{{ containers(item) }}</span>
            </dd>
          </div>
          <div class="figure figure--formats">
            <dt>{{ t('events.accounts.menu.formats') }}</dt>
            <dd>
              <span class="chips">
                <span v-for="sale in item.formats" :key="sale.name" class="chip">{{ format(item, sale) }}</span>
              </span>
              <span v-if="item.garnishes.length" class="muted detail">{{ t('events.accounts.menu.garnishesList', { list: item.garnishes.join(', ') }) }}</span>
            </dd>
          </div>
          <div class="figure">
            <dt>{{ t('events.accounts.menu.sold') }}</dt>
            <dd>
              <strong>{{ sold(item) }}</strong>
            </dd>
          </div>
          <div class="figure figure--left">
            <dt>{{ t('events.accounts.menu.left') }}</dt>
            <dd>
              <template v-if="item.left !== null">
                <strong>{{ quantity.format(item.unit, item.left) }}</strong>
                <span class="gauge" aria-hidden="true">
                  <span class="gauge__fill" :class="{ 'gauge__fill--low': share(item) < LOW_STOCK }" :style="{ transform: `scaleX(${share(item)})` }" />
                </span>
                <span v-if="servings(item)" class="muted detail">{{ servings(item) }}</span>
              </template>
              <span v-else class="muted detail">{{ t('events.accounts.menu.leftUnknown') }}</span>
            </dd>
          </div>
          <div class="figure">
            <dt>{{ t('events.accounts.menu.cost') }}</dt>
            <dd>{{ formatEuro(item.cost) }}</dd>
          </div>
          <div class="figure">
            <dt>{{ t('events.accounts.menu.income') }}</dt>
            <dd>
              <strong>{{ formatEuro(item.income) }}</strong>
            </dd>
          </div>
        </dl>

        <button
          v-if="accounts.menuEditable"
          type="button"
          class="edit-button no-print"
          :aria-label="t('events.accounts.menu.editLabel', { name: item.label })"
          @click="emit('edit', item)"
        >
          {{ t('events.accounts.menu.edit') }}
        </button>
      </li>
    </ul>

    <p v-if="accounts.items.length" class="total">
      {{ t('events.accounts.menu.total', { cost: formatEuro(accounts.totals.barCosts), income: formatEuro(accounts.totals.barIncome) }) }}
    </p>
  </section>
</template>

<style scoped>
.menu {
  gap: 0.75rem;
}

.heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.heading h2 {
  margin: 0;
  text-wrap: balance;
}

.legend {
  margin: 0;
  font-size: 0.875rem;
}

.items {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Une carte par article : les blocs passent à la ligne selon la place */
.item {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.75rem 1.25rem;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
}

.item__name {
  display: grid;
  flex: 2 1 12rem;
  gap: 0.4rem;
  min-width: 0;
}

.item h3 {
  margin: 0;
  font-size: 1.05rem;
}

.badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin: 0;
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

.badge--alcohol {
  border: 1px solid var(--color-border);
  color: var(--color-muted);
}

.figures {
  display: flex;
  flex: 999 1 30rem;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  min-width: 0;
  margin: 0;
}

/* Largeur minimale = contenu : « 120 portions » ne se coupe pas, le bloc suivant passe à la ligne */
.figure {
  display: grid;
  flex: 1 1 5rem;
  align-content: start;
  gap: 0.15rem;
}

.figure--formats {
  flex: 2 1 11rem;
}

.figure--left {
  flex-basis: 6.5rem;
}

.figure dt {
  color: var(--color-muted);
  font-size: 0.8rem;
}

.figure dd {
  display: grid;
  gap: 0.3rem;
  margin: 0;
  font-variant-numeric: tabular-nums;
}

.figure strong {
  font-size: 1.05rem;
}

.detail {
  font-size: 0.8rem;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.chip {
  display: inline-flex;
  align-items: center;
  min-height: 1.9rem;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  font-size: 0.875rem;
}

.gauge {
  display: block;
  height: 0.375rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--color-surface-2);
}

.gauge__fill {
  display: block;
  height: 100%;
  background: var(--color-success);
  transform-origin: left;
}

.gauge__fill--low {
  background: var(--badge-gold);
}

.edit-button,
.add-button {
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.edit-button {
  flex: none;
  align-self: center;
}

.add-button {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.total {
  margin: 0;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
</style>
