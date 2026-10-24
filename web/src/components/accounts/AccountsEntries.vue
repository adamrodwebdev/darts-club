<script setup lang="ts">
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatEuro } from '@/i18n/format'
import type { AccountsEntry } from '@/models/EventAccounts'

defineProps<{
  title: string
  addLabel: string
  entries: readonly AccountsEntry[]
  /** Total réel de ces lignes (tiré des totaux de l'API) */
  total: string
  editable: boolean
}>()
const emit = defineEmits<{ add: []; edit: [entry: AccountsEntry] }>()
const { t } = useI18n()
const id = useId()
</script>

<template>
  <section class="card stack" :aria-labelledby="id">
    <h2 :id="id">{{ title }}</h2>
    <p v-if="!entries.length" class="muted">{{ t('events.accounts.noEntries') }}</p>
    <ul v-else class="lines">
      <li v-for="entry in entries" :key="entry.id" class="line">
        <span class="line__label">
          {{ entry.label }}
          <span v-if="entry.actual === null || entry.actual !== entry.planned" class="muted">· {{ t('events.accounts.planned', { amount: formatEuro(entry.planned) }) }}</span>
        </span>
        <span class="line__amount">{{ entry.actual === null ? t('events.accounts.unknown') : formatEuro(entry.actual) }}</span>
        <button v-if="editable" type="button" class="ghost-button no-print" :aria-label="t('events.accounts.editLabel', { name: entry.label })" @click="emit('edit', entry)">
          {{ t('events.accounts.edit') }}
        </button>
      </li>
      <li class="line line--total">
        <span class="line__label">{{ t('events.accounts.total') }}</span>
        <span class="line__amount">{{ formatEuro(total) }}</span>
      </li>
    </ul>
    <button v-if="editable" type="button" class="add-button no-print" @click="emit('add')">{{ addLabel }}</button>
  </section>
</template>

<style scoped>
h2 {
  margin: 0;
  font-size: 1.15rem;
}

.lines {
  margin: 0;
  padding: 0;
  list-style: none;
  font-variant-numeric: tabular-nums;
}

.line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.75rem;
  min-height: 2.75rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--color-surface-2);
}

.line__label {
  flex: 1 1 10rem;
  min-width: 0;
  overflow-wrap: anywhere;
}

.line__label .muted {
  font-size: 0.85rem;
}

.line__amount {
  font-weight: 600;
}

.line--total {
  border-bottom: 0;
  font-weight: 800;
}

.ghost-button,
.add-button {
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

.add-button {
  justify-self: start;
  border-style: dashed;
}
</style>
