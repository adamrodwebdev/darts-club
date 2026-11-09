<script setup lang="ts">
import { ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import PressOrbit from '@/components/events/PressOrbit.vue'
import PressViewer from '@/components/events/PressViewer.vue'
import type { GalleryRights } from '@/models/Gallery'
import type { PressClipping } from '@/models/PressClipping'

/**
 * « Dans la presse », au-dessus de la galerie d'une soirée : les coupures que le bureau y a classées
 * (réservées aux membres, comme la galerie), en orbite autour du titre, chacune ouverte en grand.
 */
defineProps<{
  clippings: readonly PressClipping[]
  eventTitle: string
  rights: GalleryRights
  canManage: boolean
}>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()
const orbit = useTemplateRef<InstanceType<typeof PressOrbit>>('orbit')
/** Coupure ouverte en grand (null : fenêtre fermée), puis celle que la fenêtre montre */
const viewing = ref<number | null>(null)
const shown = ref(0)

function open(index: number): void {
  shown.value = index
  viewing.value = index
}

/** Fermée par l'adhérent : l'anneau garde devant la dernière coupure vue, et le focus y revient */
function close(): void {
  if (viewing.value === null) return
  viewing.value = null
  orbit.value?.returnTo(shown.value)
}

/** Une action a changé les coupures : la page les recharge, la fenêtre se ferme sans rien viser */
function onChanged(): void {
  viewing.value = null
  emit('changed')
}
</script>

<template>
  <section class="press" aria-labelledby="press-title">
    <PressOrbit ref="orbit" title-id="press-title" :clippings="clippings" @open="open">
      <template #default="{ ring }">
        <!-- Au centre sous l'anneau, au fil de la page sous la grille -->
        <p class="muted members-only" :class="{ 'members-only--centred': ring }">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
          {{ t('events.gallery.membersOnly') }}
        </p>
      </template>
    </PressOrbit>

    <PressViewer
      v-if="viewing !== null"
      :clippings="clippings"
      :start="viewing"
      :event-title="eventTitle"
      :rights="rights"
      :can-manage="canManage"
      @move="shown = $event"
      @changed="onChanged"
      @close="close"
    />
  </section>
</template>

<style scoped>
.press {
  min-width: 0;
}

.members-only {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0.5rem 0 0;
  font-size: 0.9rem;
}

.members-only--centred {
  justify-content: center;
}
</style>
