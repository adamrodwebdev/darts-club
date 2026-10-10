<script setup lang="ts">
import { onMounted, ref } from 'vue'
import MembershipStatusPill from '@/components/MembershipStatusPill.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatEuro, type MembershipRecord } from '@/models/Membership'
import { services } from '@/services'

const season = ref('')
const current = ref<MembershipRecord | null>(null)
const history = ref<MembershipRecord[]>([])
const load = useAsyncAction()
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' })

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
      <h2 id="current-title">Saison {{ season }}</h2>

      <template v-if="current">
        <MembershipStatusPill :membership="current" />
        <dl class="figures">
          <div><dt>Montant</dt><dd>{{ formatEuro(current.amountDue) }}</dd></div>
          <div><dt>Réglé</dt><dd>{{ formatEuro(current.paid) }}</dd></div>
          <div><dt>Reste</dt><dd>{{ formatEuro(current.remaining) }}</dd></div>
          <div><dt>Échéance</dt><dd>{{ dateFormat.format(new Date(current.dueDate)) }}</dd></div>
        </dl>
        <p v-if="current.status.id === 'payee'" class="notice notice--success">Merci ! Ton badge « Cotisant » est visible des autres adhérents.</p>
        <p v-else class="muted">Règle ta cotisation auprès du trésorier (espèces, chèque ou virement). Tu peux lui écrire depuis la messagerie, boîte « Trésorerie ».</p>

        <h3>Paiements</h3>
        <p v-if="!current.payments.length" class="muted">Aucun paiement enregistré.</p>
        <ul v-else class="payments">
          <li v-for="p in current.payments" :key="p.id" :class="{ cancelled: p.cancelled }">
            {{ dateFormat.format(new Date(p.paidAt)) }} · {{ p.method.label }} · <strong>{{ formatEuro(p.amount) }}</strong>
            <span v-if="p.cancelled"> (annulé)</span>
          </li>
        </ul>
      </template>
      <p v-else class="muted">La cotisation de cette saison n’est pas encore ouverte.</p>
    </section>

    <section v-if="history.length" class="card" aria-labelledby="history-title">
      <h2 id="history-title">Saisons précédentes</h2>
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
