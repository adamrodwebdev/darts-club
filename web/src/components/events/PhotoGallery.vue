<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { EventPhoto } from '@/models/ClubEvent'
import { services } from '@/services'

const props = defineProps<{ eventId: string; photos: EventPhoto[]; canUpload: boolean; canManage: boolean }>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()

const openIndex = ref<number | null>(null)
const current = computed(() => (openIndex.value === null ? null : props.photos[openIndex.value] ?? null))
const upload = useAsyncAction()
const remove = useAsyncAction()

function step(delta: number): void {
  if (openIndex.value === null) return
  openIndex.value = (openIndex.value + delta + props.photos.length) % props.photos.length
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'ArrowRight') step(1)
  if (event.key === 'ArrowLeft') step(-1)
}

async function onFiles(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? []).slice(0, 10)
  input.value = ''
  if (!files.length) return
  const ok = await upload.run(async () => {
    await services.events.uploadPhotos(props.eventId, files)
    return true
  })
  if (ok) emit('changed')
}

async function deleteCurrent(): Promise<void> {
  const photo = current.value
  if (!photo) return
  const ok = await remove.run(async () => {
    await services.events.deletePhoto(photo.id)
    return true
  })
  if (ok) {
    openIndex.value = null
    emit('changed')
  }
}
</script>

<template>
  <section class="stack" aria-labelledby="gallery-title">
    <div class="head">
      <h2 id="gallery-title">{{ t('events.gallery.title') }} <span class="muted">({{ photos.length }})</span></h2>
      <label v-if="canUpload" class="upload">
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="visually-hidden" @change="onFiles" />
        <span>{{ upload.pending.value ? t('events.gallery.uploading') : t('events.gallery.upload') }}</span>
      </label>
    </div>
    <p v-if="upload.error.value" class="notice notice--error" role="alert">{{ upload.error.value }}</p>
    <p v-if="!photos.length" class="muted">{{ t('events.gallery.empty') }}</p>

    <ul class="grid">
      <li v-for="(photo, index) in photos" :key="photo.id">
        <button type="button" class="thumb" :aria-label="t('events.gallery.enlarge', { index: index + 1, total: photos.length })" @click="openIndex = index">
          <!-- Dimensions déclarées : pas de décalage de mise en page -->
          <img :src="photo.thumbUrl" :alt="photo.caption ?? t('events.gallery.photoBy', { name: photo.uploader })" :width="photo.width" :height="photo.height" loading="lazy" decoding="async" />
        </button>
      </li>
    </ul>

    <BaseDialog v-if="current" :title="t('events.gallery.position', { index: (openIndex ?? 0) + 1, total: photos.length })" @close="openIndex = null">
      <div class="viewer" tabindex="0" @keydown="onKey">
        <img
          :src="current.largeUrl"
          :srcset="`${current.thumbUrl} 480w, ${current.largeUrl} 1600w`"
          sizes="(min-width: 32.5rem) 27.5rem, calc(100vw - 4.5rem)"
          :alt="current.caption ?? t('events.gallery.photoBy', { name: current.uploader })"
          :width="current.width"
          :height="current.height"
        />
        <p class="muted">{{ t('events.gallery.addedBy', { name: current.uploader }) }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" :disabled="photos.length < 2" @click="step(-1)">{{ t('events.gallery.previous') }}</BaseButton>
          <BaseButton variant="ghost" :disabled="photos.length < 2" @click="step(1)">{{ t('events.gallery.next') }}</BaseButton>
          <BaseButton v-if="current.mine || canManage" variant="danger" :pending="remove.pending.value" @click="deleteCurrent">{{ t('common.delete') }}</BaseButton>
        </div>
        <p v-if="remove.error.value" class="notice notice--error" role="alert">{{ remove.error.value }}</p>
      </div>
    </BaseDialog>
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

h2 {
  margin: 0;
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

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(9rem, 45%), 1fr));
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.thumb {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: var(--radius);
  background: var(--color-surface-2);
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

.viewer img {
  display: block;
  width: 100%;
  height: auto;
  max-height: 70dvh;
  object-fit: contain;
  border-radius: var(--radius);
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
