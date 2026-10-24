<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import type { BarItem } from '@/models/EventAccounts'
import type { BarItemDraft } from '@/services/EventAccountsService'

const props = defineProps<{
  /** Article à modifier, ou null pour un nouveau */
  item: BarItem | null
  pending: boolean
  error: string | null
  fieldErrors: Readonly<Record<string, string>>
}>()
const emit = defineEmits<{ save: [draft: BarItemDraft]; remove: []; close: [] }>()
const { t } = useI18n()

/** Prix décimal affiché « 0,95 » (saisie à la française, l'API accepte la virgule) */
const decimal = (value: string | undefined): string => (value ?? '').replace('.', ',')

const label = ref(props.item?.label ?? '')
const planned = ref(String(props.item?.planned ?? 0))
const bought = ref(String(props.item?.bought ?? 0))
const returned = ref(String(props.item?.returned ?? 0))
const sold = ref(String(props.item?.sold ?? 0))
const purchasePrice = ref(decimal(props.item?.purchasePrice))
const salePrice = ref(decimal(props.item?.salePrice))

function save(): void {
  emit('save', {
    label: label.value,
    planned: Number(planned.value) || 0,
    bought: Number(bought.value) || 0,
    returned: Number(returned.value) || 0,
    sold: Number(sold.value) || 0,
    purchasePrice: String(purchasePrice.value).trim(),
    salePrice: String(salePrice.value).trim(),
  })
}
</script>

<template>
  <BaseDialog :title="item ? t('events.accounts.itemEdit') : t('events.accounts.itemNew')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <BaseField v-model="label" :label="t('events.accounts.fields.label')" required :maxlength="80" :error="fieldErrors.label" />
      <div class="grid">
        <BaseField v-model="planned" :label="t('events.accounts.fields.planned')" type="number" :error="fieldErrors.planned" />
        <BaseField v-model="bought" :label="t('events.accounts.fields.bought')" type="number" :error="fieldErrors.bought" />
        <BaseField v-model="returned" :label="t('events.accounts.fields.returned')" type="number" :error="fieldErrors.returned" />
        <BaseField v-model="sold" :label="t('events.accounts.fields.sold')" type="number" :error="fieldErrors.sold" />
        <BaseField v-model="purchasePrice" :label="t('events.accounts.fields.purchasePrice')" :hint="t('events.accounts.fields.priceHint')" required :error="fieldErrors.purchasePrice" />
        <BaseField v-model="salePrice" :label="t('events.accounts.fields.salePrice')" :hint="t('events.accounts.fields.priceHint')" required :error="fieldErrors.salePrice" />
      </div>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton v-if="item" variant="danger" :disabled="pending" @click="emit('remove')">{{ t('events.accounts.remove') }}</BaseButton>
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
