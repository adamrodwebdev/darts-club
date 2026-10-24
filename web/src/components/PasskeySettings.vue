<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import LineIcon from '@/components/LineIcon.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { formatDate, formatRelative } from '@/i18n/format'
import type { Passkey } from '@/models/Passkey'
import { useServices } from '@/services'
import { useAuthStore } from '@/stores/auth'

const services = useServices()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const keys = ref<Passkey[]>([])
const info = ref<string | null>(null)
const removing = ref<Passkey | null>(null)
const load = useAsyncAction()
const action = useAsyncAction()
const supported = services.passkeys.isSupported()
/** Ajout ou retrait réservé aux 10 minutes qui suivent une connexion */
const reauthenticate = computed(() => action.errorCode.value === 'reauthentication_required')
/** Dernière clé d'un membre du bureau : la retirer bloque ses fonctions jusqu'à la suivante */
const removingLast = computed(() => auth.user?.passkeyRequired === true && keys.value.length === 1)

onMounted(async () => {
  const list = await load.run(() => services.passkeys.list())
  if (list) keys.value = list
})

function details(key: Passkey): string {
  const used = key.lastUsedAt
    ? t('account.passkeys.used', { when: formatRelative(key.lastUsedAt, services.clock.now()) ?? t('common.justNow') })
    : t('account.passkeys.neverUsed')
  const parts = [t('account.passkeys.created', { date: formatDate(key.createdAt) }), used]
  if (key.synced) parts.push(t('account.passkeys.synced'))
  return parts.join(' · ')
}

async function add(): Promise<void> {
  info.value = null
  const list = await action.run(() => services.passkeys.add())
  if (!list) return
  keys.value = list
  info.value = t('account.passkeys.added')
  await auth.refresh().catch(() => undefined)
}

async function remove(): Promise<void> {
  const key = removing.value
  if (!key) return
  removing.value = null
  info.value = null
  const ok = await action.run(async () => {
    await services.passkeys.remove(key.id)
    return true
  })
  if (!ok) return
  keys.value = keys.value.filter((k) => k.id !== key.id)
  info.value = t('account.passkeys.removed')
  await auth.refresh().catch(() => undefined)
}

async function logInAgain(): Promise<void> {
  await auth.logout().catch(() => undefined)
  await router.replace({ name: 'login', query: { redirect: route.fullPath } })
}
</script>

<template>
  <section class="card stack" aria-labelledby="passkeys-title">
    <div class="heading">
      <h2 id="passkeys-title">{{ t('account.passkeys.title') }}</h2>
      <span v-if="auth.user?.passkeyRequired" class="pill">{{ t('account.passkeys.required') }}</span>
    </div>
    <p class="muted">{{ keys.length ? t('account.passkeys.intro') : t('account.passkeys.empty') }}</p>

    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <ul v-if="keys.length" class="keys">
      <li v-for="key in keys" :key="key.id" class="key">
        <span class="tile"><LineIcon :name="key.device" /></span>
        <span class="key__text">
          <strong>{{ key.label || t('account.passkeys.unnamed') }}</strong>
          <span class="muted">{{ details(key) }}</span>
        </span>
        <BaseButton class="key__remove" variant="ghost" :disabled="action.pending.value" @click="removing = key">{{ t('account.passkeys.remove') }}</BaseButton>
      </li>
    </ul>

    <p v-if="info" class="notice notice--success" role="status">{{ info }}</p>
    <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>

    <div class="add">
      <BaseButton v-if="reauthenticate" @click="logInAgain">{{ t('account.passkeys.reauthenticate') }}</BaseButton>
      <BaseButton v-else :pending="action.pending.value" :disabled="!supported" @click="add">{{ t('account.passkeys.add') }}</BaseButton>
      <span class="muted">{{ supported ? t('account.passkeys.addHint') : t('account.passkeys.unsupported') }}</span>
    </div>

    <BaseDialog v-if="removing" :title="t('account.passkeys.removeTitle', { name: removing.label || t('account.passkeys.unnamed') })" @close="removing = null">
      <form class="stack" @submit.prevent="remove">
        <p class="muted dialog-text">{{ t('account.passkeys.removeText') }}</p>
        <p v-if="removingLast" class="notice notice--error">{{ t('account.passkeys.removeLast') }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="removing = null">{{ t('common.cancel') }}</BaseButton>
          <BaseButton type="submit" variant="danger">{{ t('account.passkeys.remove') }}</BaseButton>
        </div>
      </form>
    </BaseDialog>
  </section>
</template>

<style scoped>
.heading {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.5rem;
}

.heading h2 {
  margin: 0;
}

.pill {
  padding: 0.2rem 0.65rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.8rem;
  font-weight: 700;
}

.keys {
  container: passkey-list / inline-size;
  margin: 0;
  padding: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  list-style: none;
}

/* Étroit : pictogramme et texte, bouton dessous ; large : tout sur une ligne */
.key {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.75rem 1rem;
  padding: 1rem;
}

.key__remove {
  grid-column: 2;
  justify-self: start;
}

@container passkey-list (width >= 30rem) {
  .key {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .key__remove {
    grid-column: auto;
  }
}

.key + .key {
  border-top: 1px solid var(--color-border);
}

.tile {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.key__text {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
  overflow-wrap: anywhere;
}

.key__text .muted {
  font-size: 0.9rem;
}

.add {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
}

.add .muted {
  font-size: 0.9rem;
}

.dialog-text {
  margin: 0;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
