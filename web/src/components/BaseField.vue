<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'

const model = defineModel<string>({ required: true })

const props = withDefaults(
  defineProps<{
    label: string
    type?: string
    autocomplete?: string
    error?: string
    hint?: string
    required?: boolean
    multiline?: boolean
    maxlength?: number
    /** Éléments décrivant le champ en plus de l'aide (règles du mot de passe…) */
    describedby?: string
  }>(),
  { type: 'text', autocomplete: 'off', error: undefined, hint: undefined, required: false, multiline: false, maxlength: undefined, describedby: undefined },
)

const { t } = useI18n()
const id = useId()
const input = useTemplateRef<HTMLInputElement>('input')

const describedBy = computed(() => [props.error || props.hint ? `${id}-help` : null, props.describedby].filter(Boolean).join(' ') || undefined)

/** Mot de passe affiché en clair à la demande, pour vérifier la saisie */
const isPassword = computed(() => props.type === 'password')
const revealed = ref(false)
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type))

// Masqué de nouveau à chaque envoi du formulaire
function hide(): void {
  revealed.value = false
}
onMounted(() => input.value?.form?.addEventListener('submit', hide))
onBeforeUnmount(() => input.value?.form?.removeEventListener('submit', hide))
</script>

<template>
  <div class="field" :class="{ 'field--error': props.error }">
    <label :for="id">{{ props.label }}<span v-if="props.required" aria-hidden="true"> *</span></label>
    <textarea
      v-if="props.multiline"
      :id="id"
      v-model="model"
      rows="4"
      :required="props.required"
      :maxlength="props.maxlength"
      :aria-invalid="!!props.error"
      :aria-describedby="describedBy"
    />
    <div v-else class="field__control" :class="{ 'field__control--reveal': isPassword }">
      <input
        :id="id"
        ref="input"
        v-model="model"
        :type="inputType"
        :autocomplete="props.autocomplete"
        :required="props.required"
        :maxlength="props.maxlength"
        :aria-invalid="!!props.error"
        :aria-describedby="describedBy"
        :spellcheck="isPassword ? false : undefined"
        :autocapitalize="isPassword ? 'off' : undefined"
      />
      <!-- Bouton bascule : nom fixe, état donné par aria-pressed -->
      <button
        v-if="isPassword"
        type="button"
        class="field__reveal"
        :aria-label="t('common.showPassword')"
        :aria-pressed="revealed"
        :aria-controls="id"
        @click="revealed = !revealed"
      >
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
          <path
            v-if="revealed"
            d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.4 5.2A9.8 9.8 0 0 1 12 5c5 0 8.5 4.2 9.5 7-.4 1.1-1.2 2.5-2.4 3.7M6.1 6.1C4.2 7.4 3 9.4 2.5 12c1 2.8 4.5 7 9.5 7 1.7 0 3.2-.5 4.5-1.2"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <path
            v-else
            d="M2.5 12C3.5 9.2 7 5 12 5s8.5 4.2 9.5 7c-1 2.8-4.5 7-9.5 7s-8.5-4.2-9.5-7zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </button>
    </div>
    <p v-if="props.error || props.hint" :id="`${id}-help`" class="field__help" :role="props.error ? 'alert' : undefined">
      {{ props.error ?? props.hint }}
    </p>
    <slot />
  </div>
</template>

<style scoped>
.field {
  display: grid;
  gap: 0.35rem;
}

label {
  font-weight: 600;
  font-size: 0.95rem;
}

.field__control {
  position: relative;
}

input,
textarea {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
  resize: vertical;
}

/* Place du bouton d'affichage (cible de 44 × 44 px) */
.field__control--reveal input {
  padding-inline-end: 3rem;
}

input:focus-visible,
textarea:focus-visible {
  border-color: var(--color-accent);
}

.field__reveal {
  position: absolute;
  inset-block: 0;
  inset-inline-end: 0;
  display: grid;
  place-items: center;
  width: 2.75rem;
  min-height: 2.75rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.field__reveal:hover,
.field__reveal[aria-pressed='true'] {
  color: var(--color-text);
}

.field__reveal:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: -2px;
}

.field__help {
  margin: 0;
  font-size: 0.85rem;
  color: var(--color-muted);
}

.field--error input,
.field--error textarea {
  border-color: var(--color-danger);
}

.field--error .field__help {
  color: var(--color-danger);
}
</style>
