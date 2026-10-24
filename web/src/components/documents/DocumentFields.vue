<script setup lang="ts">
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseField from '@/components/BaseField.vue'
import type { DocumentField } from '@/models/EventDocuments'

const props = defineProps<{
  fields: readonly DocumentField[]
  values: Readonly<Record<string, string | boolean>>
  editable: boolean
}>()
const emit = defineEmits<{ update: [key: string, value: string | boolean] }>()
const { t, te } = useI18n()
const id = useId()

function label(key: string): string {
  return t(`events.documents.fields.${key}`)
}

function hint(key: string): string | undefined {
  return te(`events.documents.fieldHints.${key}`) ? t(`events.documents.fieldHints.${key}`) : undefined
}

function text(key: string): string {
  const value = props.values[key]
  return typeof value === 'string' ? value : ''
}

function checked(key: string): boolean {
  return props.values[key] === true
}

function onSelect(key: string, event: Event): void {
  if (event.target instanceof HTMLSelectElement) emit('update', key, event.target.value)
}

function onCheck(key: string, event: Event): void {
  if (event.target instanceof HTMLInputElement) emit('update', key, event.target.checked)
}
</script>

<template>
  <!-- Champs du modèle : texte, texte long, case à cocher ou liste de choix -->
  <fieldset class="fields" :disabled="!editable">
    <template v-for="field in fields" :key="field.key">
      <div v-if="field.kind === 'checkbox'" class="check">
        <input :id="`${id}-${field.key}`" type="checkbox" :checked="checked(field.key)" @change="onCheck(field.key, $event)" />
        <label :for="`${id}-${field.key}`">{{ label(field.key) }}</label>
      </div>
      <div v-else-if="field.kind === 'choice'" class="choice">
        <label :for="`${id}-${field.key}`">{{ label(field.key) }}</label>
        <select :id="`${id}-${field.key}`" :value="text(field.key)" @change="onSelect(field.key, $event)">
          <option v-for="choice in field.choices" :key="choice" :value="choice">{{ t(`events.documents.choices.${field.key}.${choice}`) }}</option>
        </select>
      </div>
      <BaseField
        v-else
        :model-value="text(field.key)"
        :label="label(field.key)"
        :hint="hint(field.key)"
        :multiline="field.kind === 'textarea'"
        :maxlength="2000"
        @update:model-value="(value: string) => emit('update', field.key, value)"
      />
    </template>
  </fieldset>
</template>

<style scoped>
.fields {
  display: grid;
  gap: 1rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.check {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 2.75rem;
}

.check input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.check label,
.choice label {
  font-weight: 600;
  font-size: 0.95rem;
}

.choice {
  display: grid;
  gap: 0.35rem;
}

select {
  width: 100%;
  min-height: 2.75rem;
  padding: 0 0.8rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  color: var(--color-text);
  font: inherit;
}
</style>
