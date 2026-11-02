<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatEuro, type MembershipRecord, type Option } from '@/models/Membership'
import { useServices } from '@/services'
import { toDateInput } from '@/time/Clock'

const services = useServices()

const props = defineProps<{ membership: MembershipRecord; methods: Option[] }>()
const emit = defineEmits<{ saved: [MembershipRecord]; close: [] }>()
const { t } = useI18n()

const today = toDateInput(services.clock.now())
const amount = ref(props.membership.remaining)
const method = ref(props.methods[0]?.id ?? 'especes')
const paidAt = ref(today)
const reference = ref('')
const { pending, error, fieldErrors, run } = useAsyncAction()

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
  <BaseDialog :title="t('treasury.paymentForm.title', { name: membership.holder.displayName })" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="submit">
      <p class="muted">{{ t('treasury.paymentForm.remaining', { amount: formatEuro(membership.remaining) }) }}</p>

      <BaseField v-model="amount" :label="t('treasury.paymentForm.amount')" required :error="fieldErrors.amount" />
      <label class="field">
        <span>{{ t('treasury.paymentForm.method') }}</span>
        <select v-model="method">
          <option v-for="m in methods" :key="m.id" :value="m.id">{{ m.label }}</option>
        </select>
      </label>
      <BaseField v-model="paidAt" :label="t('treasury.paymentForm.paidAt')" type="date" required :error="fieldErrors.paidAt" />
      <BaseField v-model="reference" :label="t('treasury.paymentForm.reference')" :maxlength="80" />

      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :pending="pending">{{ t('common.save') }}</BaseButton>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
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
