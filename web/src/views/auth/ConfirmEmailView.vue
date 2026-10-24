<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useServices } from '@/services'
import { AuthService } from '@/services/AuthService'
import { useAuthStore } from '@/stores/auth'

/** Paramètres signés du lien de confirmation */
const LINK_KEYS = ['expires', 'id', 'signature', 'token'] as const

const services = useServices()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const password = ref('')
const linkQuery = computed(() => AuthService.linkQuery(route.query, LINK_KEYS))
const { pending, error, fieldErrors, run } = useAsyncAction()

/** Adresse confirmée avec le mot de passe de l'inscription, puis connexion avec ce même mot de passe */
async function submit(): Promise<void> {
  const next = await run(async () => auth.login(await services.auth.confirmEmail(linkQuery.value, password.value), password.value))
  if (next === 'authenticated') await router.replace('/compte')
  // Deuxième étape (code ou clé d'accès) : elle se fait depuis la page de connexion
  else if (next) await router.replace('/connexion')
}
</script>

<template>
  <section class="page page--narrow">
    <h1>{{ t('auth.confirm.title') }}</h1>
    <p>{{ t('auth.confirm.intro') }}</p>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField
        v-model="password"
        :label="t('common.password')"
        type="password"
        autocomplete="current-password"
        required
        :error="fieldErrors.password"
      />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <BaseButton type="submit" :pending="pending">{{ t('auth.confirm.submit') }}</BaseButton>
    </form>

    <p class="switch">{{ t('auth.confirm.notYou') }} <RouterLink to="/mot-de-passe-oublie">{{ t('auth.confirm.choosePassword') }}</RouterLink></p>
  </section>
</template>

<style scoped>
.switch {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
