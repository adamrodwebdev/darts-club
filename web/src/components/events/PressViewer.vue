<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseDialog from '@/components/BaseDialog.vue'
import MediaActions from '@/components/events/MediaActions.vue'
import { galleryItems, type GalleryRights } from '@/models/Gallery'
import type { PressClipping } from '@/models/PressClipping'
import { wrapIndex } from '@/motion/orbit'

/** Plein écran : la fenêtre moins ses marges */
const SIZES = 'calc(100vw - 1.5rem)'

/** Touches de la visionneuse : la coupure que chacune montre (la série boucle, comme l'anneau) */
const KEYS = new Map<string, (current: number) => number>([
  ['ArrowRight', (current) => current + 1],
  ['ArrowLeft', (current) => current - 1],
])

/**
 * Une coupure de presse en grand, dans la fenêtre plein écran de la galerie (cadre sombre dans les
 * deux thèmes) : l'image entière, ses détails, et ce que l'adhérent peut en faire (demander le
 * retrait, la remettre en ligne, la supprimer ; le bureau en corrige les détails). Flèches et ← → pour
 * passer à la voisine ; `move` dit laquelle est montrée, pour que l'anneau la garde devant.
 */
const props = defineProps<{
  clippings: readonly PressClipping[]
  start: number
  /** Titre de la soirée : le texte de l'image quand la coupure n'a ni titre ni journal */
  eventTitle: string
  rights: GalleryRights
  canManage: boolean
}>()
const emit = defineEmits<{ move: [index: number]; changed: []; close: [] }>()
const { t } = useI18n()

const index = ref(wrapIndex(props.start, props.clippings.length))
const total = computed(() => props.clippings.length)
const current = computed(() => props.clippings[index.value] ?? null)
/** La photo vue comme une case de la galerie : mêmes actions, mêmes droits */
const item = computed(() => (current.value === null ? null : (galleryItems([current.value.photo], [])[0] ?? null)))
const title = computed(() => t('events.press.position', { index: index.value + 1, total: total.value }))

/** Texte de l'image pour les lecteurs d'écran : titre et journal s'ils sont connus, sinon la soirée */
const description = computed(() => {
  const details = current.value?.details
  if (!details) return ''
  if (details.title !== null && details.outlet !== null) return t('events.press.alt', { outlet: details.outlet, title: details.title })
  if (details.title !== null) return t('events.press.altTitle', { title: details.title })
  if (details.outlet !== null) return t('events.press.altOutlet', { outlet: details.outlet })
  return t('events.press.altEvent', { event: props.eventTitle })
})

function go(target: number): void {
  index.value = wrapIndex(target, total.value)
  emit('move', index.value)
}

/**
 * Seulement depuis cette fenêtre : une fenêtre ouverte par-dessus (détails de la coupure, confirmation)
 * est dans le même arbre, et ses champs gardent leurs flèches.
 */
function onKey(event: KeyboardEvent): void {
  const target = KEYS.get(event.key)
  if (target === undefined || total.value < 2 || event.altKey || event.ctrlKey || event.metaKey) return
  const from = event.target instanceof Element ? event.target.closest('dialog') : null
  if (!(event.currentTarget instanceof Element) || from !== event.currentTarget.closest('dialog')) return
  event.preventDefault()
  go(target(index.value))
}
</script>

<template>
  <BaseDialog fullscreen :title="title" @close="emit('close')">
    <div v-if="current && item" class="viewer" @keydown="onKey">
      <figure class="viewer__figure">
        <div class="viewer__stage">
          <!-- La vignette (déjà vue dans l'anneau) tout de suite, la grande image par-dessus dès qu'elle arrive -->
          <img :key="`${current.id}-preview`" class="viewer__image" :src="current.photo.thumbUrl" :width="current.photo.width" :height="current.photo.height" alt="" decoding="async" />
          <img
            :key="current.id"
            class="viewer__image"
            :src="current.photo.largeUrl"
            :srcset="`${current.photo.thumbUrl} 480w, ${current.photo.largeUrl} 1600w`"
            :sizes="SIZES"
            :width="current.photo.width"
            :height="current.photo.height"
            :alt="description"
            decoding="async"
          />
          <template v-if="total > 1">
            <button type="button" class="viewer__edge viewer__edge--previous" :aria-label="t('events.press.previous')" @click="go(index - 1)">
              <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
            <button type="button" class="viewer__edge viewer__edge--next" :aria-label="t('events.press.next')" @click="go(index + 1)">
              <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
          </template>
        </div>
        <figcaption class="viewer__caption">
          <span v-if="current.details.outlet || current.details.publishedOn" class="viewer__source">
            <span v-if="current.details.outlet">{{ current.details.outlet }}</span>
            <time v-if="current.details.publishedOn" :datetime="current.details.publishedOn">{{ current.details.publishedLabel }}</time>
          </span>
          <span v-if="current.details.title" class="viewer__title">{{ current.details.title }}</span>
          <span v-if="current.details.credit" class="viewer__credit">{{ current.details.credit }}</span>
        </figcaption>
      </figure>
      <MediaActions :key="current.id" :item="item" :rights="rights" :can-manage="canManage" @changed="emit('changed')" />
    </div>
  </BaseDialog>
</template>

<style scoped>
/* L'image prend toute la place que laissent ses détails et ses boutons, sans rien faire défiler */
.viewer {
  display: grid;
  grid-template-rows: minmax(0, 1fr) auto;
  gap: 0.5rem;
  min-height: 0;
}

.viewer__figure {
  display: grid;
  grid-template-rows: minmax(0, 1fr) auto;
  gap: 0.6rem;
  min-height: 0;
  margin: 0;
}

/* L'image ne pèse pas sur la mise en page : la scène prend la place libre, l'image s'y ajuste */
.viewer__stage {
  position: relative;
  min-height: 0;
  overflow: hidden;
}

.viewer__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.viewer__edge {
  position: absolute;
  inset-block-start: 50%;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 1px solid var(--color-border);
  border-radius: 50%;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  translate: 0 -50%;
}

.viewer__edge--previous {
  inset-inline-start: 0.25rem;
}

.viewer__edge--next {
  inset-inline-end: 0.25rem;
}

.viewer__caption {
  display: grid;
  gap: 0.2rem;
  max-width: 48rem;
}

.viewer__source {
  display: flex;
  flex-wrap: wrap;
  gap: 0 0.6rem;
  color: var(--color-muted);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.viewer__title {
  font-family: Georgia, 'Times New Roman', Times, serif;
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.viewer__credit {
  color: var(--color-muted);
  font-size: 0.85rem;
  overflow-wrap: anywhere;
}
</style>
