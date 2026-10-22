<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import MembershipStatusPill from '@/components/MembershipStatusPill.vue'
import CancelPaymentDialog from '@/components/treasury/CancelPaymentDialog.vue'
import PaymentForm from '@/components/treasury/PaymentForm.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatDate } from '@/i18n/format'
import { currentSeason, formatEuro, type MembershipRecord, type Option, type PaymentRecord, type TreasuryDashboard } from '@/models/Membership'
import { useServices } from '@/services'

const services = useServices()

const { t } = useI18n()
const thisSeason = currentSeason(services.clock.now())
const seasons = [-1, 0, 1].map((offset) => {
  const start = Number(thisSeason.slice(0, 4)) + offset
  return `${start}-${start + 1}`
})

const season = ref(thisSeason)
const status = ref('')
const search = ref('')
const dashboard = ref<TreasuryDashboard | null>(null)
const memberships = ref<MembershipRecord[]>([])
const methods = ref<Option[]>([])
const selected = ref<MembershipRecord | null>(null)
const load = useAsyncAction()
const open = useAsyncAction()
const remind = useAsyncAction()
const cancelling = ref<PaymentRecord | null>(null)
const openAmount = ref('30')
const info = ref<string | null>(null)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? memberships.value.filter((m) => m.holder.displayName.toLowerCase().includes(q) || m.holder.email?.toLowerCase().includes(q)) : memberships.value
})

async function refresh(): Promise<void> {
  await load.run(async () => {
    const [board, list] = await Promise.all([
      services.treasury.dashboard(season.value),
      services.treasury.memberships(season.value, status.value || undefined),
    ])
    dashboard.value = board
    memberships.value = list.items
    methods.value = list.methods
  })
}

async function openSeason(): Promise<void> {
  const created = await open.run(() => services.treasury.openSeason(season.value, openAmount.value))
  if (created !== undefined) {
    info.value = t('treasury.dashboard.opened', { season: season.value }, created)
    await refresh()
  }
}

async function sendReminders(): Promise<void> {
  const sent = await remind.run(() => services.treasury.sendReminders())
  if (sent !== undefined) info.value = t('treasury.dashboard.reminded', sent)
}

async function onPaid(updated: MembershipRecord): Promise<void> {
  selected.value = null
  info.value = t('treasury.dashboard.paid', { name: updated.holder.displayName })
  await refresh()
}

async function onCancelled(updated: MembershipRecord): Promise<void> {
  cancelling.value = null
  info.value = t('treasury.dashboard.cancelled', { name: updated.holder.displayName })
  await refresh()
}

watch([season, status], refresh)
onMounted(refresh)
</script>

<template>
  <section class="page stack">
    <div class="head">
      <h1>{{ t('treasury.dashboard.title') }}</h1>
      <div class="filters">
        <label>
          <span class="visually-hidden">{{ t('treasury.dashboard.seasonLabel') }}</span>
          <select v-model="season">
            <option v-for="s in seasons" :key="s" :value="s">{{ t('treasury.dashboard.seasonOption', { season: s }) }}</option>
          </select>
        </label>
        <a class="link-button" :href="services.treasury.exportUrl(season)" download>{{ t('treasury.dashboard.export') }}</a>
      </div>
    </div>

    <p v-if="info" class="notice notice--success" role="status">{{ info }}</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <dl v-if="dashboard" class="tiles">
      <div><dt>{{ t('treasury.dashboard.collected') }}</dt><dd>{{ formatEuro(dashboard.collected) }}</dd><dd class="muted">{{ t('treasury.dashboard.outOf', { amount: formatEuro(dashboard.expected) }) }}</dd></div>
      <div><dt>{{ t('treasury.dashboard.upToDate') }}</dt><dd>{{ dashboard.byStatus.payee }}</dd><dd class="muted">{{ t('treasury.dashboard.outOfMembers', dashboard.members) }}</dd></div>
      <div><dt>{{ t('treasury.dashboard.partial') }}</dt><dd>{{ dashboard.byStatus.partielle }}</dd></div>
      <div class="tile--alert"><dt>{{ t('treasury.dashboard.overdue') }}</dt><dd>{{ dashboard.overdue }}</dd><dd class="muted">{{ t('treasury.dashboard.dueDate', { date: formatDate(new Date(dashboard.dueDate), { dateStyle: 'short' }) }) }}</dd></div>
    </dl>

    <details class="card">
      <summary>{{ t('treasury.dashboard.openSummary') }}</summary>
      <form class="actions" novalidate @submit.prevent="openSeason">
        <BaseField v-model="openAmount" :label="t('treasury.dashboard.openAmount')" :error="open.error.value ?? undefined" />
        <BaseButton type="submit" :pending="open.pending.value">{{ t('treasury.dashboard.openSubmit', { season }) }}</BaseButton>
      </form>
      <BaseButton variant="ghost" :pending="remind.pending.value" @click="sendReminders">{{ t('treasury.dashboard.remind') }}</BaseButton>
      <p class="muted">{{ t('treasury.dashboard.remindHint') }}</p>
    </details>

    <div class="filters">
      <BaseField v-model="search" :label="t('treasury.dashboard.search')" />
      <label class="field">
        <span>{{ t('treasury.dashboard.status') }}</span>
        <select v-model="status">
          <option value="">{{ t('treasury.dashboard.statusAll') }}</option>
          <option value="impayee">{{ t('treasury.dashboard.statusUnpaid') }}</option>
          <option value="partielle">{{ t('treasury.dashboard.statusPartial') }}</option>
          <option value="payee">{{ t('treasury.dashboard.statusPaid') }}</option>
        </select>
      </label>
    </div>

    <p v-if="!load.pending.value && !filtered.length" class="muted">{{ t('treasury.dashboard.empty') }}</p>

    <!-- Cartes sur mobile, grille alignée façon tableau sur grand écran -->
    <ul class="rows">
      <li v-for="m in filtered" :key="m.id" class="row">
        <div class="row__who">
          <strong>{{ m.holder.displayName }}</strong>
          <span class="muted">{{ m.holder.email ?? t('treasury.dashboard.deletedAccount') }}</span>
        </div>
        <MembershipStatusPill :membership="m" />
        <div class="row__money">{{ formatEuro(m.paid) }} / {{ formatEuro(m.amountDue) }}</div>
        <BaseButton v-if="m.status.id !== 'payee'" @click="selected = m">{{ t('treasury.dashboard.collect') }}</BaseButton>
        <details v-if="m.payments.length" class="row__payments">
          <summary>{{ t('treasury.dashboard.payments', m.payments.length) }}</summary>
          <ul>
            <li v-for="p in m.payments" :key="p.id" :class="{ cancelled: p.cancelled }">
              {{ formatDate(new Date(p.paidAt), { dateStyle: 'short' }) }} · {{ p.method.label }} · {{ formatEuro(p.amount) }}
              <span v-if="p.reference" class="muted">· {{ p.reference }}</span>
              <span v-if="p.cancelled" class="muted"> · {{ t('treasury.dashboard.cancelledReason', { reason: p.cancelReason }) }}</span>
              <BaseButton v-else variant="ghost" @click="cancelling = p">{{ t('common.cancel') }}</BaseButton>
            </li>
          </ul>
        </details>
      </li>
    </ul>

    <PaymentForm v-if="selected" :membership="selected" :methods="methods" @saved="onPaid" @close="selected = null" />
    <CancelPaymentDialog v-if="cancelling" :payment="cancelling" @saved="onCancelled" @close="cancelling = null" />
  </section>
</template>

<style scoped>
.head,
.filters {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.filters > * {
  flex: 1 1 12rem;
}

select {
  width: 100%;
  min-height: 2.75rem;
  padding: 0 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
}

.field {
  display: grid;
  gap: 0.35rem;
  font-weight: 600;
}

.link-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 0.75rem;
  margin: 0;
}

.tiles > div {
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.tiles dt {
  color: var(--color-muted);
  font-size: 0.85rem;
}

.tiles dd {
  margin: 0;
  font-size: clamp(1.3rem, 4vw, 1.7rem);
  font-weight: 800;
}

.tiles dd.muted {
  font-size: 0.8rem;
  font-weight: 400;
}

.tile--alert dd:not(.muted) {
  color: var(--color-danger);
}

summary {
  display: flex;
  align-items: center;
  min-height: 44px;
  font-weight: 700;
  cursor: pointer;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem;
  margin: 0.5rem 0 1rem;
}

.rows {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.row {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 0.5rem 1rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.row__who {
  display: grid;
  min-width: 0;
  overflow-wrap: anywhere;
}

.row__payments {
  grid-column: 1 / -1;
}

.row__payments ul {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cancelled {
  color: var(--color-muted);
  text-decoration: line-through;
}

@media (min-width: 900px) {
  .row {
    grid-template-columns: minmax(12rem, 2fr) auto minmax(9rem, 1fr) auto;
  }
}
</style>
