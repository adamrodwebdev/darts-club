<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatEuro, type MembershipRecord, type Option } from '@/models/Membership'
import { services } from '@/services'

const props = defineProps<{ membership: MembershipRecord; methods: Option[] }>()
const emit = defineEmits<{ saved: [MembershipRecord]; close: [] }>()

const today = new Date().toISOString().slice(0, 10)
const amount = ref(props.membership.remaining)
const method = ref(props.methods[0]?.id ?? 'especes')
const paidAt = ref(today)
const reference = ref('')
const { pending, error, fieldErrors, run } = useAsyncAction()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

// <dialog> natif : focus piégé, Échap pour fermer, accessible
onMounted(() => dialog.value?.showModal())

async function submit(): Promise<void> {
  const updated = await run(() =>
    services.treasury.recordPayment(props.membership.id, {
      amount: amount.value,
      method: method.value,
      paidAt: paidAt.value,
      reference: reference.value || undefined,
    }),
  )
  if (updated) emit('saved', updated)
}
</script>

<template>
  <dialog ref="dialog" class="dialog" aria-labelledby="payment-title" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="submit">
      <h2 id="payment-title">Encaisser · {{ membership.holder.displayName }}</h2>
      <p class="muted">Reste à payer : {{ formatEuro(membership.remaining) }}</p>

      <BaseField v-model="amount" label="Montant (€)" required :error="fieldErrors.amount" />
      <label class="field">
        <span>Moyen de paiement</span>
        <select v-model="method">
          <option v-for="m in methods" :key="m.id" :value="m.id">{{ m.label }}</option>
        </select>
      </label>
      <BaseField v-model="paidAt" label="Date du paiement" type="date" required :error="fieldErrors.paidAt" />
      <BaseField v-model="reference" label="Référence (n° de chèque, virement…)" :maxlength="80" />

      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="dialog?.close()">Annuler</BaseButton>
        <BaseButton type="submit" :pending="pending">Enregistrer</BaseButton>
      </div>
    </form>
  </dialog>
</template>

<style scoped>
.dialog {
  width: min(28rem, calc(100vw - 2rem));
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
  color: var(--color-text);
}

.dialog::backdrop {
  background: rgb(0 0 0 / 60%);
}

.dialog h2 {
  margin: 0;
}

.field {
  display: grid;
  gap: 0.35rem;
  font-weight: 600;
}

select {
  min-height: 2.75rem;
  padding: 0 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
