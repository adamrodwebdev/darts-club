<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AccountsEntries from '@/components/accounts/AccountsEntries.vue'
import AccountsSummary from '@/components/accounts/AccountsSummary.vue'
import BarItemDialog from '@/components/accounts/BarItemDialog.vue'
import BarItemsList from '@/components/accounts/BarItemsList.vue'
import EntryDialog from '@/components/accounts/EntryDialog.vue'
import ReceiptDialog from '@/components/accounts/ReceiptDialog.vue'
import ReceiptsPanel from '@/components/accounts/ReceiptsPanel.vue'
import YearCounters from '@/components/accounts/YearCounters.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import type { AccountsEntry, BarItem, EntryKind, EventAccounts, Receipt } from '@/models/EventAccounts'
import { useServices } from '@/services'
import type { BarItemDraft, EntryDraft, ReceiptDraft } from '@/services/EventAccountsService'

const props = defineProps<{ id: string }>()
const services = useServices()
const { t } = useI18n()

const accounts = ref<EventAccounts | null>(null)
const load = useAsyncAction()
const action = useAsyncAction()

/** Fenêtres ouvertes : article, ligne (dépense ou recette), justificatif, clôture */
const itemDialog = ref<{ item: BarItem | null } | null>(null)
const entryDialog = ref<{ entry: AccountsEntry | null; kind: EntryKind } | null>(null)
const receiptDialog = ref(false)
const closing = ref(false)
const dialogOpen = computed(() => itemDialog.value !== null || entryDialog.value !== null || receiptDialog.value || closing.value)

onMounted(async () => {
  const loaded = await load.run(() => services.eventAccounts.get(props.id))
  if (loaded) accounts.value = loaded
})

/** Chaque modification renvoie le bilan à jour : rien n'est recalculé ici */
async function apply(change: () => Promise<EventAccounts>): Promise<boolean> {
  const updated = await action.run(change)
  if (!updated) return false
  accounts.value = updated
  return true
}

function openDialog(open: () => void): void {
  action.error.value = null
  action.fieldErrors.value = {}
  open()
}

async function saveItem(draft: BarItemDraft): Promise<void> {
  const item = itemDialog.value?.item
  const ok = await apply(() => (item ? services.eventAccounts.changeItem(item.id, draft) : services.eventAccounts.addItem(props.id, draft)))
  if (ok) itemDialog.value = null
}

async function removeItem(): Promise<void> {
  const item = itemDialog.value?.item
  if (item && (await apply(() => services.eventAccounts.removeItem(item.id)))) itemDialog.value = null
}

async function saveEntry(draft: EntryDraft): Promise<void> {
  const entry = entryDialog.value?.entry
  const ok = await apply(() => (entry ? services.eventAccounts.changeEntry(entry.id, draft) : services.eventAccounts.addEntry(props.id, draft)))
  if (ok) entryDialog.value = null
}

async function removeEntry(): Promise<void> {
  const entry = entryDialog.value?.entry
  if (entry && (await apply(() => services.eventAccounts.removeEntry(entry.id)))) entryDialog.value = null
}

async function saveReceipt(draft: ReceiptDraft): Promise<void> {
  if (await apply(() => services.eventAccounts.addReceipt(props.id, draft))) receiptDialog.value = false
}

async function removeReceipt(receipt: Receipt): Promise<void> {
  await apply(() => services.eventAccounts.removeReceipt(receipt.id))
}

async function changeFlags(flags: { temporaryBar: boolean; taxExempt: boolean }): Promise<void> {
  await apply(() => services.eventAccounts.changeFlags(props.id, flags))
}

async function closeAccounts(): Promise<void> {
  if (await apply(() => services.eventAccounts.close(props.id))) closing.value = false
}

async function reopen(): Promise<void> {
  await apply(() => services.eventAccounts.reopen(props.id))
}

/** Seul le bilan part à l'impression (sans en-tête ni boutons) */
function print(): void {
  const root = document.documentElement
  root.dataset.print = 'accounts'
  window.addEventListener('afterprint', () => root.removeAttribute('data-print'), { once: true })
  window.print()
}

/** Totaux des autres lignes, tirés des totaux de l'API (total moins buvette, en centimes) */
function difference(total: string, bar: string): string {
  return ((Math.round(Number(total) * 100) - Math.round(Number(bar) * 100)) / 100).toFixed(2)
}

const expensesTotal = computed(() => (accounts.value ? difference(accounts.value.totals.costs, accounts.value.totals.barCosts) : '0'))
const incomesTotal = computed(() => (accounts.value ? difference(accounts.value.totals.income, accounts.value.totals.barIncome) : '0'))

const status = computed(() => {
  const a = accounts.value
  if (!a?.closed || !a.closedAt) return t('events.accounts.open')
  const date = formatDate(a.closedAt, { dateStyle: 'long' })
  return a.closedBy ? t('events.accounts.closedBy', { date, name: a.closedBy }) : t('events.accounts.closedOn', { date })
})
</script>

<template>
  <section class="page stack accounts-page">
    <RouterLink :to="`/evenements/${id}`" class="back no-print">{{ accounts ? t('events.accounts.back', { title: accounts.eventTitle }) : t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="accounts">
      <div class="head">
        <div class="head__title">
          <p class="eyebrow">{{ accounts.eventTitle }} · {{ formatDate(accounts.eventStartsAt, { dateStyle: 'long', timeZone: CLUB_TIME_ZONE }) }}</p>
          <h1>{{ t('events.accounts.title') }}</h1>
          <p class="muted rights">{{ accounts.mayEdit ? t('events.accounts.rights') : t('events.accounts.readOnly') }}</p>
        </div>
        <div class="head__actions">
          <span class="pill" :class="{ 'pill--closed': accounts.closed }">{{ status }}</span>
          <a class="link-button no-print" :href="services.eventAccounts.exportUrl(id)" download>{{ t('events.accounts.export') }}</a>
          <button type="button" class="link-button no-print" @click="print">{{ t('events.accounts.print') }}</button>
          <BaseButton v-if="accounts.editable" class="no-print" @click="openDialog(() => (closing = true))">{{ t('events.accounts.close') }}</BaseButton>
          <BaseButton v-if="accounts.closed && accounts.mayReopen" variant="ghost" class="no-print" :pending="action.pending.value" @click="reopen">
            {{ t('events.accounts.reopen') }}
          </BaseButton>
        </div>
      </div>

      <p v-if="action.error.value && !dialogOpen" class="notice notice--error" role="alert">{{ action.error.value }}</p>

      <AccountsSummary :accounts="accounts" />
      <BarItemsList :accounts="accounts" @add="openDialog(() => (itemDialog = { item: null }))" @edit="(item) => openDialog(() => (itemDialog = { item }))" />

      <div class="columns">
        <AccountsEntries
          :title="t('events.accounts.expenses')"
          :add-label="t('events.accounts.addExpense')"
          :entries="accounts.expenses"
          :total="expensesTotal"
          :editable="accounts.editable"
          @add="openDialog(() => (entryDialog = { entry: null, kind: 'depense' }))"
          @edit="(entry) => openDialog(() => (entryDialog = { entry, kind: entry.kind }))"
        />
        <AccountsEntries
          :title="t('events.accounts.incomes')"
          :add-label="t('events.accounts.addIncome')"
          :entries="accounts.incomes"
          :total="incomesTotal"
          :editable="accounts.editable"
          @add="openDialog(() => (entryDialog = { entry: null, kind: 'recette' }))"
          @edit="(entry) => openDialog(() => (entryDialog = { entry, kind: entry.kind }))"
        />
      </div>

      <div class="columns columns--wide">
        <ReceiptsPanel :receipts="accounts.receipts" :editable="accounts.editable" @add="openDialog(() => (receiptDialog = true))" @remove="removeReceipt" />
        <YearCounters :accounts="accounts" :pending="action.pending.value" @change="changeFlags" />
      </div>
    </template>

    <BarItemDialog
      v-if="itemDialog"
      :item="itemDialog.item"
      :pending="action.pending.value"
      :error="action.error.value"
      :field-errors="action.fieldErrors.value"
      @save="saveItem"
      @remove="removeItem"
      @close="itemDialog = null"
    />
    <EntryDialog
      v-if="entryDialog"
      :entry="entryDialog.entry"
      :kind="entryDialog.kind"
      :pending="action.pending.value"
      :error="action.error.value"
      :field-errors="action.fieldErrors.value"
      @save="saveEntry"
      @remove="removeEntry"
      @close="entryDialog = null"
    />
    <ReceiptDialog v-if="receiptDialog" :pending="action.pending.value" :error="action.error.value" @save="saveReceipt" @close="receiptDialog = false" />
    <BaseDialog v-if="closing" :title="t('events.accounts.closeTitle')" @close="closing = false">
      <form class="stack" @submit.prevent="closeAccounts">
        <p class="muted dialog-text">{{ t('events.accounts.closeText') }}</p>
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="closing = false">{{ t('common.cancel') }}</BaseButton>
          <BaseButton type="submit" :pending="action.pending.value">{{ t('events.accounts.close') }}</BaseButton>
        </div>
      </form>
    </BaseDialog>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

.head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.head__title {
  display: grid;
  gap: 0.3rem;
}

.head h1 {
  margin: 0;
}

.eyebrow {
  margin: 0;
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.rights {
  margin: 0;
  font-size: 0.9rem;
}

.head__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}

.pill {
  padding: 0.3rem 0.8rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.85rem;
  font-weight: 700;
}

.pill--closed {
  border-color: var(--color-success);
  color: var(--color-success);
}

.link-button {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
  gap: 1rem;
  align-items: start;
}

.columns--wide {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));
}

.dialog-text {
  margin: 0;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>

<!-- Impression du bilan : ni en-tête du site, ni pied de page, ni boutons -->
<style>
@media print {
  html[data-print='accounts'] body .header,
  html[data-print='accounts'] body .footer,
  html[data-print='accounts'] .accounts-page .no-print {
    display: none !important;
  }
}
</style>
