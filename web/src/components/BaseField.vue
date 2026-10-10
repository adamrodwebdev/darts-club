<script setup lang="ts">
import { useId } from 'vue'

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
  }>(),
  { type: 'text', autocomplete: 'off', error: undefined, hint: undefined, required: false, multiline: false, maxlength: undefined },
)

const id = useId()
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
      :aria-describedby="props.error || props.hint ? `${id}-help` : undefined"
    />
    <input
      v-else
      :id="id"
      v-model="model"
      :type="props.type"
      :autocomplete="props.autocomplete"
      :required="props.required"
      :maxlength="props.maxlength"
      :aria-invalid="!!props.error"
      :aria-describedby="props.error || props.hint ? `${id}-help` : undefined"
    />
    <p v-if="props.error || props.hint" :id="`${id}-help`" class="field__help" :role="props.error ? 'alert' : undefined">
      {{ props.error ?? props.hint }}
    </p>
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

input:focus-visible,
textarea:focus-visible {
  border-color: var(--color-accent);
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
