<script setup lang="ts">
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import type { ReceiptDraft } from '@/services/EventAccountsService'

defineProps<{ pending: boolean; error: string | null }>()
const emit = defineEmits<{ save: [draft: ReceiptDraft]; close: [] }>()
const { t } = useI18n()
const fileId = useId()

const file = ref<File | null>(null)
const label = ref('')
const amount = ref('')
const credit = ref(false)

function pick(event: Event): void {
  const input = event.target as HTMLInputElement
  file.value = input.files?.[0] ?? null
  // Nom proposé : celui du fichier, sans extension
  if (file.value && !label.value) label.value = file.value.name.replace(/\.[^.]+$/, '').slice(0, 80)
}

function save(): void {
  if (!file.value) return
  emit('save', { file: file.value, label: label.value, amount: amount.value.trim(), credit: credit.value })
}
</script>

<template>
  <BaseDialog :title="t('events.accounts.receiptNew')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <div class="field">
        <label :for="fileId">{{ t('events.accounts.receiptFile') }}</label>
        <input :id="fileId" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required @change="pick" />
        <p class="muted hint">{{ t('events.accounts.receiptFileHint') }}</p>
      </div>
      <BaseField v-model="label" :label="t('events.accounts.receiptLabel')" required :maxlength="80" />
      <BaseField v-model="amount" :label="t('events.accounts.receiptAmount')" :hint="t('events.accounts.fields.priceHint')" />
      <label class="check">
        <input v-model="credit" type="checkbox" />
        {{ t('events.accounts.receiptCredit') }}
      </label>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :pending="pending" :disabled="!file || !label.trim()">{{ t('common.save') }}</BaseButton>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.field {
  display: grid;
  gap: 0.35rem;
}

.field label {
  font-size: 0.95rem;
  font-weight: 600;
}

.field input {
  min-height: 2.75rem;
  padding: 0.5rem;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font: inherit;
}

.hint {
  margin: 0;
  font-size: 0.85rem;
}

.check {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.75rem;
}

.check input {
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
