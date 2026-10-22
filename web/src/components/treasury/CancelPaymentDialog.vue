<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatDate } from '@/i18n/format'
import { formatEuro, type MembershipRecord, type PaymentRecord } from '@/models/Membership'
import { useServices } from '@/services'

const services = useServices()

const props = defineProps<{ payment: PaymentRecord }>()
const emit = defineEmits<{ saved: [MembershipRecord]; close: [] }>()
const { t } = useI18n()

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
      <h2 id="cancel-title">{{ t('treasury.cancelDialog.title') }}</h2>
      <p class="muted">
        {{ t('treasury.cancelDialog.summary', { amount: formatEuro(payment.amount), method: payment.method.label, date: formatDate(new Date(payment.paidAt), { dateStyle: 'short' }) }) }}
        {{ t('treasury.cancelDialog.history') }}
      </p>
      <BaseField v-model="reason" :label="t('treasury.cancelDialog.reason')" required :maxlength="200" />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="dialog?.close()">{{ t('treasury.cancelDialog.back') }}</BaseButton>
        <BaseButton type="submit" variant="danger" :pending="pending" :disabled="!reason.trim()">{{ t('treasury.cancelDialog.submit') }}</BaseButton>
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
