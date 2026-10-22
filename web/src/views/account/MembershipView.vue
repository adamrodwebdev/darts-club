<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MembershipStatusPill from '@/components/MembershipStatusPill.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { enumLabel } from '@/i18n'
import { formatDate } from '@/i18n/format'
import { formatEuro, type MembershipRecord } from '@/models/Membership'
import { useServices } from '@/services'

const services = useServices()

const { t } = useI18n()
const season = ref('')
const current = ref<MembershipRecord | null>(null)
const history = ref<MembershipRecord[]>([])
const load = useAsyncAction()

onMounted(async () => {
  const data = await load.run(() => services.treasury.mine())
  if (data) {
    season.value = data.season
    current.value = data.current
    history.value = data.history.filter((m) => m.season !== data.season)
  }
})
</script>

<template>
  <div class="stack">
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <section v-if="!load.pending.value" class="card stack" aria-labelledby="current-title">
      <h2 id="current-title">{{ t('account.membership.season', { season }) }}</h2>

      <template v-if="current">
        <MembershipStatusPill :membership="current" />
        <dl class="figures">
          <div><dt>{{ t('account.membership.amount') }}</dt><dd>{{ formatEuro(current.amountDue) }}</dd></div>
          <div><dt>{{ t('account.membership.paid') }}</dt><dd>{{ formatEuro(current.paid) }}</dd></div>
          <div><dt>{{ t('account.membership.remaining') }}</dt><dd>{{ formatEuro(current.remaining) }}</dd></div>
          <div><dt>{{ t('account.membership.dueDate') }}</dt><dd>{{ formatDate(new Date(current.dueDate)) }}</dd></div>
        </dl>
        <p v-if="current.status.id === 'payee'" class="notice notice--success">
          {{ t('account.membership.thanks', { badge: enumLabel('badge', 'cotisant') }) }}
        </p>
        <p v-else class="muted">{{ t('account.membership.howToPay') }}</p>

        <h3>{{ t('account.membership.payments') }}</h3>
        <p v-if="!current.payments.length" class="muted">{{ t('account.membership.noPayments') }}</p>
        <ul v-else class="payments">
          <li v-for="p in current.payments" :key="p.id" :class="{ cancelled: p.cancelled }">
            {{ formatDate(new Date(p.paidAt)) }} · {{ p.method.label }} · <strong>{{ formatEuro(p.amount) }}</strong>
            <span v-if="p.cancelled"> {{ t('account.membership.cancelled') }}</span>
          </li>
        </ul>
      </template>
      <p v-else class="muted">{{ t('account.membership.notOpen') }}</p>
    </section>

    <section v-if="history.length" class="card" aria-labelledby="history-title">
      <h2 id="history-title">{{ t('account.membership.history') }}</h2>
      <ul class="payments">
        <li v-for="m in history" :key="m.id">
          {{ m.season }} · {{ formatEuro(m.amountDue) }} · <MembershipStatusPill :membership="{ status: m.status, overdue: false }" />
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.figures {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.75rem;
  margin: 0;
}

.figures div {
  padding: 0.75rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.figures dt {
  color: var(--color-muted);
  font-size: 0.85rem;
}

.figures dd {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 800;
}

h3 {
  margin: 0.5rem 0 0;
  font-size: 1rem;
}

.payments {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cancelled {
  color: var(--color-muted);
  text-decoration: line-through;
}
</style>
