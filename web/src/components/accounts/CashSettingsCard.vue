<script setup lang="ts">
import { TEXT_LIMITS } from '@/models/limits'
import { useI18n } from 'vue-i18n'
import { formatEuro, formatInputNumber } from '@/i18n/format'
import TokenSwatch from '@/components/evening/TokenSwatch.vue'
import type { EventAccounts } from '@/models/EventAccounts'
import type { CashDraft } from '@/services/EventAccountsService'

const props = defineProps<{ accounts: EventAccounts; pending: boolean }>()
const emit = defineEmits<{ save: [draft: CashDraft]; addToken: []; editToken: [index: number] }>()
const { t } = useI18n()

/** Jetons au plus (limite de l'API) */
const TOKENS_MAX = 6

/** Encaissement actuel, avec un seul changement */
function draft(change: Partial<CashDraft>): CashDraft {
  const cash = props.accounts.cash
  return { tokensEnabled: cash.tokensEnabled, tokens: cash.tokens.map((token) => ({ ...token })), cashFloat: cash.cashFloat, ...change }
}

function toggle(event: Event): void {
  emit('save', draft({ tokensEnabled: (event.target as HTMLInputElement).checked }))
}

function changeFloat(event: Event): void {
  emit('save', draft({ cashFloat: (event.target as HTMLInputElement).value.trim() || '0' }))
}
</script>

<template>
  <section class="card stack cash" aria-labelledby="cash-title">
    <h2 id="cash-title">{{ t('events.accounts.cash.title') }}</h2>

    <label class="check">
      <input type="checkbox" :checked="accounts.cash.tokensEnabled" :disabled="!accounts.menuEditable || accounts.cash.frozen || pending" @change="toggle" />
      <span class="check__text">
        <strong>{{ t('events.accounts.cash.tokens') }}</strong>
        <span class="muted">{{ accounts.cash.tokensEnabled ? t('events.accounts.cash.tokensHint') : t('events.accounts.cash.direct') }}</span>
      </span>
    </label>

    <div v-if="accounts.cash.tokensEnabled" class="tokens">
      <h3 class="subtitle">{{ t('events.accounts.cash.sold') }}</h3>
      <p v-if="!accounts.cash.tokens.length" class="muted">{{ t('events.accounts.cash.noTokens') }}</p>
      <ul v-else class="token-list">
        <li v-for="(token, index) in accounts.cash.tokens" :key="token.name" class="token">
          <TokenSwatch :token="token" />
          <span class="token__name">{{ token.name }}</span>
          <strong class="token__value">{{ formatEuro(token.value) }}</strong>
          <button
            v-if="accounts.menuEditable"
            type="button"
            class="icon-button no-print"
            :aria-label="t('events.accounts.cash.edit', { name: token.name })"
            @click="emit('editToken', index)"
          >
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </li>
      </ul>
      <p v-if="accounts.cash.frozen" class="muted frozen">{{ t('events.accounts.cash.frozen') }}</p>
      <button v-else-if="accounts.menuEditable && accounts.cash.tokens.length < TOKENS_MAX" type="button" class="add-button no-print" @click="emit('addToken')">
        {{ t('events.accounts.cash.add') }}
      </button>
    </div>

    <div class="float">
      <template v-if="accounts.menuEditable">
        <label for="cash-float" class="float__label">{{ t('events.accounts.cash.float') }}</label>
        <span class="float__control">
          <input :maxlength="TEXT_LIMITS.amount"
            id="cash-float"
            :value="formatInputNumber(Number(accounts.cash.cashFloat), 2)"
            inputmode="decimal"
            autocomplete="off"
            :disabled="pending"
            @change="changeFloat"
          />
          <span aria-hidden="true">€</span>
        </span>
      </template>
      <p v-else class="float__read">
        <span>{{ t('events.accounts.cash.float') }}</span>
        <strong>{{ formatEuro(accounts.cash.cashFloat) }}</strong>
      </p>
    </div>
  </section>
</template>

<style scoped>
.cash h2 {
  margin: 0;
}

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

.tokens {
  display: grid;
  gap: 0.5rem;
}

.frozen {
  margin: 0;
  font-size: 0.875rem;
}

.subtitle {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.token-list {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.token {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.75rem;
  padding-inline-start: 0.75rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.token__name {
  flex: 1 1 auto;
  min-width: 0;
  font-weight: 600;
}

.token__value {
  font-variant-numeric: tabular-nums;
}

.token__value:last-child {
  padding-inline-end: 0.75rem;
}

.icon-button {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.icon-button:hover {
  color: var(--color-text);
}

.add-button {
  justify-self: start;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.float {
  display: grid;
  gap: 0.4rem;
}

.float__label {
  font-size: 0.9rem;
  font-weight: 600;
}

.float__control {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  max-width: 12rem;
}

.float__control input {
  box-sizing: border-box;
  width: 100%;
  min-height: 2.75rem;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
  font-size: 1rem;
}

.float__read {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.5rem;
  margin: 0;
}
</style>
