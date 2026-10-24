<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatEuro } from '@/i18n/format'
import type { BarItem, EventAccounts } from '@/models/EventAccounts'

defineProps<{ accounts: EventAccounts }>()
const emit = defineEmits<{ add: []; edit: [item: BarItem] }>()
const { t } = useI18n()

/** Colonnes de quantités, dans l'ordre de la soirée */
const QUANTITIES = ['planned', 'bought', 'returned', 'sold', 'left'] as const
const QUANTITY_LABELS = {
  planned: 'events.accounts.columns.planned',
  bought: 'events.accounts.columns.bought',
  returned: 'events.accounts.columns.returned',
  sold: 'events.accounts.columns.sold',
  left: 'events.accounts.columns.left',
} as const
</script>

<template>
  <section class="card stack bar" aria-labelledby="bar-title">
    <div class="heading">
      <h2 id="bar-title">{{ t('events.accounts.bar') }}</h2>
      <p class="muted legend">{{ t('events.accounts.barLegend') }}</p>
    </div>

    <p v-if="!accounts.items.length" class="muted">{{ t('events.accounts.noItems') }}</p>

    <template v-else>
      <!-- Large : tableau ; étroit : une carte par article (requête de conteneur) -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th scope="col" class="label">{{ t('events.accounts.columns.item') }}</th>
              <th v-for="q in QUANTITIES" :key="q" scope="col">{{ t(QUANTITY_LABELS[q]) }}</th>
              <th scope="col">{{ t('events.accounts.columns.purchasePrice') }}</th>
              <th scope="col">{{ t('events.accounts.columns.salePrice') }}</th>
              <th scope="col">{{ t('events.accounts.columns.cost') }}</th>
              <th scope="col">{{ t('events.accounts.columns.income') }}</th>
              <th v-if="accounts.editable" scope="col" class="no-print"><span class="visually-hidden">{{ t('events.accounts.columns.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in accounts.items" :key="item.id">
              <th scope="row" class="label">{{ item.label }}</th>
              <td v-for="q in QUANTITIES" :key="q" :class="{ muted: q === 'planned' || q === 'left' }">{{ item[q] }}</td>
              <td>{{ formatEuro(item.purchasePrice) }}</td>
              <td>{{ formatEuro(item.salePrice) }}</td>
              <td>{{ formatEuro(item.cost) }}</td>
              <td>{{ formatEuro(item.income) }}</td>
              <td v-if="accounts.editable" class="action no-print">
                <button type="button" class="icon-button" :aria-label="t('events.accounts.editLabel', { name: item.label })" @click="emit('edit', item)">
                  <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th scope="row" :colspan="QUANTITIES.length + 3" class="label">{{ t('events.accounts.barTotal') }}</th>
              <td>{{ formatEuro(accounts.totals.barCosts) }}</td>
              <td>{{ formatEuro(accounts.totals.barIncome) }}</td>
              <td v-if="accounts.editable" class="no-print" />
            </tr>
          </tfoot>
        </table>
      </div>

      <ul class="cards">
        <li v-for="item in accounts.items" :key="item.id" class="item-card">
          <div class="item-card__head">
            <h3>{{ item.label }}</h3>
            <button v-if="accounts.editable" type="button" class="ghost-button" :aria-label="t('events.accounts.editLabel', { name: item.label })" @click="emit('edit', item)">
              {{ t('events.accounts.edit') }}
            </button>
          </div>
          <dl class="quantities">
            <div v-for="q in QUANTITIES.slice(1)" :key="q">
              <dt>{{ t(QUANTITY_LABELS[q]) }}</dt>
              <dd>{{ item[q] }}</dd>
            </div>
          </dl>
          <p class="amounts">
            <span>{{ t('events.accounts.columns.cost') }} {{ formatEuro(item.cost) }}</span>
            <span>{{ t('events.accounts.columns.income') }} <strong>{{ formatEuro(item.income) }}</strong></span>
          </p>
        </li>
      </ul>
    </template>

    <button v-if="accounts.editable" type="button" class="add-button no-print" @click="emit('add')">{{ t('events.accounts.addItem') }}</button>
  </section>
</template>

<style scoped>
.bar {
  container: bar-items / inline-size;
}

.heading {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.heading h2 {
  margin: 0;
}

.legend {
  margin: 0;
  font-size: 0.85rem;
}

.table-wrap {
  display: none;
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-variant-numeric: tabular-nums;
}

th,
td {
  padding: 0.65rem 0.6rem;
  border-bottom: 1px solid var(--color-surface-2);
  text-align: right;
  white-space: nowrap;
}

thead th {
  color: var(--color-muted);
  font-size: 0.8rem;
  font-weight: 700;
}

.label {
  padding-inline-start: 0.9rem;
  text-align: left;
  white-space: normal;
}

tbody th {
  font-weight: 600;
}

tfoot th,
tfoot td {
  border-bottom: 0;
  font-weight: 800;
}

.action {
  padding: 0 0.4rem;
}

.icon-button {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.icon-button:hover {
  color: var(--color-text);
}

.cards {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.item-card {
  display: grid;
  gap: 0.6rem;
  padding: 0.9rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.item-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.item-card h3 {
  margin: 0;
  font-size: 1rem;
}

.quantities {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.4rem;
  margin: 0;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.quantities dt {
  color: var(--color-muted);
  font-size: 0.75rem;
}

.quantities dd {
  margin: 0;
  font-weight: 700;
}

.amounts {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.25rem 1rem;
  margin: 0;
  color: var(--color-muted);
  font-size: 0.9rem;
  font-variant-numeric: tabular-nums;
}

.amounts strong {
  color: var(--color-text);
}

.ghost-button,
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

.add-button {
  justify-self: start;
  border-style: dashed;
}

@container bar-items (width >= 46rem) {
  .table-wrap {
    display: block;
  }

  .cards {
    display: none;
  }
}

/* Impression : toujours le tableau, en entier */
@media print {
  .table-wrap {
    display: block;
    overflow: visible;
  }

  .cards {
    display: none;
  }
}
</style>
