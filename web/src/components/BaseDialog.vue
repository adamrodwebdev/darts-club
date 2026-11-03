<script setup lang="ts">
import { onMounted, useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * `wide` : formulaire en plusieurs colonnes (article de la carte) ; `fullscreen` : tout l'écran, cadre
 * sombre dans les deux thèmes, rien à faire défiler (photos et vidéos en grand)
 */
withDefaults(defineProps<{ title: string; wide?: boolean; fullscreen?: boolean }>(), { wide: false, fullscreen: false })
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
  <dialog ref="dialog" class="dialog" :class="{ 'dialog--wide': wide, 'dialog--fullscreen': fullscreen }" :aria-labelledby="id" @close="emit('close')">
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

/* Plein écran : les jetons de la page prennent les teintes du cadre sombre, pour tout ce qu'il contient */
.dialog--fullscreen {
  --color-bg: var(--media-stage);
  --color-surface: var(--media-panel);
  --color-surface-2: var(--media-panel-2);
  --color-border: var(--media-line);
  --color-text: var(--media-ink);
  --color-muted: var(--media-ink-muted);
  --color-accent: var(--media-accent);
  --color-link: var(--media-link);
  --color-danger: var(--media-danger);
  width: 100%;
  max-width: none;
  height: 100dvh;
  max-height: none;
  margin: 0;
  border: 0;
  border-radius: 0;
  background: var(--media-stage);
  color-scheme: dark;
}

.dialog--fullscreen::backdrop {
  background: var(--media-stage);
}

.dialog--fullscreen .dialog__body {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  height: 100%;
  max-height: none;
  overflow: hidden;
  padding:
    max(0.75rem, env(safe-area-inset-top, 0px))
    max(0.75rem, env(safe-area-inset-right, 0px))
    max(0.75rem, env(safe-area-inset-bottom, 0px))
    max(0.75rem, env(safe-area-inset-left, 0px));
}

/* Le titre dit où l'on en est (« Photo 3 / 9 ») */
.dialog--fullscreen h2 {
  margin-block-end: 0.5rem;
  font-size: 1rem;
  font-variant-numeric: tabular-nums;
}

.dialog--fullscreen .dialog__close {
  inset-block-start: max(0.25rem, env(safe-area-inset-top, 0px));
  inset-inline-end: max(0.5rem, env(safe-area-inset-right, 0px));
  background: var(--color-surface);
  color: var(--color-text);
}

/* La page derrière ne défile pas sous la molette (gouttière de défilement réservée : rien ne bouge) */
:global(:root:has(.dialog--fullscreen[open])) {
  overflow: hidden;
}
</style>
