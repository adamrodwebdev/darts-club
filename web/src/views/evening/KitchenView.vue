<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import OrderTicket from '@/components/evening/OrderTicket.vue'
import ScreenHeader from '@/components/evening/ScreenHeader.vue'
import { useKitchen } from '@/composables/useKitchen'
import { useStockQuantity } from '@/composables/useStockQuantity'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import type { EveningOrder, ServiceItem } from '@/models/EveningService'
import { LATE_AFTER_MINUTES, waitMinutes } from '@/models/OrderRules'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const quantity = useStockQuantity()
const kitchen = useKitchen(props.id)
const { screen, sound, toast, now, load, action, wakeLock } = kitchen

const time = (date: Date): string => formatDate(date, { hour: '2-digit', minute: '2-digit', timeZone: CLUB_TIME_ZONE })

function sentState(order: EveningOrder): string {
  if (order.kitchen === 'remise' && order.deliveredAt) return t('evening.kitchen.deliveredAt', { time: time(order.deliveredAt) })
  return order.readyAt ? t('evening.kitchen.readyAt', { time: time(order.readyAt) }) : ''
}

function stockDetail(item: ServiceItem): string {
  if (item.markedSoldOut) return t('evening.kitchen.hidden')
  if (item.soldOut) return t('evening.bar.soldOut')
  return item.left === null ? '' : t('evening.bar.left', { n: quantity.withUnit(item.unit, item.left) })
}
</script>

<template>
  <section class="page stack service">
    <RouterLink :to="`/evenements/${id}`" class="back">{{ screen ? t('events.accounts.back', { title: screen.event.title }) : t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value && !screen" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="screen">
      <ScreenHeader :title="t('evening.kitchen.title')" :event-title="screen.event.title" :starts-at="screen.event.startsAt" :online="!load.error.value" />

      <div class="toggles">
        <button type="button" class="toggle" :aria-pressed="sound" @click="kitchen.toggleSound()">{{ sound ? t('evening.kitchen.soundOn') : t('evening.kitchen.soundOff') }}</button>
        <button v-if="wakeLock.supported" type="button" class="toggle" :aria-pressed="wakeLock.active.value" @click="wakeLock.toggle()">
          {{ wakeLock.active.value ? t('evening.kitchen.awakeOn') : t('evening.kitchen.awakeOff') }}
        </button>
      </div>

      <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
      <p v-if="toast" class="notice notice--success" role="status">{{ toast }}</p>

      <section class="stack" aria-labelledby="pending-title">
        <div class="section-head">
          <h2 id="pending-title">{{ t('evening.kitchen.pending', { n: screen.pending.length }) }}</h2>
          <span class="muted">{{ t('evening.kitchen.oldestFirst') }}</span>
        </div>
        <p v-if="!screen.pending.length" class="muted empty">{{ t('evening.kitchen.none') }}</p>
        <div class="tickets">
          <OrderTicket v-for="order in screen.pending" :key="order.id" :order="order" kitchen-only :late="waitMinutes(order.takenAt, now) >= LATE_AFTER_MINUTES">
            <template #state>
              {{ t('evening.order.wait', { n: waitMinutes(order.takenAt, now) }) }}
              <strong v-if="waitMinutes(order.takenAt, now) >= LATE_AFTER_MINUTES" class="late">{{ t('evening.kitchen.late') }}</strong>
            </template>
            <BaseButton :pending="action.pending.value" @click="kitchen.ready(order)">{{ t('evening.kitchen.ready') }}</BaseButton>
          </OrderTicket>
        </div>
      </section>

      <div class="columns">
        <section class="card stack" aria-labelledby="sent-title">
          <h2 id="sent-title">{{ t('evening.kitchen.sent') }}</h2>
          <p v-if="!screen.sent.length" class="muted">{{ t('evening.kitchen.noneSent') }}</p>
          <ul v-else class="handled">
            <li v-for="order in screen.sent" :key="order.id">
              <strong>{{ t('evening.order.number', { number: order.number }) }}</strong> · {{ order.note ?? t('evening.order.noNote') }}
              <span class="muted">{{ sentState(order) }}</span>
            </li>
          </ul>
        </section>

        <section class="card stack" aria-labelledby="stock-title">
          <h2 id="stock-title">{{ t('evening.kitchen.stock') }}</h2>
          <p v-if="!screen.stock.length" class="muted">{{ t('evening.bar.noFood') }}</p>
          <div v-for="item in screen.stock" :key="item.id" class="stock" :class="{ 'stock--out': item.soldOut }">
            <p class="stock__head">
              <strong>{{ item.label }}</strong>
              <span class="muted">{{ stockDetail(item) }}</span>
              <button
                type="button"
                class="toggle"
                :aria-pressed="item.markedSoldOut"
                :aria-label="item.markedSoldOut ? t('evening.kitchen.backLabel', { name: item.label }) : t('evening.kitchen.outLabel', { name: item.label })"
                @click="kitchen.setAvailability(item, { soldOut: !item.markedSoldOut })"
              >
                {{ item.markedSoldOut ? t('evening.kitchen.back') : t('evening.kitchen.out') }}
              </button>
            </p>
            <div v-if="item.garnishes.length" class="garnishes" role="group" :aria-label="t('evening.bar.garnishes', { name: item.label })">
              <button
                v-for="garnish in item.garnishes"
                :key="garnish.name"
                type="button"
                class="chip"
                :aria-pressed="garnish.out"
                :aria-label="garnish.out ? t('evening.kitchen.backLabel', { name: garnish.name }) : t('evening.kitchen.outLabel', { name: garnish.name })"
                @click="kitchen.setAvailability(item, { garnish: garnish.name })"
              >
                {{ garnish.out ? t('evening.bar.garnishOut', { name: garnish.name }) : garnish.name }}
              </button>
            </div>
          </div>
        </section>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

.toggles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.toggle,
.chip {
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.toggle[aria-pressed='true'] {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 14%, transparent);
}

.chip[aria-pressed='true'] {
  border-color: var(--badge-gold);
  color: var(--color-muted);
  text-decoration: line-through;
}

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.section-head h2,
.card h2 {
  margin: 0;
}

.empty {
  margin: 0;
}

.tickets {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr));
  gap: 0.75rem;
}

.tickets :deep(.btn) {
  min-height: 3.25rem;
  min-width: 8rem;
  font-size: 1.05rem;
}

.late {
  margin-inline-start: 0.5rem;
  color: var(--badge-gold);
}

.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
  gap: 1rem;
  align-items: start;
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

.stock {
  display: grid;
  gap: 0.5rem;
}

.stock__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.75rem;
  margin: 0;
}

.stock__head .muted {
  flex: 1 1 auto;
  font-size: 0.875rem;
}

.stock--out .stock__head strong {
  color: var(--color-muted);
  text-decoration: line-through;
}

.garnishes {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
