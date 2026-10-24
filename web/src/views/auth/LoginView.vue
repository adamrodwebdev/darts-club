<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import OAuthButtons from '@/components/OAuthButtons.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useStages } from '@/composables/useStages'
import { useServices } from '@/services'
import { useAuthStore } from '@/stores/auth'

const services = useServices()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const step = ref<'password' | 'code'>('password')
const code = ref('')
const { pending, error, errorCode, run } = useAsyncAction()
const resend = useAsyncAction()
const resent = ref(false)
/**
 * Connexion par un fournisseur une image après le formulaire : le rendu de la page reste sous le
 * seuil d'une tâche longue au démarrage (Lighthouse : temps de blocage). En bas de page, elle ne
 * décale rien au-dessus d'elle.
 */
const extras = useStages(1, { all: false })

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
  const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/compte'
  await router.replace(redirect)
}

async function submit(): Promise<void> {
  const status = await run(() => auth.login(email.value, password.value))
  if (status === 'authenticated') await goToRedirect()
  if (status === 'two-factor') {
    password.value = ''
    step.value = 'code'
  }
}

async function submitCode(): Promise<void> {
  const ok = await run(async () => {
    await auth.verifyTwoFactor(code.value)
    return true
  })
  if (ok) await goToRedirect()
}

async function restart(): Promise<void> {
  await auth.logout().catch(() => undefined)
  code.value = ''
  step.value = 'password'
}

async function resendLink(): Promise<void> {
  await resend.run(() => services.auth.resendVerification(email.value))
  resent.value = resend.error.value === null
}
</script>

<template>
  <section class="page page--narrow">
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

    <OAuthButtons v-if="step === 'password' && extras >= 1" />

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
</style>
