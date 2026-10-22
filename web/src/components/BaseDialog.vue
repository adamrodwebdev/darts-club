<script setup lang="ts">
import { onMounted, useId, useTemplateRef } from 'vue'

defineProps<{ title: string }>()
const emit = defineEmits<{ close: [] }>()

const id = useId()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

// <dialog> natif modal : focus piégé, Échap pour fermer, arrière-plan inerte
onMounted(() => dialog.value?.showModal())

defineExpose({ close: () => dialog.value?.close() })
</script>

<template>
  <dialog ref="dialog" class="dialog" :aria-labelledby="id" @close="emit('close')">
    <h2 :id="id">{{ title }}</h2>
    <slot />
  </dialog>
</template>

<style scoped>
.dialog {
  width: min(30rem, calc(100% - 2rem));
  max-height: calc(100dvh - 2rem);
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
  color: var(--color-text);
}

.dialog::backdrop {
  background: rgb(0 0 0 / 60%);
}

h2 {
  margin: 0 0 1rem;
}
</style>
