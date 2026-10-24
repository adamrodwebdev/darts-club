<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TicketSeries } from '@/models/EventDocuments'

const props = defineProps<{
  tickets: TicketSeries
  /** Toute la série (impression) ; sinon les premiers billets (aperçu) */
  all: boolean
}>()
const { t } = useI18n()

const PREVIEW = 4

const numbers = computed(() => {
  const count = props.all ? props.tickets.count : Math.min(PREVIEW, props.tickets.count)
  return Array.from({ length: count }, (_, index) => props.tickets.first + index)
})

/** Numéros de même largeur dans toute la série (« 001 » … « 100 ») */
const digits = computed(() => Math.max(3, String(props.tickets.first + props.tickets.count - 1).length))

function numberLabel(value: number): string {
  return t('events.documents.tickets.number', { number: String(value).padStart(digits.value, '0') })
}
</script>

<template>
  <!-- Billets en français (public de la soirée), quelle que soit la langue de l'écran -->
  <div class="tickets" lang="fr">
    <article v-for="value in numbers" :key="value" class="ticket">
      <div class="ticket__stub">
        <strong>{{ numberLabel(value) }}</strong>
        <span>{{ tickets.category }}</span>
        <span>{{ tickets.price }}</span>
      </div>
      <div class="ticket__body">
        <strong class="ticket__organizer">{{ tickets.organizer }}</strong>
        <span>{{ tickets.event }}</span>
        <span class="ticket__meta">{{ t('events.documents.tickets.meta', { category: tickets.category, price: tickets.price }) }}</span>
        <strong class="ticket__number">{{ numberLabel(value) }}</strong>
      </div>
    </article>
  </div>
</template>

<style scoped>
.tickets {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
  gap: 0.75rem;
}

.ticket {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 7fr);
  min-height: 9rem;
  border: 1px dashed var(--paper-rule);
  background: var(--paper);
  color: var(--paper-ink);
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 0.85rem;
  line-height: 1.35;
}

.ticket__stub,
.ticket__body {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.75rem;
}

.ticket__stub {
  justify-content: center;
  border-right: 1px dashed var(--paper-rule);
  color: var(--paper-ink-muted);
  text-align: center;
}

.ticket__stub strong {
  color: var(--paper-ink);
}

.ticket__organizer {
  font-size: 1.05rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.ticket__meta {
  color: var(--paper-ink-muted);
}

.ticket__number {
  margin-top: auto;
  align-self: flex-end;
  font-size: 1.15rem;
}
</style>

<!-- Impression : 12 billets par page A4 (2 × 6), traits pointillés pour la découpe -->
<style>
@page tickets-a4 {
  size: A4 portrait;
  margin: 10mm;
}

@media print {
  html[data-print='tickets'],
  html[data-print='tickets'] body {
    background: var(--paper);
  }

  html[data-print='tickets'] body *:not(:has(.tickets), .tickets, .tickets *) {
    display: none !important;
  }

  html[data-print='tickets'] body *:has(.tickets) {
    display: block !important;
    width: auto !important;
    min-height: 0 !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: none !important;
    box-shadow: none !important;
  }

  html[data-print='tickets'] .tickets {
    page: tickets-a4;
    grid-template-columns: 1fr 1fr;
    gap: 0;
  }

  html[data-print='tickets'] .ticket {
    height: 46mm;
    min-height: 0;
    break-inside: avoid;
    font-size: 9pt;
  }
}
</style>
