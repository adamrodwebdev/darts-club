<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStockQuantity } from '@/composables/useStockQuantity'
import { formatEuro } from '@/i18n/format'
import type { ServiceFormat, ServiceItem } from '@/models/EveningService'

/** Un format à la prise de commande : « Verre 25 cl · 2,00 € », moins / quantité / plus */
const props = defineProps<{ item: ServiceItem; format: ServiceFormat; count: number }>()
const emit = defineEmits<{ change: [delta: number] }>()
const { t } = useI18n()
const quantity = useStockQuantity()

const label = computed(() =>
  props.item.unit === 'litre' && props.format.volume !== null ? `${props.format.name} ${quantity.format('litre', props.format.volume)}` : props.format.name,
)
const aria = computed(() => (props.item.formats.length > 1 ? `${props.item.label} · ${label.value}` : props.item.label))
</script>

<template>
  <div class="format" :class="{ 'format--on': count > 0 }">
    <span class="format__label">
      <strong>{{ label }}</strong>
      <span class="muted">{{ formatEuro(format.price) }}</span>
    </span>
    <button type="button" class="step" :disabled="count === 0" :aria-label="t('evening.bar.remove', { name: aria })" @click="emit('change', -1)">
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></svg>
    </button>
    <output class="format__count">{{ count }}</output>
    <button type="button" class="step" :disabled="item.soldOut" :aria-label="t('evening.bar.add', { name: aria })" @click="emit('change', 1)">
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></svg>
    </button>
  </div>
</template>

<style scoped>
.format {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.35rem 0.35rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.format--on {
  border-color: var(--color-accent);
}

.format__label {
  display: grid;
  flex: 1 1 auto;
  min-width: 0;
}

.format__label .muted {
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
}

.step {
  display: grid;
  flex: none;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  touch-action: manipulation;
}

.step:disabled {
  cursor: default;
  opacity: 0.45;
}

.format__count {
  min-width: 2ch;
  font-size: 1.3rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  text-align: center;
}
</style>
