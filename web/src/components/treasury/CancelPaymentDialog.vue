<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatEuro, type MembershipRecord, type PaymentRecord } from '@/models/Membership'
import { services } from '@/services'

const props = defineProps<{ payment: PaymentRecord }>()
const emit = defineEmits<{ saved: [MembershipRecord]; close: [] }>()

const reason = ref('')
const { pending, error, run } = useAsyncAction()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

onMounted(() => dialog.value?.showModal())

async function submit(): Promise<void> {
  const updated = await run(() => services.treasury.cancelPayment(props.payment.id, reason.value))
  if (updated) emit('saved', updated)
}
</script>

<template>
  <dialog ref="dialog" class="dialog" aria-labelledby="cancel-title" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="submit">
      <h2 id="cancel-title">Annuler un paiement</h2>
      <p class="muted">
        {{ formatEuro(payment.amount) }} · {{ payment.method.label }} du {{ new Date(payment.paidAt).toLocaleDateString('fr-FR') }}.
        Le paiement reste visible dans l’historique, barré, avec son motif.
      </p>
      <BaseField v-model="reason" label="Motif" required :maxlength="200" />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="dialog?.close()">Retour</BaseButton>
        <BaseButton type="submit" variant="danger" :pending="pending" :disabled="!reason.trim()">Annuler le paiement</BaseButton>
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

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
