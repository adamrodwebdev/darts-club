<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { BlockKind, DocumentBlock } from '@/models/EventDocuments'
import EditableText from './EditableText.vue'

const props = defineProps<{ blocks: readonly DocumentBlock[]; editable: boolean }>()
const emit = defineEmits<{ edit: [id: string, text: string]; restore: [id: string] }>()
const { t, locale } = useI18n()

/** Place de chaque morceau sur la feuille A4 (courrier ou fiche) */
const LAYOUT: Record<BlockKind, string> = {
  expediteur: 'sender',
  destinataire: 'recipient',
  'lieu-date': 'place-date',
  objet: 'subject',
  paragraphe: 'paragraph',
  signature: 'signature',
  titre: 'heading',
  ligne: 'line',
  note: 'note',
}

/** En-tête d'association (logo) sur les courriers seulement */
const letter = computed(() => props.blocks.some((b) => b.kind === 'expediteur'))

/** Nom de chaque passage pour les lecteurs d'écran : son intitulé, sinon sa nature, numérotée si elle revient (« Paragraphe 3 ») */
const labels = computed(() => {
  const totals = new Map<BlockKind, number>()
  for (const block of props.blocks) totals.set(block.kind, (totals.get(block.kind) ?? 0) + 1)
  const seen = new Map<BlockKind, number>()
  return new Map(
    props.blocks.map((block): [string, string] => {
      const rank = (seen.get(block.kind) ?? 0) + 1
      seen.set(block.kind, rank)
      const name = t(`events.documents.blocks.${block.kind}`)
      return [block.id, block.label ?? ((totals.get(block.kind) ?? 0) > 1 ? t('events.documents.blockNumbered', { name, rank }) : name)]
    }),
  )
})

function label(block: DocumentBlock): string {
  return labels.value.get(block.id) ?? block.id
}
</script>

<template>
  <!-- Texte des documents en français (administrations françaises), quelle que soit la langue de l'écran -->
  <div class="paper-frame">
    <article class="paper" lang="fr" :class="{ 'paper--letter': letter }" :aria-label="t('events.documents.paper')">
      <img v-if="letter" class="paper__logo" src="/logo.svg" alt="" width="56" height="56" />
      <div
        v-for="block in blocks"
        :key="block.id"
        class="block"
        :class="[`block--${LAYOUT[block.kind]}`, { 'block--removed': block.rewritten && block.text === '' }]"
      >
        <span v-if="block.label" class="block__label">{{ block.label }}</span>
        <EditableText
          class="block__text"
          :text="block.text"
          :label="label(block)"
          :editable="editable"
          :placeholder="t('events.documents.removed')"
          @edit="(text) => emit('edit', block.id, text)"
        />
        <button
          v-if="editable && block.rewritten"
          type="button"
          class="restore"
          :lang="locale"
          :aria-label="t('events.documents.restoreLabel', { name: label(block) })"
          @click="emit('restore', block.id)"
        >
          {{ t('events.documents.restore') }}
        </button>
      </div>
    </article>
  </div>
</template>

<style scoped>
/* Le cadre porte le conteneur : marges et texte de la feuille suivent sa largeur (proportions A4) */
.paper-frame {
  container: paper / inline-size;
}

.paper {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1.1em;
  aspect-ratio: 1 / 1.414;
  padding: 8cqw 8.5cqw;
  border: 1px solid var(--color-border);
  background: var(--paper);
  color: var(--paper-ink);
  box-shadow: 0 0.75rem 2rem color-mix(in srgb, var(--paper-ink) 18%, transparent);
  font-family: Georgia, 'Times New Roman', serif;
  font-size: clamp(0.72rem, 0.45rem + 1.6cqw, 1rem);
  line-height: 1.55;
}

.paper__logo {
  position: absolute;
  top: 8cqw;
  right: 8.5cqw;
  width: 10cqw;
  height: auto;
}

.paper--letter .block--sender {
  padding-right: 13cqw;
}

.block {
  display: grid;
  gap: 0.2em;
}

.block--recipient,
.block--place-date,
.block--signature {
  margin-left: 50%;
}

.block--signature {
  margin-top: 1.5em;
  text-align: center;
}

.block--subject {
  font-weight: 700;
}

.block--heading {
  font-size: 1.3em;
  font-weight: 700;
  line-height: 1.25;
}

.block--line {
  grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
  gap: 0.2em 1em;
  padding-bottom: 0.45em;
  border-bottom: 1px solid color-mix(in srgb, var(--paper-ink) 15%, transparent);
}

.block__label {
  color: var(--paper-ink-muted);
}

.block--line .restore {
  grid-column: 2;
}

.restore {
  justify-self: start;
  min-height: 2.75rem;
  padding: 0 0.75rem;
  border: 1px solid var(--paper-rule);
  border-radius: var(--radius);
  background: var(--paper);
  color: var(--paper-ink);
  font: 600 0.85rem system-ui, sans-serif;
  cursor: pointer;
}

.block--signature .restore {
  justify-self: center;
}
</style>

<!-- Impression : la feuille seule, au format A4 (page nommée, sans effet sur les autres impressions) -->
<style>
@page document-a4 {
  size: A4 portrait;
  margin: 18mm 20mm;
}

@media print {
  html[data-print='document'],
  html[data-print='document'] body {
    background: var(--paper);
  }

  html[data-print='document'] body *:not(:has(.paper), .paper, .paper *) {
    display: none !important;
  }

  html[data-print='document'] body *:has(.paper) {
    display: block !important;
    width: auto !important;
    min-height: 0 !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: none !important;
    box-shadow: none !important;
  }

  html[data-print='document'] .paper {
    page: document-a4;
    aspect-ratio: auto;
    padding: 0;
    border: 0;
    box-shadow: none;
    font-size: 11pt;
  }

  html[data-print='document'] .paper__logo {
    top: 0;
    right: 0;
    width: 16mm;
  }

  html[data-print='document'] .paper--letter .block--sender {
    padding-right: 20mm;
  }

  html[data-print='document'] .paper .restore,
  html[data-print='document'] .paper .block--removed {
    display: none !important;
  }
}
</style>
