<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { meetsRule, PASSWORD_MIN_LENGTH, PASSWORD_RULES, type PasswordRule } from '@/models/PasswordPolicy'

/** Règles d'un nouveau mot de passe, cochées au fil de la saisie (relié au champ par aria-describedby) */
const props = defineProps<{ id: string; password: string }>()
const { t } = useI18n()

const LABELS = {
  length: 'passwordRules.length',
  digit: 'passwordRules.digit',
  special: 'passwordRules.special',
} as const satisfies Record<PasswordRule, string>

const rules = computed(() =>
  PASSWORD_RULES.map((rule) => ({ rule, label: t(LABELS[rule], { n: PASSWORD_MIN_LENGTH }), met: meetsRule(props.password, rule) })),
)
</script>

<template>
  <ul :id="props.id" class="rules">
    <li v-for="r in rules" :key="r.rule" :class="{ met: r.met }">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
        <path v-if="r.met" d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        <circle v-else cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="2" />
      </svg>
      {{ r.label }}
      <span class="visually-hidden">{{ r.met ? t('passwordRules.met') : t('passwordRules.unmet') }}</span>
    </li>
  </ul>
</template>

<style scoped>
.rules {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
  margin: 0;
  padding: 0;
  color: var(--color-muted);
  font-size: 0.85rem;
  list-style: none;
}

.rules li {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.met {
  color: var(--color-success);
}
</style>
