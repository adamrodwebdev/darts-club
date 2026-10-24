<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import LineIcon from '@/components/LineIcon.vue'
import OAuthButtons from '@/components/OAuthButtons.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { internalPath } from '@/router/redirect'
import { useServices } from '@/services'
import type { LoginStep } from '@/services/AuthService'
import { PasskeyError } from '@/services/PasskeyService'
import { useAuthStore } from '@/stores/auth'

const services = useServices()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const step = ref<'password' | 'code' | 'passkey'>('password')
const code = ref('')
const { pending, error, errorCode, run } = useAsyncAction()
const resend = useAsyncAction()
const resent = ref(false)
/** Sans WebAuthn dans le navigateur, aucun bouton de clé d'accès */
const passkeySupported = services.passkeys.isSupported()

const notice = computed(() => {
  switch (route.query.verify) {
    case 'success':
      return { kind: 'success', text: t('auth.login.verifySuccess') }
    case 'invalid':
      return { kind: 'error', text: t('auth.login.verifyInvalid') }
  }
  if (route.query.oauth === 'failed') {
    return { kind: 'error', text: t('auth.login.oauthFailed') }
  }
  if (route.query.reset === 'success') {
    return { kind: 'success', text: t('auth.login.resetSuccess') }
  }
  return null
})

const unverified = computed(() => errorCode.value === 'email_not_verified')

async function goToRedirect(): Promise<void> {
  const target = internalPath(route.query.redirect)
  // Bureau sans clé d'accès : la créer d'abord (obligatoire sans code, proposée avec)
  if (auth.user?.passkeyRequired && !auth.user.hasPasskey) {
    await router.replace({ name: 'passkey-setup', query: { redirect: target } })
    return
  }
  await router.replace(target)
}

/** Suite de la connexion selon la réponse de l'API */
const NEXT: Record<LoginStep, () => Promise<void>> = {
  authenticated: goToRedirect,
  totp: async () => {
    password.value = ''
    step.value = 'code'
  },
  passkey: async () => {
    password.value = ''
    step.value = 'passkey'
    await confirmPasskey(true)
  },
}

async function submit(): Promise<void> {
  const next = await run(() => auth.login(email.value, password.value))
  if (next) await NEXT[next]()
}

async function loginWithPasskey(): Promise<void> {
  const next = await run(() => auth.loginWithPasskey())
  if (next) await NEXT[next]()
}

async function submitCode(): Promise<void> {
  const ok = await run(async () => {
    await auth.verifyTwoFactor(code.value)
    return true
  })
  if (ok) await goToRedirect()
}

/**
 * Deuxième étape du bureau. Lancée d'elle-même après le mot de passe ; si le navigateur exige un
 * geste (Safari), elle attend le bouton sans afficher d'erreur.
 */
async function confirmPasskey(automatic = false): Promise<void> {
  const ok = await run(async () => {
    try {
      await auth.confirmWithPasskey()
      return true
    } catch (e) {
      if (automatic && e instanceof PasskeyError && e.cancelled) return false
      throw e
    }
  })
  if (ok) await goToRedirect()
}

async function restart(): Promise<void> {
  await auth.logout().catch(() => undefined)
  code.value = ''
  error.value = null
  step.value = 'password'
}

async function resendLink(): Promise<void> {
  await resend.run(() => services.auth.resendVerification(email.value))
  resent.value = resend.error.value === null
}
</script>

<template>
  <section v-if="step === 'passkey'" class="page page--narrow confirm" aria-labelledby="confirm-title">
    <span class="confirm__badge"><LineIcon name="fingerprint" :size="56" /></span>
    <p class="eyebrow">{{ t('auth.passkeyStep.eyebrow') }}</p>
    <h1 id="confirm-title">{{ t('auth.passkeyStep.title') }}</h1>
    <p class="muted confirm__text">{{ t('auth.passkeyStep.text') }}</p>

    <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
    <div class="stack confirm__actions">
      <BaseButton :pending="pending" @click="confirmPasskey()">{{ t('auth.passkeyStep.use') }}</BaseButton>
      <BaseButton variant="ghost" @click="restart">{{ t('common.cancel') }}</BaseButton>
    </div>

    <p class="lost">
      <LineIcon name="info" />
      <span><strong>{{ t('auth.passkeyStep.lostTitle') }}</strong> {{ t('auth.passkeyStep.lostText') }}</span>
    </p>
  </section>

  <section v-else class="page page--narrow">
    <h1>{{ t('auth.login.title') }}</h1>

    <p v-if="notice" class="notice" :class="`notice--${notice.kind}`" role="status">{{ notice.text }}</p>

    <form v-if="step === 'code'" class="stack" novalidate @submit.prevent="submitCode">
      <p>{{ t('auth.login.codeIntro') }}</p>
      <BaseField v-model="code" :label="t('auth.login.codeLabel')" autocomplete="one-time-code" :maxlength="20" required />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <BaseButton type="submit" :pending="pending">{{ t('auth.login.verify') }}</BaseButton>
      <BaseButton variant="ghost" @click="restart">{{ t('common.cancel') }}</BaseButton>
    </form>

    <form v-else class="stack" novalidate @submit.prevent="submit">
      <BaseField v-model="email" :label="t('common.email')" type="email" autocomplete="email" required />
      <BaseField v-model="password" :label="t('common.password')" type="password" autocomplete="current-password" required />
      <RouterLink class="forgot" to="/mot-de-passe-oublie">{{ t('auth.login.forgot') }}</RouterLink>

      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

      <BaseButton type="submit" :pending="pending">{{ t('auth.login.submit') }}</BaseButton>
    </form>

    <div v-if="unverified || route.query.verify === 'invalid'" class="stack resend">
      <BaseButton variant="ghost" :pending="resend.pending.value" :disabled="!email" @click="resendLink">
        {{ t('auth.login.resend') }}
      </BaseButton>
      <p v-if="resent" class="notice notice--success" role="status">{{ t('auth.login.resent') }}</p>
      <p v-if="resend.error.value" class="notice notice--error" role="alert">{{ resend.error.value }}</p>
    </div>

    <div v-if="step === 'password' && passkeySupported" class="passkey">
      <p class="separator"><span>{{ t('auth.oauthSeparator') }}</span></p>
      <button type="button" class="passkey__button" :disabled="pending" @click="loginWithPasskey">
        <LineIcon name="fingerprint" />
        {{ t('auth.login.passkey') }}
      </button>
      <p class="muted passkey__hint">{{ t('auth.login.passkeyHint') }}</p>
    </div>

    <OAuthButtons v-if="step === 'password'" :separated="!passkeySupported" />

    <p class="switch">{{ t('auth.login.noAccount') }} <RouterLink to="/inscription">{{ t('auth.login.join') }}</RouterLink></p>
  </section>
</template>

<style scoped>
.resend {
  margin-top: 1rem;
}

.switch {
  margin-top: 1.5rem;
  text-align: center;
}

.forgot {
  display: inline-flex;
  align-items: center;
  justify-self: end;
  min-height: 2.75rem;
  margin-top: -0.75rem;
}

.passkey {
  display: grid;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.separator {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  color: var(--color-muted);
  font-size: 0.9rem;
}

.separator::before,
.separator::after {
  content: '';
  flex: 1;
  border-top: 1px solid var(--color-border);
}

/* Bouton secondaire marqué : le geste le plus simple pour qui a déjà une clé */
.passkey__button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  min-height: 3.25rem;
  padding: 0.6rem 1rem;
  border: 1px solid var(--color-text);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.15s;
}

.passkey__button:hover {
  background: var(--color-surface-2);
}

.passkey__button:disabled {
  cursor: progress;
  opacity: 0.7;
}

.passkey__hint {
  margin: 0;
  font-size: 0.9rem;
  text-align: center;
}

/* Deuxième étape par clé d'accès : un seul geste à faire, centré */
.confirm {
  display: grid;
  justify-items: center;
  gap: 1rem;
  text-align: center;
}

.confirm h1 {
  margin: 0;
  text-wrap: balance;
}

.confirm__badge {
  display: grid;
  place-items: center;
  width: 7rem;
  height: 7rem;
  margin-top: 1rem;
  border: 2px solid var(--color-accent);
  border-radius: 50%;
  background: var(--color-surface-2);
}

.eyebrow {
  margin: 0.5rem 0 0;
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.confirm__text {
  max-width: 20rem;
  margin: 0;
}

.confirm__actions {
  width: 100%;
  margin-top: 0.5rem;
}

.lost {
  display: flex;
  gap: 0.75rem;
  width: 100%;
  margin: 1.5rem 0 0;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-muted);
  font-size: 0.9rem;
  text-align: start;
}

.lost strong {
  color: var(--color-text);
}
</style>
