<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { EventAccounts, YearCounter } from '@/models/EventAccounts'

const props = defineProps<{ accounts: EventAccounts; pending: boolean }>()
const emit = defineEmits<{ change: [flags: { temporaryBar: boolean; taxExempt: boolean }] }>()
const { t } = useI18n()

const counters = computed(() => [
  { key: 'bars', label: t('events.accounts.temporaryBars'), counter: props.accounts.temporaryBars },
  { key: 'exempt', label: t('events.accounts.taxExemptEvents'), counter: props.accounts.taxExemptEvents },
])

function share(counter: YearCounter): string {
  return `${Math.min(100, (counter.used / counter.limit) * 100)}%`
}

function toggle(flag: 'temporaryBar' | 'taxExempt', value: boolean): void {
  emit('change', { temporaryBar: props.accounts.temporaryBar, taxExempt: props.accounts.taxExempt, [flag]: value })
}
</script>

<template>
  <section class="card stack counters" aria-labelledby="counters-title">
    <h2 id="counters-title">{{ t('events.accounts.counters', { year: accounts.year }) }}</h2>

    <fieldset class="flags">
      <legend class="visually-hidden">{{ t('events.accounts.counters', { year: accounts.year }) }}</legend>
      <label class="check">
        <input
          type="checkbox"
          :checked="accounts.temporaryBar"
          :disabled="!accounts.editable || pending"
          @change="toggle('temporaryBar', ($event.target as HTMLInputElement).checked)"
        />
        {{ t('events.accounts.temporaryBar') }}
      </label>
      <label class="check">
        <input
          type="checkbox"
          :checked="accounts.taxExempt"
          :disabled="!accounts.editable || pending"
          @change="toggle('taxExempt', ($event.target as HTMLInputElement).checked)"
        />
        {{ t('events.accounts.taxExempt') }}
      </label>
    </fieldset>

    <div v-for="c in counters" :key="c.key" class="counter">
      <p class="counter__line">
        <span>{{ c.label }}</span>
        <strong>{{ t('events.accounts.counterValue', { used: c.counter.used, limit: c.counter.limit }) }}</strong>
      </p>
      <span class="bar" aria-hidden="true"><span class="bar__fill" :class="{ 'bar__fill--over': c.counter.used > c.counter.limit }" :style="{ width: share(c.counter) }" /></span>
      <p v-if="c.counter.used > c.counter.limit" class="over" role="status">{{ t('events.accounts.overLimit') }}</p>
    </div>

    <p class="muted hint">{{ t('events.accounts.countersHint') }}</p>
  </section>
</template>

<style scoped>
h2 {
  margin: 0;
  font-size: 1.05rem;
}

.flags {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.check {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.75rem;
}

.check input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.counter {
  display: grid;
  gap: 0.35rem;
}

.counter__line {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin: 0;
}

.bar {
  display: block;
  height: 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--color-surface-2);
}

.bar__fill {
  display: block;
  height: 100%;
  background: var(--badge-gold);
}

.bar__fill--over {
  background: var(--color-danger);
}

.over {
  margin: 0;
  color: var(--color-danger);
  font-weight: 700;
}

.hint {
  margin: 0;
  font-size: 0.85rem;
}
</style>
