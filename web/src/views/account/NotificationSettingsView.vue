<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import CalendarSubscription from '@/components/events/CalendarSubscription.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CATEGORIES, type OptionalChannel, type Preferences } from '@/models/Notification'
import { services } from '@/services'

const { t } = useI18n()

const CHANNELS: readonly OptionalChannel[] = ['email', 'push']

function channelLabel(channel: OptionalChannel): string {
  return t(`account.notificationSettings.channels.${channel}`)
}

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
      <h2 id="push-title">{{ t('account.notificationSettings.pushTitle') }}</h2>
      <template v-if="pushSupported">
        <p class="muted">{{ pushEnabled ? t('account.notificationSettings.pushOn') : t('account.notificationSettings.pushOff') }}</p>
        <BaseButton :variant="pushEnabled ? 'ghost' : 'primary'" :pending="push.pending.value" @click="togglePush">
          {{ pushEnabled ? t('account.notificationSettings.disable') : t('account.notificationSettings.enable') }}
        </BaseButton>
        <p v-if="push.error.value" class="notice notice--error" role="alert">{{ push.error.value }}</p>
      </template>
      <p v-else class="muted">{{ t('account.notificationSettings.pushUnsupported') }}</p>
    </section>

    <section class="card" aria-labelledby="prefs-title">
      <h2 id="prefs-title">{{ t('account.notificationSettings.prefsTitle') }}</h2>
      <p class="muted">{{ t('account.notificationSettings.prefsIntro') }}</p>
      <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

      <form @submit.prevent="submit">
        <table class="prefs">
          <caption class="visually-hidden">{{ t('account.notificationSettings.caption') }}</caption>
          <thead>
            <tr>
              <th scope="col">{{ t('account.notificationSettings.category') }}</th>
              <th v-for="c in CHANNELS" :key="c" scope="col">{{ channelLabel(c) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="category in CATEGORIES" :key="category.id">
              <th scope="row">{{ category.label }}</th>
              <td v-for="c in CHANNELS" :key="c">
                <input
                  type="checkbox"
                  :checked="isOn(category.id, c)"
                  :aria-label="t('account.notificationSettings.toggle', { category: category.label, channel: channelLabel(c) })"
                  @change="toggle(category.id, c, ($event.target as HTMLInputElement).checked)"
                />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
        <p v-if="saved" class="notice notice--success" role="status">{{ t('account.notificationSettings.saved') }}</p>
        <BaseButton type="submit" :pending="save.pending.value">{{ t('common.save') }}</BaseButton>
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
