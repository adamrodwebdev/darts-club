<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
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

async function submit(): Promise<void> {
  const updated = await run(() => services.treasury.cancelPayment(props.payment.id, reason.value))
  if (updated) emit('saved', updated)
}
</script>

<template>
  <BaseDialog :title="t('treasury.cancelDialog.title')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="submit">
      <p class="muted">
        {{ t('treasury.cancelDialog.summary', { amount: formatEuro(payment.amount), method: payment.method.label, date: formatDate(new Date(payment.paidAt), { dateStyle: 'short' }) }) }}
        {{ t('treasury.cancelDialog.history') }}
      </p>
      <BaseField v-model="reason" :label="t('treasury.cancelDialog.reason')" required :maxlength="200" />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="emit('close')">{{ t('treasury.cancelDialog.back') }}</BaseButton>
        <BaseButton type="submit" variant="danger" :pending="pending" :disabled="!reason.trim()">{{ t('treasury.cancelDialog.submit') }}</BaseButton>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
