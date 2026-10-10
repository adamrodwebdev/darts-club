<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { localInputToIso, toLocalInput } from '@/models/ClubEvent'
import { services } from '@/services'

/** Création (sans id) ou modification d'un événement — organisateurs uniquement. */
const props = defineProps<{ id?: string }>()
const router = useRouter()
const { t } = useI18n()

const title = ref('')
const description = ref('')
const location = ref('')
const startsAt = ref('')
const endsAt = ref('')
const capacity = ref('')
const save = useAsyncAction()
const load = useAsyncAction()

onMounted(async () => {
  if (!props.id) return
  const detail = await load.run(() => services.events.get(props.id as string))
  if (detail) {
    title.value = detail.summary.title
    description.value = detail.description
    location.value = detail.summary.location
    startsAt.value = toLocalInput(detail.summary.startsAt)
    endsAt.value = toLocalInput(detail.summary.endsAt)
    capacity.value = detail.summary.capacity?.toString() ?? ''
  }
})

async function submit(): Promise<void> {
  const draft = {
    title: title.value,
    description: description.value,
    location: location.value,
    startsAt: startsAt.value ? localInputToIso(startsAt.value) : '',
    endsAt: endsAt.value ? localInputToIso(endsAt.value) : '',
    capacity: capacity.value ? Number.parseInt(capacity.value, 10) : null,
  }
  const saved = await save.run(() => (props.id ? services.events.update(props.id, draft) : services.events.create(draft)))
  if (saved) await router.push(`/evenements/${saved.id}`)
}
</script>

<template>
  <section class="page page--form stack">
    <h1>{{ id ? t('events.form.editTitle') : t('events.form.createTitle') }}</h1>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p class="muted">
      {{ id ? t('events.form.editHint') : t('events.form.createHint') }}
    </p>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField v-model="title" :label="t('events.form.title')" required :maxlength="120" />
      <BaseField v-model="location" :label="t('events.form.location')" required :maxlength="160" />
      <div class="dates">
        <BaseField v-model="startsAt" :label="t('events.form.startsAt')" type="datetime-local" required />
        <BaseField v-model="endsAt" :label="t('events.form.endsAt')" type="datetime-local" required />
      </div>
      <BaseField v-model="capacity" :label="t('events.form.capacity')" type="number" />
      <BaseField v-model="description" :label="t('events.form.description')" multiline :maxlength="5000" />
      <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
      <BaseButton type="submit" :pending="save.pending.value">{{ id ? t('common.save') : t('events.form.publish') }}</BaseButton>
    </form>
  </section>
</template>

<style scoped>
.page--form {
  max-width: 40rem;
}

.dates {
  display: grid;
  gap: 1rem;
}

@media (min-width: 640px) {
  .dates {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
