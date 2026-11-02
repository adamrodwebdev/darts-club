<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { EveningOrder } from '@/models/EveningService'

/**
 * Ticket d'une commande : numéro, repère du client, lignes (cuisine seulement pour la cuisine),
 * une ligne d'état et les actions du poste.
 */
const props = withDefaults(defineProps<{ order: EveningOrder; kitchenOnly?: boolean; late?: boolean }>(), { kitchenOnly: false, late: false })
const { t } = useI18n()

const lines = () => (props.kitchenOnly ? props.order.lines.filter((line) => line.station === 'cuisine') : props.order.lines)
</script>

<template>
  <article class="ticket" :class="{ 'ticket--late': late }" :aria-labelledby="`ticket-${order.id}`">
    <header class="ticket__head">
      <h3 :id="`ticket-${order.id}`">{{ t('evening.order.number', { number: order.number }) }}</h3>
      <span class="ticket__who">{{ order.note ?? t('evening.order.noNote') }}</span>
      <span class="ticket__state"><slot name="state" /></span>
    </header>
    <ul class="ticket__lines">
      <li v-for="(line, index) in lines()" :key="index">
        <strong>{{ t('evening.order.line', { count: line.count, label: line.label }) }}</strong>
        <span class="muted"> · {{ line.format }}</span>
        <span v-if="line.garnishes.length" class="ticket__garnish">{{ line.garnishes.join(' + ') }}</span>
      </li>
    </ul>
    <div v-if="$slots.default" class="ticket__actions">
      <slot />
    </div>
  </article>
</template>

<style scoped>
.ticket {
  display: grid;
  gap: 0.6rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
}

.ticket--late {
  border-color: var(--badge-gold);
  box-shadow: inset 0.25rem 0 0 var(--badge-gold);
}

.ticket__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.75rem;
}

.ticket__head h3 {
  margin: 0;
  font-size: 1.3rem;
  font-variant-numeric: tabular-nums;
}

.ticket__who {
  flex: 1 1 auto;
  font-weight: 600;
}

.ticket__state {
  color: var(--color-muted);
  font-size: 0.9rem;
  font-variant-numeric: tabular-nums;
}

.ticket__lines {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 1.05rem;
}

.ticket__garnish {
  display: block;
  color: var(--badge-teal);
  font-size: 0.9rem;
  font-weight: 600;
}

.ticket__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
