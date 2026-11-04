<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseDialog from '@/components/BaseDialog.vue'
import GalleryUploadList from '@/components/events/GalleryUploadList.vue'
import MediaSwiper from '@/components/events/MediaSwiper.vue'
import { useGalleryUpload } from '@/composables/useGalleryUpload'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import type { EventPhoto } from '@/models/ClubEvent'
import { GALLERY_LABELS, galleryItems, type GalleryItem, type GalleryRights, type GalleryVideo } from '@/models/Gallery'
import { VideoUploadService } from '@/services/VideoUploadService'

type Filter = 'all' | 'photo' | 'video'

const PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp'

const props = defineProps<{
  eventId: string
  eventTitle: string
  eventDate: Date
  photos: EventPhoto[]
  videos: GalleryVideo[]
  rights: GalleryRights
  canManage: boolean
}>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()

const filter = ref<Filter>('all')
/** Média ouvert en plein écran (null : fenêtre fermée), puis celui qu'elle montre */
const fullscreen = ref<number | null>(null)
const fullscreenAt = ref(0)
const inline = useTemplateRef<InstanceType<typeof MediaSwiper>>('inline')
const uploads = useGalleryUpload(
  () => props.eventId,
  () => emit('changed'),
)

const canUpload = computed(() => props.rights.canAddPhotos || props.rights.canAddVideos)
const accept = computed(() => (props.rights.canAddVideos ? [PHOTO_ACCEPT, ...VideoUploadService.TYPES].join(',') : PHOTO_ACCEPT))
const uploadLabel = computed(() => (props.rights.canAddVideos ? t('events.gallery.uploadMedia') : t('events.gallery.upload')))
const items = computed(() => galleryItems(props.photos, props.videos))
const visible = computed(() => items.value.filter((item) => filter.value === 'all' || item.kind === filter.value))
const filters = computed(() => [
  { value: 'all' as const, label: t('events.gallery.filters.all', { n: items.value.length }) },
  { value: 'photo' as const, label: t('events.gallery.filters.photos', { n: props.photos.length }) },
  { value: 'video' as const, label: t('events.gallery.filters.videos', { n: props.videos.length }) },
])
const fullscreenTitle = computed(() => {
  const item = visible.value[fullscreenAt.value]
  return item ? t(GALLERY_LABELS[item.kind].position, { index: fullscreenAt.value + 1, total: visible.value.length }) : ''
})

/**
 * Texte d'une photo ou d'une vidéo pour les lecteurs d'écran et les moteurs (alt) : la légende de son
 * auteur, sinon une légende tirée de la soirée (« Photo de « Soirée fléchettes », le 10 octobre 2026,
 * ajoutée par Bob »). Jamais vide, quoi que l'adhérent ait envoyé.
 */
function describe(item: GalleryItem): string {
  const date = formatDate(props.eventDate, { dateStyle: 'long', timeZone: CLUB_TIME_ZONE })
  if (item.kind === 'photo') return item.photo.caption ?? t('events.gallery.photoFrom', { event: props.eventTitle, date, name: item.photo.uploader })
  return t('events.gallery.videoFrom', { event: props.eventTitle, date, name: item.video.uploader })
}

function onFiles(event: Event): void {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (files.length) void uploads.add(files)
}

function openFullscreen(at: number): void {
  fullscreenAt.value = at
  fullscreen.value = at
}

/** En sortant du plein écran, la galerie de la page montre le média où l'on s'est arrêté */
function closeFullscreen(): void {
  inline.value?.show(fullscreenAt.value)
  fullscreen.value = null
}

function onMediaChanged(): void {
  fullscreen.value = null
  emit('changed')
}
</script>

<template>
  <section class="stack gallery" aria-labelledby="gallery-title">
    <div class="head">
      <div class="head__row">
        <h2 id="gallery-title">{{ t('events.gallery.title') }} <span class="muted">({{ items.length }})</span></h2>
        <!-- Le champ porte son nom entier, quel que soit le texte affiché (« + Ajouter » au téléphone) -->
        <label v-if="canUpload" class="upload">
          <input type="file" :accept="accept" multiple class="visually-hidden" :aria-label="uploadLabel" @change="onFiles" />
          <span class="upload__label" aria-hidden="true">{{ uploadLabel }}</span>
          <span class="upload__short" aria-hidden="true">+ {{ t('events.gallery.uploadShort') }}</span>
        </label>
      </div>
      <!-- Toute la largeur sous le titre : une ligne de moins à côté du bouton -->
      <p class="muted members-only">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
        {{ t('events.gallery.membersOnly') }}
      </p>
    </div>

    <ul v-if="rights.canAddVideos" class="rules">
      <li>{{ t('events.gallery.rules.videos') }}</li>
      <li>{{ t('events.gallery.rules.photos') }}</li>
      <li>{{ t('events.gallery.rules.consent') }}</li>
    </ul>

    <GalleryUploadList v-if="uploads.lines.value.length" :lines="uploads.lines.value" />

    <div v-if="videos.length && photos.length" role="group" :aria-label="t('events.gallery.filterLabel')" class="filters">
      <button
        v-for="f in filters"
        :key="f.value"
        type="button"
        class="filter"
        :class="{ 'filter--on': filter === f.value }"
        :aria-pressed="filter === f.value"
        @click="filter = f.value"
      >
        {{ f.label }}
      </button>
    </div>

    <p v-if="!items.length" class="muted">{{ t('events.gallery.empty') }}</p>

    <!-- Un filtre change la série : le carrousel repart du début -->
    <MediaSwiper
      v-if="visible.length"
      ref="inline"
      :key="filter"
      :items="visible"
      :describe="describe"
      :rights="rights"
      :can-manage="canManage"
      :suspended="fullscreen !== null"
      @expand="openFullscreen"
      @changed="onMediaChanged"
    />

    <p v-if="items.length" class="muted hint">{{ t('events.gallery.removalHint') }}</p>

    <BaseDialog v-if="fullscreen !== null" fullscreen :title="fullscreenTitle" @close="closeFullscreen">
      <MediaSwiper
        fullscreen
        :items="visible"
        :start="fullscreen"
        :describe="describe"
        :rights="rights"
        :can-manage="canManage"
        @move="fullscreenAt = $event"
        @changed="onMediaChanged"
      />
    </BaseDialog>
  </section>
</template>

<style scoped>
/* Un peu plus serré que la page : le titre, les filtres et le carrousel tiennent dans un écran */
.gallery {
  gap: 0.75rem;
}

.head {
  container: gallery-head / inline-size;
  display: grid;
  gap: 0.25rem;
}

.head__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}

h2 {
  min-width: 0;
}

h2,
.head p {
  margin: 0;
}

.members-only {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
}

.upload {
  position: relative;
  display: inline-flex;
  flex: none;
  align-items: center;
  min-height: 44px;
  padding: 0 1.2rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  cursor: pointer;
}

.upload:focus-within {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.upload__short {
  display: none;
  white-space: nowrap;
}

/* Téléphone : « + Ajouter » garde le titre sur une ligne, et la galerie tient dans l'écran */
@container gallery-head (width < 40rem) {
  .head__row {
    flex-wrap: nowrap;
  }

  .upload {
    padding-inline: 0.9rem;
  }

  .upload__label {
    display: none;
  }

  .upload__short {
    display: inline;
  }
}

.rules {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0.75rem 1rem 0.75rem 2rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  font-size: 0.9rem;
}

/*
 * Une seule ligne, quelle que soit la police de l'appareil (sur une ligne de plus, les vignettes
 * passeraient sous l'écran) : au besoin, la bande glisse de côté. Marge intérieure rendue au-dehors :
 * l'anneau de focus n'est pas rogné.
 */
.filters {
  display: flex;
  gap: 0.5rem;
  margin: -0.25rem;
  padding: 0.25rem;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: none;
}

.filters::-webkit-scrollbar {
  display: none;
}

.filter {
  flex: none;
  min-height: 44px;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.filter--on {
  border-color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 16%, transparent);
}

.hint {
  margin: 0;
  font-size: 0.9rem;
}
</style>
