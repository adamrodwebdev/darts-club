<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import { formatInputNumber } from '@/i18n/format'
import type { TokenKind } from '@/models/EventAccounts'
import type { TokenDraft } from '@/services/EventAccountsService'

const props = defineProps<{
  /** Jeton à modifier, ou null pour un nouveau */
  token: TokenKind | null
  pending: boolean
  error: string | null
}>()
const emit = defineEmits<{ save: [draft: TokenDraft]; remove: []; close: [] }>()
const { t } = useI18n()

const name = ref(props.token?.name ?? '')
const value = ref(props.token ? formatInputNumber(Number(props.token.value), 2) : '')
const bag = ref(props.token?.bag ?? false)

function save(): void {
  emit('save', { name: name.value.trim(), value: value.value.trim(), bag: bag.value })
}
</script>

<template>
  <BaseDialog :title="token ? t('events.accounts.cash.editTitle', { name: token.name }) : t('events.accounts.cash.newTitle')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <BaseField v-model="name" :label="t('events.accounts.cash.name')" required :maxlength="30" />
      <BaseField v-model="value" :label="t('events.accounts.cash.value')" :hint="t('events.accounts.fields.priceHint')" inputmode="decimal" required />
      <label class="check">
        <input v-model="bag" type="checkbox" />
        <span class="check__text">
          <strong>{{ t('events.accounts.cash.bag') }}</strong>
          <span class="muted">{{ t('events.accounts.cash.bagHint') }}</span>
        </span>
      </label>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton v-if="token" variant="danger" :disabled="pending" @click="emit('remove')">{{ t('events.accounts.remove') }}</BaseButton>
        <span class="buttons__end">
          <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
          <BaseButton type="submit" :pending="pending">{{ t('common.save') }}</BaseButton>
        </span>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.check {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-height: 2.75rem;
  cursor: pointer;
}

.check input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  margin: 0.15rem 0 0;
  accent-color: var(--color-accent);
}

.check__text {
  display: grid;
  gap: 0.15rem;
}

.check__text .muted {
  font-size: 0.875rem;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* Annuler et Enregistrer restent ensemble, à droite, même quand la ligne se coupe */
.buttons__end {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-inline-start: auto;
}
</style>
