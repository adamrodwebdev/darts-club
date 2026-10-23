<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import PosterField from '@/components/events/PosterField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { localInputToIso, toLocalInput, type EventPoster } from '@/models/ClubEvent'
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
const isPublic = ref(false)
const city = ref('')
/** Affiche déjà en ligne, nouveau fichier choisi, ou retrait demandé */
const currentPoster = ref<EventPoster | null>(null)
const posterFile = ref<File | null>(null)
const removePoster = ref(false)
/** Événement déjà créé : un nouvel envoi le modifie au lieu d'en créer un second */
const eventId = ref(props.id)
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
    currentPoster.value = detail.summary.poster
    isPublic.value = detail.summary.isPublic
    city.value = detail.summary.city ?? ''
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
    public: isPublic.value,
    city: city.value,
  }
  const saved = await save.run(async () => {
    const id = eventId.value
    const event = id ? await services.events.update(id, draft) : await services.events.create(draft)
    eventId.value = event.id
    // Affiche envoyée une fois l'événement enregistré ; en cas d'échec, le formulaire reste ouvert
    if (posterFile.value) await services.events.setPoster(event.id, posterFile.value)
    else if (removePoster.value && currentPoster.value) await services.events.removePoster(event.id)
    return event
  })
  if (saved) await router.push(`/evenements/${saved.id}`)
}
</script>

<template>
  <section class="page page--form stack">
    <h1>{{ eventId ? t('events.form.editTitle') : t('events.form.createTitle') }}</h1>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p class="muted">
      {{ id ? t('events.form.editHint') : t('events.form.createHint') }}
    </p>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField v-model="title" :label="t('events.form.title')" required :maxlength="120" />
      <BaseField v-model="location" :label="t('events.form.location')" :hint="t('events.form.locationHint')" required :maxlength="160" />
      <div class="dates">
        <BaseField v-model="startsAt" :label="t('events.form.startsAt')" type="datetime-local" required />
        <BaseField v-model="endsAt" :label="t('events.form.endsAt')" type="datetime-local" required />
      </div>
      <BaseField v-model="capacity" :label="t('events.form.capacity')" type="number" />
      <BaseField v-model="description" :label="t('events.form.description')" multiline :maxlength="5000" />
      <PosterField v-model:file="posterFile" v-model:remove="removePoster" :current="currentPoster" />
      <fieldset class="public">
        <legend>{{ t('events.form.publicLegend') }}</legend>
        <label class="check">
          <input v-model="isPublic" type="checkbox">
          <span>{{ t('events.form.public') }}</span>
        </label>
        <p class="muted hint">{{ t('events.form.publicHint') }}</p>
        <BaseField v-if="isPublic" v-model="city" :label="t('events.form.city')" :hint="t('events.form.cityHint')" required :maxlength="80" />
      </fieldset>
      <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
      <BaseButton type="submit" :pending="save.pending.value">{{ eventId ? t('common.save') : t('events.form.publish') }}</BaseButton>
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

.public {
  display: grid;
  gap: 0.6rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.public legend {
  padding: 0;
  font-weight: 600;
  font-size: 0.95rem;
}

.check {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.75rem;
  cursor: pointer;
}

.check input {
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.hint {
  margin: 0;
  font-size: 0.85rem;
}

@media (width >= 40rem) {
  .dates {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
