<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import FormatStepper from '@/components/evening/FormatStepper.vue'
import OrderTicket from '@/components/evening/OrderTicket.vue'
import ScreenHeader from '@/components/evening/ScreenHeader.vue'
import TokenSwatch from '@/components/evening/TokenSwatch.vue'
import { useBar, type BarTab } from '@/composables/useBar'
import { useStockQuantity } from '@/composables/useStockQuantity'
import { CLUB_TIME_ZONE, formatDate, formatEuro } from '@/i18n/format'
import type { ServiceItem } from '@/models/EveningService'
import { toCents } from '@/models/CashRules'
import { servingsLeft, waitMinutes } from '@/models/OrderRules'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const quantity = useStockQuantity()
const bar = useBar(props.id)
const { screen, tab, cart, garnishChoice, note, pending, online, toast, now, total, tokens, load, action } = bar

const tabs = computed<{ key: BarTab; label: string }[]>(() => [
  { key: 'order', label: t('evening.bar.tabs.order') },
  { key: 'ready', label: t('evening.bar.tabs.ready', { n: screen.value?.orders.ready.length ?? 0 }) },
  { key: 'kitchen', label: t('evening.bar.tabs.kitchen', { n: screen.value?.orders.waiting.length ?? 0 }) },
])

const time = (date: Date): string => formatDate(date, { hour: '2-digit', minute: '2-digit', timeZone: CLUB_TIME_ZONE })

/** « reste ≈ 62 × Verre », « reste 18 », « épuisé » */
function stockHint(item: ServiceItem): string {
  if (item.soldOut) return t('evening.bar.soldOut')
  const servings = servingsLeft(item)
  const first = item.formats[0]
  if (item.unit === 'litre' && servings !== null && first) return t('evening.bar.leftServings', { n: servings, format: first.name })
  return item.left === null ? '' : t('evening.bar.left', { n: quantity.format(item.unit, item.left) })
}
</script>

<template>
  <section class="page stack service">
    <RouterLink :to="`/evenements/${id}`" class="back">{{ screen ? t('events.accounts.back', { title: screen.event.title }) : t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value && !screen" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="screen">
      <ScreenHeader :title="t('evening.bar.title')" :event-title="screen.event.title" :starts-at="screen.event.startsAt" :online="online" />

      <div class="tabs" role="group" :aria-label="t('evening.bar.tabs.label')">
        <button v-for="entry in tabs" :key="entry.key" type="button" class="tab" :aria-pressed="tab === entry.key" @click="tab = entry.key">{{ entry.label }}</button>
      </div>

      <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
      <p v-if="toast" class="notice notice--success" role="status">{{ toast }}</p>

      <div v-show="tab === 'order'" class="layout">
        <div class="stack menu">
          <section class="card stack" aria-labelledby="drinks-title">
            <h2 id="drinks-title">{{ t('evening.bar.drinks') }}</h2>
            <p v-if="!screen.drinks.length" class="muted">{{ t('evening.bar.noDrinks') }}</p>
            <div v-for="item in screen.drinks" :key="item.id" class="item" :class="{ 'item--out': item.soldOut }">
              <p class="item__head">
                <strong>{{ item.label }}</strong>
                <span class="item__hint">{{ stockHint(item) }}</span>
              </p>
              <div class="item__formats">
                <FormatStepper v-for="format in item.formats" :key="format.name" :item="item" :format="format" :count="bar.countOf(item, format)" @change="(delta) => bar.step(item, format, delta)" />
              </div>
            </div>
          </section>

          <section class="card stack" aria-labelledby="food-title">
            <div class="section-head">
              <h2 id="food-title">{{ t('evening.bar.food') }}</h2>
              <span class="muted">{{ t('evening.bar.foodHint') }}</span>
            </div>
            <p v-if="!screen.food.length" class="muted">{{ t('evening.bar.noFood') }}</p>
            <div v-for="item in screen.food" :key="item.id" class="item" :class="{ 'item--out': item.soldOut }">
              <p class="item__head">
                <strong>{{ item.label }}</strong>
                <span class="item__hint">{{ stockHint(item) }}</span>
              </p>
              <template v-if="item.garnishes.length">
                <div v-for="format in item.formats" :key="format.name" class="garnished">
                  <div class="chips" role="group" :aria-label="t('evening.bar.garnishes', { name: item.label })">
                    <button
                      v-for="garnish in item.garnishes"
                      :key="garnish.name"
                      type="button"
                      class="chip"
                      :class="{ 'chip--out': garnish.out }"
                      :disabled="garnish.out || item.soldOut"
                      :aria-pressed="(garnishChoice[item.id] ?? []).includes(garnish.name)"
                      @click="bar.toggleGarnish(item, garnish.name)"
                    >
                      {{ garnish.out ? t('evening.bar.garnishOut', { name: garnish.name }) : garnish.name }}
                    </button>
                  </div>
                  <button type="button" class="add" :disabled="item.soldOut" @click="bar.addWithGarnishes(item, format)">
                    {{ t('evening.bar.addWith', { name: item.label, price: formatEuro(format.price), garnishes: (garnishChoice[item.id] ?? []).join(' + ') || t('evening.bar.plain') }) }}
                  </button>
                </div>
              </template>
              <div v-else class="item__formats">
                <FormatStepper v-for="format in item.formats" :key="format.name" :item="item" :format="format" :count="bar.countOf(item, format)" @change="(delta) => bar.step(item, format, delta)" />
              </div>
            </div>
          </section>
        </div>

        <section class="card stack summary" aria-labelledby="summary-title">
          <h2 id="summary-title">{{ t('evening.order.number', { number: screen.nextNumber }) }}</h2>
          <p class="summary__total" aria-live="polite">
            <strong>{{ formatEuro(total / 100) }}</strong>
            <span v-if="tokens && tokens.counts.length" class="summary__tokens">
              <span v-for="entry in tokens.counts" :key="entry.token.name" class="summary__token">
                <TokenSwatch :token="{ bag: false, color: entry.token.color }" />
                {{ t('evening.entrance.line', { count: entry.count, name: entry.token.name }) }}
              </span>
            </span>
          </p>
          <p v-if="tokens && tokens.rest > 0" class="muted summary__rest">{{ t('evening.bar.rest', { amount: formatEuro(tokens.rest / 100) }) }}</p>
          <p v-if="!cart.length" class="muted">{{ t('evening.bar.empty') }}</p>
          <ul v-else class="lines">
            <li v-for="line in cart" :key="line.key" class="line">
              <span class="line__what">
                {{ t('evening.order.line', { count: line.count, label: line.item.label }) }} · {{ line.format.name }}
                <span v-if="line.garnishes.length" class="line__garnish">{{ line.garnishes.join(' + ') }}</span>
              </span>
              <span class="line__price">{{ formatEuro((toCents(line.format.price) * line.count) / 100) }}</span>
              <button type="button" class="line__remove" :aria-label="t('evening.bar.removeLine', { name: line.item.label })" @click="bar.stepLine(line, -1)">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></svg>
              </button>
            </li>
          </ul>
          <BaseField v-model="note" :label="t('evening.bar.note')" :hint="t('evening.bar.noteHint')" :maxlength="40" />
          <BaseButton :disabled="!cart.length" @click="bar.validate()">{{ t('evening.bar.validate') }}</BaseButton>
          <div v-if="pending.length" class="stack waiting">
            <h3>{{ t('evening.bar.pending') }}</h3>
            <p v-for="waiting in pending" :key="waiting.order.id" class="muted">{{ formatEuro(waiting.total) }} · {{ waiting.order.note || t('evening.order.noNote') }}</p>
          </div>
        </section>
      </div>

      <div v-show="tab === 'ready'" class="stack">
        <p class="muted">{{ t('evening.bar.readyHint') }}</p>
        <p v-if="!screen.orders.ready.length" class="muted empty">{{ t('evening.bar.noReady') }}</p>
        <div class="tickets">
          <OrderTicket v-for="order in screen.orders.ready" :key="order.id" :order="order">
            <template #state>{{ t('evening.bar.readySince', { n: waitMinutes(order.readyAt ?? order.takenAt, now) }) }}</template>
            <BaseButton :pending="action.pending.value" @click="bar.deliver(order)">{{ t('evening.bar.deliver') }}</BaseButton>
          </OrderTicket>
        </div>
        <section v-if="screen.orders.delivered.length" class="stack" aria-labelledby="delivered-title">
          <h2 id="delivered-title">{{ t('evening.bar.deliveredTitle') }}</h2>
          <ul class="handled">
            <li v-for="order in screen.orders.delivered" :key="order.id">
              <strong>{{ t('evening.order.number', { number: order.number }) }}</strong> · {{ order.note ?? t('evening.order.noNote') }}
              <span v-if="order.deliveredAt" class="muted">{{ order.deliveredBy ? t('evening.bar.deliveredAt', { time: time(order.deliveredAt), name: order.deliveredBy }) : t('evening.kitchen.deliveredAt', { time: time(order.deliveredAt) }) }}</span>
              <span class="locked">{{ t('evening.bar.locked') }}</span>
            </li>
          </ul>
        </section>
      </div>

      <div v-show="tab === 'kitchen'" class="stack">
        <p class="muted">{{ t('evening.bar.kitchenHint') }}</p>
        <p v-if="!screen.orders.waiting.length" class="muted empty">{{ t('evening.bar.noWaiting') }}</p>
        <div class="tickets">
          <OrderTicket v-for="order in screen.orders.waiting" :key="order.id" :order="order" kitchen-only>
            <template #state>{{ t('evening.order.wait', { n: waitMinutes(order.takenAt, now) }) }}</template>
            <BaseButton variant="ghost" :disabled="action.pending.value" @click="bar.cancel(order)">{{ t('evening.bar.cancel') }}</BaseButton>
          </OrderTicket>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.tab {
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.tab[aria-pressed='true'] {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 14%, transparent);
}

.layout {
  display: grid;
  gap: 1rem;
  align-items: start;
}

@media (width >= 64rem) {
  .layout {
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  }
}

/* Commande toujours visible sur grand écran, seulement si la hauteur laisse la place */
@media (width >= 64rem) and (height >= 40rem) {
  .summary {
    position: sticky;
    top: 5rem;
  }
}

.card h2,
.stack h2,
.waiting h3 {
  margin: 0;
}

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.item {
  display: grid;
  gap: 0.5rem;
}

.item--out .item__head strong {
  color: var(--color-muted);
  text-decoration: line-through;
}

.item__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
  margin: 0;
}

.item__hint {
  color: var(--badge-gold);
  font-size: 0.875rem;
  font-weight: 600;
}

.item__formats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
  gap: 0.5rem;
}

.garnished {
  display: grid;
  gap: 0.5rem;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.chip {
  min-height: 2.75rem;
  padding: 0 0.9rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.chip[aria-pressed='true'] {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 14%, transparent);
}

.chip--out {
  color: var(--color-muted);
  text-decoration: line-through;
  cursor: default;
}

.add {
  justify-self: start;
  min-height: 3rem;
  padding: 0 1rem;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  touch-action: manipulation;
}

.add:disabled {
  cursor: default;
  opacity: 0.5;
}

.summary__total {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
  margin: 0;
}

.summary__total strong {
  font-size: clamp(1.8rem, 1.5rem + 1.2vw, 2.4rem);
  font-variant-numeric: tabular-nums;
}

.summary__tokens {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  font-weight: 700;
}

.summary__token {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.summary__rest {
  margin: 0;
}

.lines {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.line__what {
  flex: 1 1 auto;
  min-width: 0;
}

.line__garnish {
  display: block;
  color: var(--badge-teal);
  font-size: 0.85rem;
  font-weight: 600;
}

.line__price {
  font-variant-numeric: tabular-nums;
}

.line__remove {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.waiting p {
  margin: 0;
}

.tickets {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr));
  gap: 0.75rem;
}

.empty {
  margin: 0;
}

.handled {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.handled li {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.locked {
  margin-inline-start: auto;
  color: var(--color-success);
  font-weight: 700;
}
</style>
