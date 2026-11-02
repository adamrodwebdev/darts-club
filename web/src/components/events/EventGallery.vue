<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GalleryUploadList from '@/components/events/GalleryUploadList.vue'
import MediaViewer from '@/components/events/MediaViewer.vue'
import { useGalleryUpload } from '@/composables/useGalleryUpload'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import type { EventPhoto } from '@/models/ClubEvent'
import { galleryItems, type GalleryItem, type GalleryRights, type GalleryVideo } from '@/models/Gallery'
import { VideoUploadService } from '@/services/VideoUploadService'

type Filter = 'all' | 'photo' | 'video'

interface Tile {
  key: string
  src: string | null
  width: number | undefined
  height: number | undefined
  label: string
  isVideo: boolean
  duration: string
  badge: string | null
}

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
const openKey = ref<string | null>(null)
const uploads = useGalleryUpload(
  () => props.eventId,
  () => emit('changed'),
)

const canUpload = computed(() => props.rights.canAddPhotos || props.rights.canAddVideos)
const accept = computed(() => (props.rights.canAddVideos ? [PHOTO_ACCEPT, ...VideoUploadService.TYPES].join(',') : PHOTO_ACCEPT))
const items = computed(() => galleryItems(props.photos, props.videos))
const visible = computed(() => items.value.filter((item) => filter.value === 'all' || item.kind === filter.value))
const openIndex = computed(() => visible.value.findIndex((item) => item.key === openKey.value))
const current = computed(() => visible.value[openIndex.value] ?? null)
const filters = computed(() => [
  { value: 'all' as const, label: t('events.gallery.filters.all', { n: items.value.length }) },
  { value: 'photo' as const, label: t('events.gallery.filters.photos', { n: props.photos.length }) },
  { value: 'video' as const, label: t('events.gallery.filters.videos', { n: props.videos.length }) },
])

/** Vue d'une case : vignette de la photo, ou aperçu de la vidéo avec sa durée */
function tile(item: GalleryItem, index: number, total: number): Tile {
  const position = { index: index + 1, total }
  if (item.kind === 'photo') {
    const p = item.photo
    return {
      key: item.key,
      src: p.thumbUrl,
      width: p.width,
      height: p.height,
      label: t('events.gallery.enlarge', position),
      isVideo: false,
      duration: '',
      badge: p.hidden ? t('events.gallery.hiddenBadge') : null,
    }
  }
  const v = item.video
  return {
    key: item.key,
    src: v.posterUrl,
    width: v.width ?? undefined,
    height: v.height ?? undefined,
    label: v.isReady ? t('events.gallery.play', { ...position, duration: v.durationLabel }) : t('events.gallery.pendingTile', position),
    isVideo: true,
    duration: v.durationLabel,
    badge: v.hidden ? t('events.gallery.hiddenBadge') : v.status === 'echec' ? t('events.gallery.failedBadge') : v.isReady ? null : t('events.gallery.convertingBadge'),
  }
}

const tiles = computed(() => visible.value.map((item, index) => tile(item, index, visible.value.length)))

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

function step(delta: number): void {
  const list = visible.value
  if (list.length < 2 || openIndex.value < 0) return
  openKey.value = list[(openIndex.value + delta + list.length) % list.length]?.key ?? null
}

function onMediaChanged(): void {
  openKey.value = null
  emit('changed')
}
</script>

<template>
  <section class="stack" aria-labelledby="gallery-title">
    <div class="head">
      <div class="head__text">
        <h2 id="gallery-title">{{ t('events.gallery.title') }} <span class="muted">({{ items.length }})</span></h2>
        <p class="muted members-only">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
          {{ t('events.gallery.membersOnly') }}
        </p>
      </div>
      <label v-if="canUpload" class="upload">
        <input type="file" :accept="accept" multiple class="visually-hidden" @change="onFiles" />
        <span>{{ rights.canAddVideos ? t('events.gallery.uploadMedia') : t('events.gallery.upload') }}</span>
      </label>
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

    <ul class="grid">
      <li v-for="tl in tiles" :key="tl.key">
        <button type="button" class="thumb" :aria-label="tl.label" @click="openKey = tl.key">
          <!-- Dimensions déclarées : pas de décalage de mise en page ; AVIF ou WebP selon le navigateur -->
          <img v-if="tl.src" :src="tl.src" alt="" :width="tl.width" :height="tl.height" loading="lazy" decoding="async" />
          <span v-if="tl.isVideo" class="thumb__play" aria-hidden="true">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M10 8.5l6 3.5-6 3.5z" /></svg>
          </span>
          <span v-if="tl.duration" class="thumb__duration" aria-hidden="true">{{ tl.duration }}</span>
          <span v-if="tl.badge" class="thumb__badge">{{ tl.badge }}</span>
        </button>
      </li>
    </ul>

    <p v-if="items.length" class="muted hint">{{ t('events.gallery.removalHint') }}</p>

    <MediaViewer
      v-if="current"
      :item="current"
      :description="describe(current)"
      :index="openIndex"
      :total="visible.length"
      :rights="rights"
      :can-manage="canManage"
      @close="openKey = null"
      @step="step"
      @changed="onMediaChanged"
    />
  </section>
</template>

<style scoped>
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.head__text {
  display: grid;
  gap: 0.25rem;
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
  display: inline-flex;
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

.rules {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0.75rem 1rem 0.75rem 2rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  font-size: 0.9rem;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.filter {
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

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(9rem, 45%), 1fr));
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.thumb {
  position: relative;
  display: block;
  width: 100%;
  aspect-ratio: 1;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  color: var(--color-text);
  cursor: zoom-in;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.25s ease;
}

.thumb:hover img {
  transform: scale(1.05);
}

.thumb__play {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: var(--on-media);
  filter: drop-shadow(0 1px 3px var(--media-scrim));
}

.thumb__duration,
.thumb__badge {
  position: absolute;
  padding: 0.1rem 0.45rem;
  border-radius: 0.4rem;
  background: var(--media-scrim);
  color: var(--on-media);
  font-size: 0.8rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.thumb__duration {
  right: 0.4rem;
  bottom: 0.4rem;
}

.thumb__badge {
  top: 0.4rem;
  left: 0.4rem;
}

.hint {
  margin: 0;
  font-size: 0.9rem;
}

@media (prefers-reduced-motion: reduce) {
  .thumb img {
    transition: none;
  }
}
</style>
