<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import type { AccountsEntry, EntryKind } from '@/models/EventAccounts'
import type { EntryDraft } from '@/services/EventAccountsService'

const props = defineProps<{
  /** Ligne à modifier, ou null pour une nouvelle ligne de ce type */
  entry: AccountsEntry | null
  kind: EntryKind
  pending: boolean
  error: string | null
  fieldErrors: Readonly<Record<string, string>>
}>()
const emit = defineEmits<{ save: [draft: EntryDraft]; remove: []; close: [] }>()
const { t } = useI18n()

const decimal = (value: string | null | undefined): string => (value ?? '').replace('.', ',')

const label = ref(props.entry?.label ?? '')
const planned = ref(decimal(props.entry?.planned))
const actual = ref(decimal(props.entry?.actual))

const title = computed(() => {
  if (props.entry) return t('events.accounts.entryEdit')
  return props.kind === 'depense' ? t('events.accounts.expenseNew') : t('events.accounts.incomeNew')
})

function save(): void {
  const realAmount = actual.value.trim()
  emit('save', { kind: props.entry?.kind ?? props.kind, label: label.value, planned: planned.value.trim(), actual: realAmount === '' ? null : realAmount })
}
</script>

<template>
  <BaseDialog :title="title" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <BaseField v-model="label" :label="t('events.accounts.fields.label')" required :maxlength="80" :error="fieldErrors.label" />
      <div class="grid">
        <BaseField v-model="planned" :label="t('events.accounts.fields.entryPlanned')" required :error="fieldErrors.planned" />
        <BaseField v-model="actual" :label="t('events.accounts.fields.entryActual')" :hint="t('events.accounts.fields.entryActualHint')" :error="fieldErrors.actual" />
      </div>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton v-if="entry" variant="danger" :disabled="pending" @click="emit('remove')">{{ t('events.accounts.remove') }}</BaseButton>
        <span class="spacer" />
        <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :pending="pending">{{ t('common.save') }}</BaseButton>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr));
  gap: 0.75rem 1rem;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.spacer {
  flex: 1;
}
</style>
