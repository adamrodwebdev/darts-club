<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GalleryItem, VideoStatus } from '@/models/Gallery'

/** AV1 d'abord (le plus léger), H.264 pour les navigateurs qui ne le décodent pas */
const AV1_TYPE = 'video/mp4; codecs="av01.0.05M.08, mp4a.40.2"'
const H264_TYPE = 'video/mp4; codecs="avc1.64001F, mp4a.40.2"'

/** Vidéo pas encore lisible : pastille et explication selon son étape */
const PENDING_VIDEO: Partial<Record<VideoStatus, { badge: string; hint: string }>> = {
  envoi: { badge: 'events.gallery.convertingBadge', hint: 'events.gallery.convertingHint' },
  conversion: { badge: 'events.gallery.convertingBadge', hint: 'events.gallery.convertingHint' },
  echec: { badge: 'events.gallery.failedBadge', hint: 'events.gallery.failedVideo' },
}

/**
 * Une photo ou une vidéo du carrousel, entière dans son cadre, sur un fond tiré d'elle-même (flou).
 * `near` : assez proche de celle à l'écran pour charger la grande image (gardée ensuite) ; `active` :
 * celle à l'écran, seule à jouer (une vidéo qui ne l'est plus s'arrête) ; `sizes` : largeur du cadre,
 * déduite du CSS du carrousel. Rien ne se télécharge d'une vidéo avant « Lire » : l'aperçu seul.
 */
const props = defineProps<{ item: GalleryItem; description: string; index: number; total: number; active: boolean; near: boolean; sizes: string }>()
const { t } = useI18n()
const player = useTemplateRef<HTMLVideoElement>('player')
const started = ref(false)
const loaded = ref(props.near)

watch(
  () => props.near,
  (near) => {
    if (near) loaded.value = true
  },
)

watch(
  () => props.active,
  (active) => {
    if (!active) player.value?.pause()
  },
)

const photo = computed(() => (props.item.kind === 'photo' ? props.item.photo : null))
const video = computed(() => (props.item.kind === 'video' ? props.item.video : null))
const pending = computed(() => (video.value === null ? undefined : PENDING_VIDEO[video.value.status]))
const badge = computed(() => (props.item.media.hidden ? t('events.gallery.hiddenBadge') : pending.value ? t(pending.value.badge) : null))

function play(): void {
  const element = player.value
  if (element === null) return
  started.value = true
  // Refusée ou illisible : les commandes du lecteur, désormais affichées, permettent de réessayer
  element.play().catch(() => undefined)
  // Le bouton disparaît : le focus passe au lecteur
  void nextTick(() => element.focus({ preventScroll: true }))
}
</script>

<template>
  <div class="media-slide">
    <img v-if="loaded && item.preview.url" class="media-slide__ambient" :src="item.preview.url" alt="" decoding="async" draggable="false" />
    <img
      v-if="photo && loaded"
      class="media-slide__media"
      :src="photo.largeUrl"
      :srcset="`${photo.thumbUrl} 480w, ${photo.largeUrl} 1600w`"
      :sizes="sizes"
      :alt="description"
      :width="photo.width"
      :height="photo.height"
      decoding="async"
      draggable="false"
    />
    <template v-else-if="video">
      <video
        v-if="video.isReady"
        ref="player"
        class="media-slide__media"
        :controls="started"
        playsinline
        preload="none"
        :poster="loaded ? (video.posterUrl ?? undefined) : undefined"
        :width="video.width ?? undefined"
        :height="video.height ?? undefined"
        :aria-label="description"
        @play="started = true"
      >
        <source v-if="video.av1Url" :src="video.av1Url" :type="AV1_TYPE" />
        <source v-if="video.videoUrl" :src="video.videoUrl" :type="H264_TYPE" />
      </video>
      <button
        v-if="video.isReady && !started"
        type="button"
        class="media-slide__play"
        :aria-label="t('events.gallery.play', { index: index + 1, total, duration: video.durationLabel })"
        @click="play"
      >
        <svg width="34" height="34" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor" /></svg>
      </button>
      <span v-if="video.durationLabel && !started" class="media-slide__chip media-slide__duration" aria-hidden="true">{{ video.durationLabel }}</span>
      <p v-if="pending" class="media-slide__pending">{{ t(pending.hint) }}</p>
    </template>
    <!-- Crédit sur l'image, comme dans les albums photo : la ligne sous la photo garde ses seuls boutons -->
    <p v-if="!started" class="media-slide__credits">
      <span class="media-slide__chip">{{ t('events.gallery.addedBy', { name: item.media.uploader }) }}</span>
      <span v-if="badge" class="media-slide__chip">{{ badge }}</span>
    </p>
  </div>
</template>

<style scoped>
/* Cadre opaque : la photo suivante glisse par-dessus celle-ci (ombre portée sur son bord) */
.media-slide {
  position: absolute;
  inset: 0;
  overflow: hidden;
  background: var(--media-stage);
  box-shadow: -1.25rem 0 2.5rem -1.25rem var(--media-scrim);
}

/* La photo elle-même, floue et assombrie, remplit les bandes autour d'elle */
.media-slide__ambient {
  position: absolute;
  inset: -12%;
  width: 124%;
  max-inline-size: none;
  height: 124%;
  object-fit: cover;
  filter: blur(1.6rem) saturate(1.3) brightness(0.5);
}

.media-slide__media {
  position: absolute;
  inset: 0;
  width: 100%;
  max-inline-size: none;
  height: 100%;
  object-fit: contain;
}

.media-slide__play {
  position: absolute;
  inset: 50% auto auto 50%;
  display: grid;
  place-items: center;
  width: 4.75rem;
  height: 4.75rem;
  padding: 0;
  border: 2px solid color-mix(in srgb, var(--on-media) 85%, transparent);
  border-radius: 50%;
  background: var(--media-scrim);
  color: var(--on-media);
  cursor: pointer;
  translate: -50% -50%;
  transition: transform 0.2s ease;
}

.media-slide__play:hover {
  transform: scale(1.06);
}

.media-slide__play:active {
  transform: scale(0.94);
}

.media-slide__chip {
  padding: 0.2rem 0.55rem;
  border-radius: 0.45rem;
  background: var(--media-scrim);
  color: var(--on-media);
  font-size: 0.82rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.media-slide__duration {
  position: absolute;
  inset: auto 0.75rem 0.75rem auto;
}

/* En bas à gauche, la durée d'une vidéo gardant le coin droit */
.media-slide__credits {
  position: absolute;
  inset: auto 4.5rem 0.75rem 0.75rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin: 0;
  pointer-events: none;
}

.media-slide__pending {
  position: absolute;
  inset: 50% 1rem auto;
  max-width: 26rem;
  margin: 0 auto;
  padding: 0.75rem 1rem;
  border-radius: var(--radius);
  background: var(--media-scrim);
  color: var(--on-media);
  text-align: center;
  translate: 0 -50%;
}
</style>
