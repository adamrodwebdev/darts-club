<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatDate, formatEuro } from '@/i18n/format'
import type { Receipt } from '@/models/EventAccounts'
import { useServices } from '@/services'

defineProps<{ receipts: readonly Receipt[]; editable: boolean }>()
const emit = defineEmits<{ add: []; remove: [receipt: Receipt] }>()
const { t } = useI18n()
const { eventAccounts } = useServices()

/** Avoir : montant à déduire */
function amountText(receipt: Receipt): string {
  if (receipt.amount === null) return ''
  return receipt.credit ? `−${formatEuro(receipt.amount)}` : formatEuro(receipt.amount)
}
</script>

<template>
  <section class="card stack" aria-labelledby="receipts-title">
    <div class="heading">
      <h2 id="receipts-title">{{ t('events.accounts.receipts') }}</h2>
      <p class="muted privacy">{{ t('events.accounts.receiptsPrivacy') }}</p>
    </div>

    <p v-if="!receipts.length" class="muted">{{ t('events.accounts.noReceipts') }}</p>
    <ul v-else class="receipts">
      <li v-for="receipt in receipts" :key="receipt.id" class="receipt">
        <!-- Photo ouverte dans un onglet, PDF téléchargé (jamais affiché dans le site) -->
        <a
          class="receipt__open"
          :href="eventAccounts.receiptUrl(receipt.id)"
          :target="receipt.type === 'image' ? '_blank' : undefined"
          :rel="receipt.type === 'image' ? 'noopener' : undefined"
          :download="receipt.type === 'pdf' ? '' : undefined"
        >
          <span class="receipt__type" aria-hidden="true">{{ receipt.type === 'pdf' ? t('events.accounts.typePdf') : t('events.accounts.typeImage') }}</span>
          <span class="receipt__text">
            <strong>{{ receipt.label }}</strong>
            <span class="muted">
              <template v-if="receipt.amount !== null">{{ amountText(receipt) }} · </template>
              <template v-if="receipt.credit">{{ t('events.accounts.receiptCreditTag') }} · </template>
              {{ formatDate(receipt.uploadedAt, { dateStyle: 'short' }) }}
            </span>
          </span>
          <span v-if="receipt.type === 'image'" class="visually-hidden">{{ t('common.newTab') }}</span>
        </a>
        <button v-if="editable" type="button" class="remove no-print" :aria-label="t('events.accounts.removeReceipt', { name: receipt.label })" @click="emit('remove', receipt)">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </li>
    </ul>

    <button v-if="editable" type="button" class="add-button no-print" @click="emit('add')">{{ t('events.accounts.addReceipt') }}</button>
  </section>
</template>

<style scoped>
.heading {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.heading h2 {
  margin: 0;
  font-size: 1.15rem;
}

.privacy {
  margin: 0;
  font-size: 0.85rem;
}

.receipts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.receipt {
  display: flex;
  align-items: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.receipt__open {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  min-height: 2.75rem;
  padding: 0.6rem 0.75rem;
  color: var(--color-text);
  text-decoration: none;
}

.receipt__type {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: calc(var(--radius) * 0.8);
  background: var(--color-surface-2);
  color: var(--color-muted);
  font-size: 0.7rem;
  font-weight: 800;
}

.receipt__text {
  display: grid;
  min-width: 0;
  overflow-wrap: anywhere;
}

.receipt__text .muted {
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
}

.remove {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.remove:hover {
  color: var(--color-danger);
}

.add-button {
  justify-self: start;
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
</style>
