<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { TEXT_LIMITS } from '@/models/limits'
import { isoDay, type PressDetails } from '@/models/PressClipping'
import { useServices } from '@/services'

/**
 * Le bureau classe une photo de la galerie parmi les coupures de presse, ou en corrige les détails
 * (`details` : ceux de la coupure, null pour une photo de la galerie). Les quatre champs sont
 * facultatifs ; la date vient du calendrier de l'appareil et reste vérifiée par l'API.
 */
const props = defineProps<{ photoId: string; details: PressDetails | null }>()
const emit = defineEmits<{ saved: []; close: [] }>()
const { t } = useI18n()
const services = useServices()
const action = useAsyncAction()

const outlet = ref(props.details?.outlet ?? '')
const publishedOn = ref(props.details?.publishedOn ?? '')
const title = ref(props.details?.title ?? '')
const credit = ref(props.details?.credit ?? '')
const dateError = ref<string>()

async function save(): Promise<void> {
  // Le champ date donne « AAAA-MM-JJ » ou rien ; un autre texte (navigateur sans calendrier) est refusé ici
  const day = publishedOn.value.trim()
  dateError.value = day === '' || isoDay(day) !== null ? undefined : t('events.press.dateInvalid')
  if (dateError.value !== undefined) return
  const draft = { outlet: outlet.value, publishedOn: day, title: title.value, credit: credit.value }
  if (await action.run(async () => (await services.events.savePressClipping(props.photoId, draft), true))) emit('saved')
}

async function returnToGallery(): Promise<void> {
  if (await action.run(async () => (await services.events.returnToGallery(props.photoId), true))) emit('saved')
}
</script>

<template>
  <BaseDialog :title="details ? t('events.press.editTitle') : t('events.press.fileTitle')" @close="emit('close')">
    <form class="stack press-form" novalidate @submit.prevent="save">
      <p class="muted press-form__hint">{{ t('events.press.formHint') }}</p>
      <BaseField v-model="outlet" :label="t('events.press.outlet')" :maxlength="TEXT_LIMITS.pressOutlet" :error="action.fieldErrors.value.outlet" />
      <BaseField v-model="publishedOn" type="date" :label="t('events.press.publishedOn')" :error="dateError ?? action.fieldErrors.value.publishedOn" />
      <BaseField v-model="title" :label="t('events.press.headline')" :maxlength="TEXT_LIMITS.pressTitle" :error="action.fieldErrors.value.title" />
      <BaseField
        v-model="credit"
        :label="t('events.press.credit')"
        :hint="t('events.press.creditHint')"
        :maxlength="TEXT_LIMITS.pressCredit"
        :error="action.fieldErrors.value.credit"
      />
      <p v-if="details" class="muted press-form__hint">{{ t('events.press.editHint') }}</p>
      <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
      <div class="press-form__buttons">
        <BaseButton v-if="details" variant="danger" class="press-form__back" :pending="action.pending.value" @click="returnToGallery">{{ t('events.press.unmark') }}</BaseButton>
        <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :pending="action.pending.value">{{ t('common.save') }}</BaseButton>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.press-form__hint {
  margin: 0;
}

.press-form__buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

/* À l'écart des deux autres : rendre la photo à la galerie efface ses détails */
.press-form__back {
  margin-inline-end: auto;
}
</style>
