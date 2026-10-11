<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import type { EventPoster } from '@/models/ClubEvent'

/**
 * Choix de l'affiche d'un événement. Le fichier n'est envoyé qu'à l'enregistrement
 * du formulaire (voir EventFormView) ; l'API le ré-encode en WebP.
 */
const file = defineModel<File | null>('file', { required: true })
const remove = defineModel<boolean>('remove', { required: true })
const props = defineProps<{ current: EventPoster | null }>()
const { t } = useI18n()
const id = useId()

const MAX_BYTES = 10 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'image/webp']
/** Aperçu local en data: (la CSP n'autorise pas les URL blob:) */
const preview = ref<string | null>(null)
const error = ref<string | null>(null)

const shown = computed(() => (file.value ? preview.value : remove.value ? null : (props.current?.cardUrl ?? null)))
const hasPoster = computed(() => file.value !== null || (props.current !== null && !remove.value))

function onChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const chosen = input.files?.[0]
  input.value = ''
  if (!chosen) return

  error.value = !TYPES.includes(chosen.type) ? t('events.poster.wrongType') : chosen.size > MAX_BYTES ? t('events.poster.tooLarge') : null
  if (error.value) return

  file.value = chosen
  remove.value = false
  const reader = new FileReader()
  reader.onload = () => {
    if (file.value === chosen && typeof reader.result === 'string') preview.value = reader.result
  }
  reader.readAsDataURL(chosen)
}

function clear(): void {
  error.value = null
  if (file.value) {
    file.value = null
    preview.value = null
  } else {
    remove.value = true
  }
}
</script>

<template>
  <fieldset class="poster-field">
    <legend>{{ t('events.poster.label') }}</legend>
    <p :id="`${id}-hint`" class="muted hint">{{ t('events.poster.hint') }}</p>

    <div class="body">
      <img v-if="shown" class="preview" :src="shown" :alt="t('events.poster.preview')">
      <div class="actions">
        <label class="pick">
          <input type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden" :aria-describedby="`${id}-hint`" @change="onChange">
          <span>{{ hasPoster ? t('events.poster.replace') : t('events.poster.choose') }}</span>
        </label>
        <button v-if="hasPoster" type="button" class="secondary" @click="clear">{{ t('events.poster.remove') }}</button>
        <button v-else-if="remove && current" type="button" class="secondary" @click="remove = false">{{ t('events.poster.undo') }}</button>
      </div>
    </div>

    <p v-if="remove && current && !file" class="muted" role="status">{{ t('events.poster.removed') }}</p>
    <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
  </fieldset>
</template>

<style scoped>
.poster-field {
  display: grid;
  gap: 0.6rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

legend {
  padding: 0;
  font-weight: 600;
  font-size: 0.95rem;
}

.hint {
  margin: 0;
  font-size: 0.85rem;
}

.body {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 1rem;
}

.preview {
  width: min(10rem, 40%);
  height: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.pick,
.secondary {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.pick:hover,
.secondary:hover,
.pick:focus-within {
  border-color: var(--color-accent);
}

/* Champ fichier masqué : le focus clavier se voit sur son libellé */
.pick:focus-within {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}
</style>
