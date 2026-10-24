<script setup lang="ts">
import { onMounted, useTemplateRef, watch } from 'vue'

const props = defineProps<{
  text: string
  /** Nom du passage pour les lecteurs d'écran (« Objet », « Signature »…) */
  label: string
  editable: boolean
  /** Affiché dans un passage vidé (retiré du document) */
  placeholder: string
}>()
const emit = defineEmits<{ edit: [text: string] }>()

const element = useTemplateRef<HTMLElement>('element')

/** Le texte n'est remplacé que hors saisie : le curseur ne saute jamais pendant la frappe */
function sync(): void {
  const el = element.value
  if (el && document.activeElement !== el && el.innerText !== props.text) el.innerText = props.text
}

onMounted(sync)
watch(() => props.text, sync)

function onInput(): void {
  if (element.value) emit('edit', element.value.innerText)
}
</script>

<template>
  <div
    v-if="editable"
    ref="element"
    class="editable"
    contenteditable="plaintext-only"
    role="textbox"
    aria-multiline="true"
    :aria-label="label"
    :data-placeholder="placeholder"
    @input="onInput"
    @blur="sync"
  />
  <div v-else class="editable">{{ text }}</div>
</template>

<style scoped>
.editable {
  white-space: pre-line;
  border-radius: 0.2rem;
  outline-offset: 0.15rem;
}

.editable[contenteditable] {
  cursor: text;
}

.editable[contenteditable]:hover {
  background: color-mix(in srgb, var(--paper-ink) 5%, transparent);
}

.editable[contenteditable]:focus-visible {
  outline: 2px solid var(--color-accent);
}

.editable:empty::before {
  content: attr(data-placeholder);
  color: var(--paper-ink-muted);
  font-style: italic;
}
</style>
