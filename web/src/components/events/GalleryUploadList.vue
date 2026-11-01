<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { UploadLine } from '@/composables/useGalleryUpload'

defineProps<{ lines: readonly UploadLine[] }>()
const { t } = useI18n()
</script>

<template>
  <section class="uploads" aria-labelledby="uploads-title">
    <h3 id="uploads-title">{{ t('events.gallery.uploadsTitle') }}</h3>
    <ul>
      <li v-for="line in lines" :key="line.key" class="line" :class="`line--${line.tone}`">
        <div class="line__head">
          <strong class="line__name">{{ line.name }}</strong>
          <span class="muted line__meta">{{ line.meta }}</span>
        </div>
        <progress v-if="line.tone === 'progress'" :value="line.progress ?? undefined" max="1" :aria-label="line.name"></progress>
        <!-- La barre suit l'envoi ; seules les issues (ajoutée, refusée) sont annoncées -->
        <span class="line__status" :role="line.tone === 'progress' ? undefined : 'status'">{{ line.status }}</span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.uploads {
  display: grid;
  gap: 0.5rem;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

h3 {
  margin: 0;
  font-size: 1rem;
}

ul {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.line {
  display: grid;
  gap: 0.35rem;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-bg);
}

.line--error {
  border-color: var(--color-danger);
}

.line__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.25rem 0.75rem;
}

.line__name {
  min-width: 0;
  overflow-wrap: anywhere;
}

.line__meta {
  font-size: 0.85rem;
}

.line__status {
  font-size: 0.9rem;
  color: var(--color-muted);
}

.line--done .line__status {
  color: var(--color-success);
}

.line--error .line__status {
  color: var(--color-danger);
}

progress {
  width: 100%;
  height: 0.4rem;
  accent-color: var(--color-accent);
}
</style>
