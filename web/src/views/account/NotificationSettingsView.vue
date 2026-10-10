<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import CalendarSubscription from '@/components/events/CalendarSubscription.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CATEGORIES, type OptionalChannel, type Preferences } from '@/models/Notification'
import { services } from '@/services'

const CHANNELS: { id: OptionalChannel; label: string }[] = [
  { id: 'email', label: 'Email' },
  { id: 'push', label: 'Push' },
]

const preferences = ref<Preferences>({})
const saved = ref(false)
const load = useAsyncAction()
const save = useAsyncAction()

const pushSupported = services.push.isSupported()
const pushEnabled = ref(false)
const push = useAsyncAction()

onMounted(async () => {
  const prefs = await load.run(() => services.notifications.preferences())
  if (prefs) preferences.value = prefs
  pushEnabled.value = await services.push.isSubscribed().catch(() => false)
})

function isOn(category: string, channel: OptionalChannel): boolean {
  return preferences.value[category]?.includes(channel) ?? false
}

function toggle(category: string, channel: OptionalChannel, on: boolean): void {
  const current = new Set(preferences.value[category] ?? [])
  if (on) current.add(channel)
  else current.delete(channel)
  preferences.value = { ...preferences.value, [category]: [...current] }
  saved.value = false
}

async function submit(): Promise<void> {
  const prefs = await save.run(() => services.notifications.savePreferences(preferences.value))
  if (prefs) {
    preferences.value = prefs
    saved.value = true
  }
}

async function togglePush(): Promise<void> {
  await push.run(() => (pushEnabled.value ? services.push.unsubscribe() : services.push.subscribe()))
  pushEnabled.value = await services.push.isSubscribed().catch(() => false)
}
</script>

<template>
  <div class="stack">
    <CalendarSubscription />
    <section class="card" aria-labelledby="push-title">
      <h2 id="push-title">Notifications push sur cet appareil</h2>
      <template v-if="pushSupported">
        <p class="muted">{{ pushEnabled ? 'Activées sur ce navigateur.' : 'Désactivées sur ce navigateur.' }}</p>
        <BaseButton :variant="pushEnabled ? 'ghost' : 'primary'" :pending="push.pending.value" @click="togglePush">
          {{ pushEnabled ? 'Désactiver' : 'Activer' }}
        </BaseButton>
        <p v-if="push.error.value" class="notice notice--error" role="alert">{{ push.error.value }}</p>
      </template>
      <p v-else class="muted">Ce navigateur ne gère pas les notifications push. Sur iPhone, ajoute d’abord le site à l’écran d’accueil.</p>
    </section>

    <section class="card" aria-labelledby="prefs-title">
      <h2 id="prefs-title">Que recevoir, et où ?</h2>
      <p class="muted">Le centre de notifications du site reçoit toujours tout.</p>
      <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

      <form @submit.prevent="submit">
        <table class="prefs">
          <caption class="visually-hidden">Canaux activés par catégorie</caption>
          <thead>
            <tr>
              <th scope="col">Catégorie</th>
              <th v-for="c in CHANNELS" :key="c.id" scope="col">{{ c.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="category in CATEGORIES" :key="category.id">
              <th scope="row">{{ category.label }}</th>
              <td v-for="c in CHANNELS" :key="c.id">
                <input
                  type="checkbox"
                  :checked="isOn(category.id, c.id)"
                  :aria-label="`${category.label} par ${c.label}`"
                  @change="toggle(category.id, c.id, ($event.target as HTMLInputElement).checked)"
                />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
        <p v-if="saved" class="notice notice--success" role="status">Préférences enregistrées.</p>
        <BaseButton type="submit" :pending="save.pending.value">Enregistrer</BaseButton>
      </form>
    </section>
  </div>
</template>

<style scoped>
.prefs {
  width: 100%;
  margin: 1rem 0;
  border-collapse: collapse;
}

.prefs th,
.prefs td {
  padding: 0.25rem 0.5rem;
  border-bottom: 1px solid var(--color-border);
  text-align: center;
}

.prefs th[scope='row'],
.prefs thead th:first-child {
  text-align: left;
}

.prefs input {
  width: 1.4rem;
  height: 1.4rem;
  margin: 0.6rem;
  accent-color: var(--color-accent);
}
</style>
