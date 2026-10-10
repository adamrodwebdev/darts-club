<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { localInputToIso, toLocalInput } from '@/models/ClubEvent'
import { services } from '@/services'

/** Création (sans id) ou modification d'un événement — organisateurs uniquement. */
const props = defineProps<{ id?: string }>()
const router = useRouter()

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
    <h1>{{ id ? 'Modifier l’événement' : 'Nouvel événement' }}</h1>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p class="muted">
      {{ id ? 'Un changement de date prévient les inscrits.' : 'Tous les adhérents seront prévenus, puis relancés à J-7 ; les inscrits à J-1.' }}
    </p>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField v-model="title" label="Titre" required :maxlength="120" />
      <BaseField v-model="location" label="Lieu" required :maxlength="160" />
      <div class="dates">
        <BaseField v-model="startsAt" label="Début" type="datetime-local" required />
        <BaseField v-model="endsAt" label="Fin" type="datetime-local" required />
      </div>
      <BaseField v-model="capacity" label="Nombre de places (vide = illimité)" type="number" />
      <BaseField v-model="description" label="Présentation" multiline :maxlength="5000" />
      <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
      <BaseButton type="submit" :pending="save.pending.value">{{ id ? 'Enregistrer' : 'Publier l’événement' }}</BaseButton>
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
