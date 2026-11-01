<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import ChangeHelper from '@/components/evening/ChangeHelper.vue'
import RecentSales from '@/components/evening/RecentSales.vue'
import ScreenHeader from '@/components/evening/ScreenHeader.vue'
import TokenStepper from '@/components/evening/TokenStepper.vue'
import { useEntrance } from '@/composables/useEntrance'
import { formatEuro } from '@/i18n/format'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const { screen, pending, counts, given, online, toast, total, change, summary, load, action, add, sell, cancel } = useEntrance(props.id)
</script>

<template>
  <section class="page stack entrance">
    <RouterLink :to="`/evenements/${id}`" class="back">{{ screen ? t('events.accounts.back', { title: screen.eventTitle }) : t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="screen">
      <ScreenHeader :title="t('evening.entrance.title')" :event-title="screen.eventTitle" :starts-at="screen.eventStartsAt" :online="online" />
      <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>

      <p v-if="!screen.tokensEnabled" class="notice">{{ t('evening.entrance.noTokens') }}</p>

      <div v-else class="layout">
        <section class="card stack sale" aria-labelledby="sale-title">
          <div class="sale__head">
            <h2 id="sale-title">{{ t('evening.entrance.sale') }}</h2>
            <p class="muted">{{ t('evening.entrance.saleHint') }}</p>
          </div>
          <ul class="products">
            <TokenStepper v-for="token in screen.tokens" :key="token.name" :token="token" :count="counts[token.name] ?? 0" @change="(delta) => add(token.name, delta)" />
          </ul>

          <div class="due">
            <span class="due__label">{{ t('evening.entrance.due') }}</span>
            <strong class="due__total" aria-live="polite">{{ formatEuro(total / 100) }}</strong>
            <p class="muted due__summary">{{ summary || t('evening.entrance.pick') }}</p>
          </div>

          <ChangeHelper v-model="given" :change="change" :total="total" />

          <div class="pay">
            <BaseButton :disabled="total === 0" @click="sell('especes')">{{ t('evening.entrance.cash') }}</BaseButton>
            <BaseButton :disabled="total === 0" @click="sell('carte')">{{ t('evening.entrance.card') }}</BaseButton>
          </div>
          <p v-if="toast" class="notice notice--success" role="status">{{ toast }}</p>
        </section>

        <div class="side stack">
          <section class="card stack" aria-labelledby="latest-title">
            <h2 id="latest-title">{{ t('evening.entrance.latest') }}</h2>
            <RecentSales :sales="screen.sales" :pending="pending" :busy="action.pending.value" @cancel="cancel" />
          </section>

          <section class="card stack" aria-labelledby="drawer-title">
            <h2 id="drawer-title">{{ t('evening.entrance.drawer') }}</h2>
            <dl class="drawer">
              <div>
                <dt>{{ t('evening.entrance.cash') }}</dt>
                <dd>{{ formatEuro(screen.taken.cash) }}</dd>
              </div>
              <div>
                <dt>{{ t('evening.entrance.card') }}</dt>
                <dd>{{ formatEuro(screen.taken.card) }}</dd>
              </div>
              <div>
                <dt>{{ t('evening.entrance.float') }}</dt>
                <dd>{{ formatEuro(screen.cashFloat) }}</dd>
              </div>
            </dl>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

.layout {
  display: grid;
  gap: 1rem;
  align-items: start;
}

@media (width >= 64rem) {
  .layout {
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  }
}

.card h2 {
  margin: 0;
}

.sale__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 1rem;
}

.sale__head p {
  margin: 0;
}

.products {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.due {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: baseline;
  gap: 0.15rem 1rem;
  padding: 0.75rem 1rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.due__label {
  font-weight: 700;
}

.due__total {
  font-size: clamp(1.8rem, 1.5rem + 1.2vw, 2.4rem);
  font-variant-numeric: tabular-nums;
}

.due__summary {
  grid-column: 1 / -1;
  margin: 0;
}

.pay {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.pay :deep(.btn) {
  min-height: 3.25rem;
  font-size: 1.05rem;
}

.drawer {
  display: grid;
  gap: 0.5rem;
  margin: 0;
}

.drawer div {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.drawer dd {
  margin: 0;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
</style>
