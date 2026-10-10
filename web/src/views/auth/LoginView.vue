<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import OAuthButtons from '@/components/OAuthButtons.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const { pending, error, run } = useAsyncAction()
const resend = useAsyncAction()
const resent = ref(false)

const notice = computed(() => {
  switch (route.query.verify) {
    case 'success':
      return { kind: 'success', text: 'Adresse confirmée : tu peux te connecter.' }
    case 'invalid':
      return { kind: 'error', text: 'Lien de confirmation invalide ou expiré. Demande un nouveau lien ci-dessous.' }
  }
  if (route.query.oauth === 'failed') {
    return { kind: 'error', text: 'Connexion externe impossible : ton fournisseur n’a pas confirmé ton email.' }
  }
  return null
})

const unverified = computed(() => error.value?.includes('non vérifiée') ?? false)

async function submit(): Promise<void> {
  const ok = await run(async () => {
    await auth.login(email.value, password.value)
    return true
  })
  if (ok) {
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/compte'
    await router.replace(redirect)
  }
}

async function resendLink(): Promise<void> {
  await resend.run(() => services.auth.resendVerification(email.value))
  resent.value = resend.error.value === null
}
</script>

<template>
  <section class="page page--narrow">
    <h1>Connexion</h1>

    <p v-if="notice" class="notice" :class="`notice--${notice.kind}`" role="status">{{ notice.text }}</p>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField v-model="email" label="Email" type="email" autocomplete="email" required />
      <BaseField v-model="password" label="Mot de passe" type="password" autocomplete="current-password" required />

      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

      <BaseButton type="submit" :pending="pending">Se connecter</BaseButton>
    </form>

    <div v-if="unverified || route.query.verify === 'invalid'" class="stack resend">
      <BaseButton variant="ghost" :pending="resend.pending.value" :disabled="!email" @click="resendLink">
        Renvoyer le lien de confirmation
      </BaseButton>
      <p v-if="resent" class="notice notice--success" role="status">Si un compte en attente existe, un nouveau lien vient d’être envoyé.</p>
      <p v-if="resend.error.value" class="notice notice--error" role="alert">{{ resend.error.value }}</p>
    </div>

    <OAuthButtons />

    <p class="switch">Pas encore de compte ? <RouterLink to="/inscription">Rejoindre le club</RouterLink></p>
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
</style>
