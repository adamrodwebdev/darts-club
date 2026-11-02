<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { GalleryItem, GalleryRights } from '@/models/Gallery'
import { useServices } from '@/services'

/** AV1 d'abord (le plus léger), H.264 pour les navigateurs qui ne le décodent pas */
const AV1_TYPE = 'video/mp4; codecs="av01.0.05M.08, mp4a.40.2"'
const H264_TYPE = 'video/mp4; codecs="avc1.64001F, mp4a.40.2"'

/** `description` : légende de l'auteur ou légende tirée de la soirée (EventGallery), jamais vide */
const props = defineProps<{ item: GalleryItem; description: string; index: number; total: number; rights: GalleryRights; canManage: boolean }>()
const emit = defineEmits<{ close: []; step: [delta: number]; changed: [] }>()
const { t } = useI18n()
const services = useServices()
const action = useAsyncAction()
const confirmingRemoval = ref(false)

const photo = computed(() => (props.item.kind === 'photo' ? props.item.photo : null))
const video = computed(() => (props.item.kind === 'video' ? props.item.video : null))
const media = computed(() => photo.value ?? video.value)
const mediaRef = computed(() => ({ kind: props.item.kind, id: media.value?.id ?? '' }))
const title = computed(() =>
  photo.value
    ? t('events.gallery.position', { index: props.index + 1, total: props.total })
    : t('events.gallery.positionVideo', { index: props.index + 1, total: props.total }),
)
const pendingText = computed(() => {
  const v = video.value
  if (!v || v.isReady) return null
  return v.status === 'echec' ? t('events.gallery.failedVideo') : t('events.gallery.convertingHint')
})

function onKey(event: KeyboardEvent): void {
  if (event.key === 'ArrowRight') emit('step', 1)
  if (event.key === 'ArrowLeft') emit('step', -1)
}

async function run(task: () => Promise<void>): Promise<void> {
  if (await action.run(async () => (await task(), true))) emit('changed')
}

const remove = () => run(() => services.events.deleteMedia(mediaRef.value))
const requestRemoval = () => run(() => services.events.requestRemoval(mediaRef.value))
const restore = () => run(() => services.events.restoreMedia(mediaRef.value))
</script>

<template>
  <BaseDialog :title="title" @close="emit('close')">
    <div class="viewer" tabindex="0" @keydown="onKey">
      <img
        v-if="photo"
        :src="photo.largeUrl"
        :srcset="`${photo.thumbUrl} 480w, ${photo.largeUrl} 1600w`"
        sizes="(min-width: 32.5rem) 27.5rem, calc(100vw - 4.5rem)"
        :alt="description"
        :width="photo.width"
        :height="photo.height"
      />
      <!-- Rien n'est téléchargé avant la lecture : l'aperçu seul (AVIF ou WebP) -->
      <video
        v-else-if="video && video.isReady"
        controls
        playsinline
        preload="none"
        :poster="video.posterUrl ?? undefined"
        :width="video.width ?? undefined"
        :height="video.height ?? undefined"
        :aria-label="description"
      >
        <source v-if="video.av1Url" :src="video.av1Url" :type="AV1_TYPE" />
        <source v-if="video.videoUrl" :src="video.videoUrl" :type="H264_TYPE" />
      </video>
      <p v-if="pendingText" class="notice" role="status">{{ pendingText }}</p>

      <p class="muted">{{ t('events.gallery.addedBy', { name: media?.uploader ?? '' }) }}</p>
      <p v-if="media?.hidden" class="notice notice--error" role="status">{{ t('events.gallery.hiddenBy', { name: media.hiddenBy ?? '' }) }}</p>

      <div class="buttons">
        <BaseButton variant="ghost" :disabled="total < 2" @click="emit('step', -1)">{{ t('events.gallery.previous') }}</BaseButton>
        <BaseButton variant="ghost" :disabled="total < 2" @click="emit('step', 1)">{{ t('events.gallery.next') }}</BaseButton>
        <BaseButton v-if="rights.canModerate && media?.hidden" :pending="action.pending.value" @click="restore">{{ t('events.gallery.restore') }}</BaseButton>
        <BaseButton v-if="media && !media.mine && !media.hidden" variant="ghost" @click="confirmingRemoval = true">{{ t('events.gallery.requestRemoval') }}</BaseButton>
        <BaseButton v-if="media && (media.mine || canManage)" variant="danger" :pending="action.pending.value" @click="remove">{{ t('common.delete') }}</BaseButton>
      </div>

      <div v-if="confirmingRemoval" class="confirm">
        <p>{{ t('events.gallery.removalText') }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="confirmingRemoval = false">{{ t('common.cancel') }}</BaseButton>
          <BaseButton variant="danger" :pending="action.pending.value" @click="requestRemoval">{{ t('events.gallery.requestRemoval') }}</BaseButton>
        </div>
      </div>
      <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
    </div>
  </BaseDialog>
</template>

<style scoped>
.viewer {
  display: grid;
  gap: 0.75rem;
}

.viewer img,
.viewer video {
  display: block;
  width: 100%;
  height: auto;
  max-height: 70dvh;
  object-fit: contain;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.viewer > p {
  margin: 0;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.confirm {
  display: grid;
  gap: 0.5rem;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.confirm p {
  margin: 0;
}
</style>
