<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import LineIcon from '@/components/LineIcon.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { Passkey } from '@/models/Passkey'
import { internalPath } from '@/router/redirect'
import { useServices } from '@/services'
import { useAuthStore } from '@/stores/auth'

const services = useServices()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const target = internalPath(route.query.redirect)
/** Code TOTP actif à l'arrivée : l'accès reste ouvert, la clé peut attendre (« Plus tard ») */
const hadCode = auth.user?.twoFactorEnabled === true
const created = ref<Passkey | null>(null)
const { pending, error, errorCode, run } = useAsyncAction()
const reauthenticate = computed(() => errorCode.value === 'reauthentication_required')
const site = window.location.hostname

// Page du bureau : un autre compte gère ses clés depuis « Sécurité », une clé existante ne se recrée pas ici
if (!auth.user?.passkeyRequired) void router.replace({ name: 'account-security' })
else if (auth.user.hasPasskey) void router.replace(target)

const BENEFITS = [
  { icon: 'fingerprint', title: 'account.passkeySetup.typingTitle', text: 'account.passkeySetup.typingText' },
  { icon: 'shield', title: 'account.passkeySetup.phishingTitle', text: 'account.passkeySetup.phishingText' },
  { icon: 'phone', title: 'account.passkeySetup.deviceTitle', text: 'account.passkeySetup.deviceText' },
] as const

async function create(): Promise<void> {
  const keys = await run(() => services.passkeys.add())
  if (!keys) return
  created.value = keys.reduce<Passkey | null>((newest, key) => (newest && newest.createdAt > key.createdAt ? newest : key), null)
  await auth.refresh().catch(() => undefined)
}

/** Ajout réservé aux 10 minutes qui suivent une connexion : se reconnecter puis revenir ici */
async function logInAgain(): Promise<void> {
  await auth.logout().catch(() => undefined)
  await router.replace({ name: 'login', query: { redirect: route.fullPath } })
}
</script>

<template>
  <section v-if="created" class="page page--narrow setup setup--done" aria-labelledby="done-title">
    <span class="done-badge"><LineIcon name="check" :size="52" /></span>
    <h1 id="done-title">{{ t('account.passkeySetup.doneTitle') }}</h1>
    <p class="muted lead">
      {{ t('account.passkeySetup.doneText') }}
      <template v-if="hadCode">{{ t('account.passkeySetup.doneCodeGone') }}</template>
    </p>

    <p class="key-card">
      <span class="tile"><LineIcon :name="created.device" /></span>
      <span class="key-card__text">
        <strong>{{ created.label || t('account.passkeys.unnamed') }}</strong>
        <span class="muted">{{ created.synced ? t('account.passkeySetup.doneSynced') : t('account.passkeySetup.doneLocal') }}</span>
      </span>
    </p>

    <div class="setup__footer">
      <RouterLink class="cta" :to="target">{{ t('account.passkeySetup.continue') }}</RouterLink>
      <i18n-t keypath="account.passkeySetup.tip" tag="p" class="muted hint" scope="global">
        <template #link>
          <RouterLink to="/compte/securite">{{ t('account.passkeySetup.tipLink') }}</RouterLink>
        </template>
      </i18n-t>
    </div>
  </section>

  <section v-else class="page page--narrow setup" aria-labelledby="setup-title">
    <p class="eyebrow">{{ t('account.passkeySetup.eyebrow') }}</p>
    <h1 id="setup-title">{{ hadCode ? t('account.passkeySetup.titleReplace') : t('account.passkeySetup.titleCreate') }}</h1>
    <p class="muted lead">{{ t('account.passkeySetup.lead') }}</p>

    <ul class="benefits">
      <li v-for="b in BENEFITS" :key="b.icon">
        <span class="tile"><LineIcon :name="b.icon" /></span>
        <span>
          <strong>{{ t(b.title) }}</strong> <span class="muted">{{ t(b.text, { site }) }}</span>
        </span>
      </li>
    </ul>

    <div class="setup__footer">
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <BaseButton v-if="reauthenticate" @click="logInAgain">{{ t('account.passkeys.reauthenticate') }}</BaseButton>
      <BaseButton v-else :pending="pending" @click="create">{{ t('account.passkeySetup.create') }}</BaseButton>
      <p class="muted hint">{{ t('account.passkeySetup.createHint') }}</p>
      <RouterLink v-if="hadCode" class="later" :to="target">{{ t('account.passkeySetup.later') }}</RouterLink>
    </div>
  </section>
</template>

<style scoped>
.setup {
  display: grid;
  gap: 1rem;
}

.setup h1 {
  margin: 0;
  text-wrap: balance;
}

.setup--done {
  justify-items: center;
  text-align: center;
}

.eyebrow {
  margin: 0;
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.lead {
  margin: 0;
}

.benefits {
  display: grid;
  gap: 0.9rem;
  margin: 0.25rem 0 0;
  padding: 0;
  list-style: none;
}

.benefits li {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
}

.tile {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.setup__footer {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  margin-top: 0.75rem;
}

.hint {
  margin: 0;
  font-size: 0.9rem;
  text-align: center;
}

.later {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
}

.done-badge {
  display: grid;
  place-items: center;
  width: 6.5rem;
  height: 6.5rem;
  margin-top: 1.5rem;
  border-radius: 50%;
  background: color-mix(in srgb, var(--color-success) 16%, transparent);
  color: var(--color-success);
}

.key-card {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  width: 100%;
  margin: 0.5rem 0 0;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  text-align: start;
}

.key-card__text {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
  overflow-wrap: anywhere;
}

.cta {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 3.25rem;
  padding: 0.6rem 1.2rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  text-decoration: none;
}

.cta:hover {
  background: var(--color-accent-hover);
}
</style>
