<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import PressClippingDialog from '@/components/events/PressClippingDialog.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { GALLERY_LABELS, type GalleryItem, type GalleryRights } from '@/models/Gallery'
import { useServices } from '@/services'

type Confirmation = 'delete' | 'removal'

/**
 * Sous la photo ou la vidéo à l'écran (son auteur est crédité sur l'image) : sa légende et ce que
 * l'adhérent peut en faire, sur une ligne. Supprimer et demander le retrait passent par une
 * confirmation : les boutons restent à portée du doigt sous chaque photo. Le bureau classe une photo
 * parmi les coupures de presse (ou corrige celles qui le sont déjà) dans sa propre fenêtre.
 */
const props = defineProps<{ item: GalleryItem; rights: GalleryRights; canManage: boolean }>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()
const services = useServices()
const action = useAsyncAction()
const confirming = ref<Confirmation | null>(null)
/** Fenêtre « coupure de presse » ouverte */
const filing = ref(false)

const media = computed(() => props.item.media)
const mediaRef = computed(() => ({ kind: props.item.kind, id: media.value.id }))
const caption = computed(() => (props.item.kind === 'photo' ? props.item.photo.caption : null))
/** Photo seulement (une vidéo n'est jamais une coupure) : ses détails de presse, null dans la galerie */
const photo = computed(() => (props.item.kind === 'photo' ? props.item.photo : null))
const pressLabel = computed(() => t(photo.value?.press ? 'events.press.edit' : 'events.press.file'))

function onFiled(): void {
  filing.value = false
  emit('changed')
}

/** Chaque confirmation : son titre, son texte, son bouton et l'appel qu'elle déclenche */
const CONFIRMATIONS: Record<Confirmation, { title: () => string; text: string; button: string; run: () => Promise<void> }> = {
  delete: {
    title: () => t(GALLERY_LABELS[props.item.kind].deleteTitle),
    text: 'events.gallery.deleteText',
    button: 'common.delete',
    run: () => services.events.deleteMedia(mediaRef.value),
  },
  removal: {
    title: () => t('events.gallery.removalTitle'),
    text: 'events.gallery.removalText',
    button: 'events.gallery.requestRemoval',
    run: () => services.events.requestRemoval(mediaRef.value),
  },
}
const confirmation = computed(() => (confirming.value === null ? null : CONFIRMATIONS[confirming.value]))

async function run(task: () => Promise<void>): Promise<void> {
  if (await action.run(async () => (await task(), true))) {
    confirming.value = null
    emit('changed')
  }
}

const confirm = () => (confirmation.value ? run(confirmation.value.run) : Promise.resolve())
const restore = () => run(() => services.events.restoreMedia(mediaRef.value))
</script>

<template>
  <div class="media-actions">
    <p v-if="caption" class="media-actions__caption">{{ caption }}</p>
    <div class="media-actions__buttons">
      <BaseButton v-if="rights.canModerate && media.hidden" :pending="action.pending.value" @click="restore">{{ t('events.gallery.restore') }}</BaseButton>
      <BaseButton v-if="!media.mine && !media.hidden" variant="ghost" @click="confirming = 'removal'">{{ t('events.gallery.requestRemoval') }}</BaseButton>
      <!-- Au téléphone, l'icône seule (la ligne ne passe jamais sur deux) : le bouton garde son nom entier -->
      <BaseButton v-if="canManage && photo" variant="ghost" class="media-actions__press" :aria-label="pressLabel" @click="filing = true">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path
            d="M4 5.5h12.5v13H6.5a2.5 2.5 0 0 1-2.5-2.5V5.5zM16.5 9H20v7.5a2.5 2.5 0 0 1-2.5 2.5M7.5 9h5.5M7.5 12.25h5.5M7.5 15.5h3.5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span class="media-actions__press-label" aria-hidden="true">{{ pressLabel }}</span>
      </BaseButton>
      <BaseButton v-if="media.mine || canManage" variant="danger" @click="confirming = 'delete'">{{ t('common.delete') }}</BaseButton>
    </div>
    <p v-if="media.hidden" class="notice notice--error media-actions__notice" role="status">{{ t('events.gallery.hiddenBy', { name: media.hiddenBy ?? '' }) }}</p>
    <p v-if="action.error.value && !confirmation" class="notice notice--error media-actions__notice" role="alert">{{ action.error.value }}</p>

    <BaseDialog v-if="confirmation" :title="confirmation.title()" @close="confirming = null">
      <div class="stack">
        <p class="media-actions__text">{{ t(confirmation.text) }}</p>
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="media-actions__confirm">
          <BaseButton variant="ghost" @click="confirming = null">{{ t('common.cancel') }}</BaseButton>
          <BaseButton variant="danger" :pending="action.pending.value" @click="confirm">{{ t(confirmation.button) }}</BaseButton>
        </div>
      </div>
    </BaseDialog>

    <PressClippingDialog v-if="filing && photo" :photo-id="photo.id" :details="photo.press" @saved="onFiled" @close="filing = false" />
  </div>
</template>

<style scoped>
.media-actions {
  container: media-actions / inline-size;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  min-height: 2.75rem;
}

/* Icône carrée de 44 px quand le libellé est caché ; icône et libellé quand la ligne a la place */
.media-actions__press {
  padding-inline: 0.6rem;
}

@container media-actions (width < 36rem) {
  .media-actions__press-label {
    display: none;
  }
}

.media-actions__caption,
.media-actions__text {
  margin: 0;
}

.media-actions__caption {
  min-width: 0;
  overflow-wrap: anywhere;
}

/* À droite, sous le pouce, avec ou sans légende à gauche */
.media-actions__buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-inline-start: auto;
}

.media-actions__confirm {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.media-actions__notice {
  flex-basis: 100%;
}
</style>
