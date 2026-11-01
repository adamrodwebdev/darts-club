<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import TokenSwatch from '@/components/evening/TokenSwatch.vue'
import { formatInputNumber } from '@/i18n/format'
import { TOKEN_COLORS, type TokenColor, type TokenKind } from '@/models/EventAccounts'
import type { TokenDraft } from '@/services/EventAccountsService'

const props = defineProps<{
  /** Jeton à modifier, ou null pour un nouveau */
  token: TokenKind | null
  /** Jetons figés par une première vente : seule la couleur change encore */
  frozen: boolean
  pending: boolean
  error: string | null
}>()
const emit = defineEmits<{ save: [draft: TokenDraft]; remove: []; close: [] }>()
const { t } = useI18n()

const COLOR_LABELS: Readonly<Record<TokenColor, string>> = {
  vert: 'events.accounts.cash.colors.vert',
  jaune: 'events.accounts.cash.colors.jaune',
  rouge: 'events.accounts.cash.colors.rouge',
  bleu: 'events.accounts.cash.colors.bleu',
  violet: 'events.accounts.cash.colors.violet',
  orange: 'events.accounts.cash.colors.orange',
  blanc: 'events.accounts.cash.colors.blanc',
  noir: 'events.accounts.cash.colors.noir',
}

const name = ref(props.token?.name ?? '')
const value = ref(props.token ? formatInputNumber(Number(props.token.value), 2) : '')
const bag = ref(props.token?.bag ?? false)
const color = ref<TokenColor | null>(props.token?.color ?? null)

function save(): void {
  emit('save', { name: name.value.trim(), value: value.value.trim(), bag: bag.value, color: bag.value ? null : color.value })
}
</script>

<template>
  <BaseDialog :title="token ? t('events.accounts.cash.editTitle', { name: token.name }) : t('events.accounts.cash.newTitle')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <p v-if="frozen" class="notice">{{ t('events.accounts.cash.frozen') }}</p>
      <template v-else>
        <BaseField v-model="name" :label="t('events.accounts.cash.name')" required :maxlength="30" />
        <BaseField v-model="value" :label="t('events.accounts.cash.value')" :hint="t('events.accounts.fields.priceHint')" inputmode="decimal" required />
        <label class="check">
          <input v-model="bag" type="checkbox" />
          <span class="check__text">
            <strong>{{ t('events.accounts.cash.bag') }}</strong>
            <span class="muted">{{ t('events.accounts.cash.bagHint') }}</span>
          </span>
        </label>
      </template>
      <fieldset v-if="!bag" class="colors">
        <legend>{{ t('events.accounts.cash.color') }}</legend>
        <div class="colors__list">
          <label v-for="option in TOKEN_COLORS" :key="option" class="color" :class="{ 'color--on': color === option }">
            <input v-model="color" type="radio" name="token-color" :value="option" />
            <TokenSwatch :token="{ bag: false, color: option }" />
            {{ t(COLOR_LABELS[option]) }}
          </label>
        </div>
      </fieldset>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton v-if="token && !frozen" variant="danger" :disabled="pending" @click="emit('remove')">{{ t('events.accounts.remove') }}</BaseButton>
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

.colors {
  display: grid;
  gap: 0.4rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.colors legend {
  margin-bottom: 0.4rem;
  padding: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.colors__list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 7.5rem), 1fr));
  gap: 0.5rem;
}

.color {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  cursor: pointer;
}

/* Bouton radio natif invisible, étendu à toute la pastille : clic, clavier et lecteurs d'écran */
.color input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.color--on {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 12%, transparent);
}

.color:has(input:focus-visible) {
  outline: 2px solid var(--color-link);
  outline-offset: 2px;
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
