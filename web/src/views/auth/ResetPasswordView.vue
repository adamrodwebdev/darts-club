<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import PasswordRules from '@/components/PasswordRules.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useServices } from '@/services'
import { AuthService } from '@/services/AuthService'

/** Paramètres du lien « mot de passe oublié » */
const LINK_KEYS = ['id', 'expires', 'token'] as const

const services = useServices()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const password = ref('')
const rulesId = useId()
const linkQuery = computed(() => AuthService.linkQuery(route.query, LINK_KEYS))
const { pending, error, fieldErrors, run } = useAsyncAction()

async function submit(): Promise<void> {
  const done = await run(async () => (await services.auth.resetPassword(linkQuery.value, password.value), true))
  if (done) await router.replace({ path: '/connexion', query: { reset: 'success' } })
}
</script>

<template>
  <section class="page page--narrow">
    <h1>{{ t('auth.reset.title') }}</h1>

    <form class="stack" novalidate @submit.prevent="submit">
      <BaseField
        v-model="password"
        :label="t('auth.reset.password')"
        type="password"
        autocomplete="new-password"
        required
        :error="fieldErrors.password"
        :describedby="rulesId"
      >
        <PasswordRules :id="rulesId" :password="password" />
      </BaseField>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <BaseButton type="submit" :pending="pending">{{ t('auth.reset.submit') }}</BaseButton>
    </form>

    <p class="switch"><RouterLink to="/mot-de-passe-oublie">{{ t('auth.reset.newLink') }}</RouterLink></p>
  </section>
</template>

<style scoped>
.switch {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
