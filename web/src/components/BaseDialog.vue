<script setup lang="ts">
import { onMounted, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'

/** `wide` : formulaire en plusieurs colonnes (article de la carte) */
withDefaults(defineProps<{ title: string; wide?: boolean }>(), { wide: false })
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()

const id = useId()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

// <dialog> natif modal : focus piégé, Échap pour fermer, arrière-plan inerte
onMounted(() => dialog.value?.showModal())

function close(): void {
  dialog.value?.close()
}

defineExpose({ close })
</script>

<template>
  <dialog ref="dialog" class="dialog" :class="{ 'dialog--wide': wide }" :aria-labelledby="id" @close="emit('close')">
    <div class="dialog__body">
      <h2 :id="id">{{ title }}</h2>
      <slot />
    </div>
    <!--
      Toute fenêtre se ferme d'un bouton : au doigt, il n'y a pas de touche Échap. Dernier dans l'ordre
      du focus (le premier champ le garde à l'ouverture), affiché en haut à droite, hors du défilement.
    -->
    <button type="button" class="dialog__close" :aria-label="t('common.closeDialog')" @click="close">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
        <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      </svg>
    </button>
  </dialog>
</template>

<style scoped>
.dialog {
  width: min(30rem, calc(100% - 2rem));
  padding: 0;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
  color: var(--color-text);
}

.dialog--wide {
  width: min(42rem, calc(100% - 2rem));
}

.dialog::backdrop {
  background: rgb(0 0 0 / 60%);
}

/* Seul le contenu défile : le bouton de fermeture reste en vue */
.dialog__body {
  max-height: calc(100dvh - 2rem - 2px);
  overflow: auto;
  padding: 1.25rem;
}

h2 {
  margin: 0 0 1rem;
  /* Place du bouton de fermeture */
  padding-inline-end: 2.75rem;
}

.dialog__close {
  position: absolute;
  inset-block-start: 0.5rem;
  inset-inline-end: 1rem;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.dialog__close:hover,
.dialog__close:focus-visible {
  background: var(--color-surface-2);
  color: var(--color-text);
}
</style>
