<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useServices } from '@/services'

const services = useServices()
const { t } = useI18n()

const email = ref('')
const sent = ref(false)
const { pending, error, fieldErrors, run } = useAsyncAction()

async function submit(): Promise<void> {
  await run(async () => {
    await services.auth.forgotPassword(email.value)
    sent.value = true
  })
}
</script>

<template>
  <section class="page page--narrow">
    <h1>{{ t('auth.forgot.title') }}</h1>

    <div v-if="sent" class="notice notice--success" role="status">
      <p><strong>{{ t('auth.register.checkInbox') }}</strong></p>
      <p>{{ t('auth.forgot.sent') }}</p>
    </div>

    <form v-else class="stack" novalidate @submit.prevent="submit">
      <p>{{ t('auth.forgot.intro') }}</p>
      <BaseField v-model="email" :label="t('common.email')" type="email" autocomplete="email" required :error="fieldErrors.email" />
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <BaseButton type="submit" :pending="pending">{{ t('auth.forgot.submit') }}</BaseButton>
    </form>

    <p class="switch"><RouterLink to="/connexion">{{ t('auth.forgot.back') }}</RouterLink></p>
  </section>
</template>

<style scoped>
.switch {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
